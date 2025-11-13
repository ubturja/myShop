<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProvenanceEvent;
use App\Services\ProvenanceService;
use App\Services\Web3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * ProvenanceController - Handles supply chain provenance tracking.
 * 
 * Endpoints:
 * - POST /provenance/events - Create provenance event
 * - GET /provenance/events/{id} - Get event details
 * - GET /products/{id}/provenance - Get product's provenance chain
 */
class ProvenanceController extends Controller
{
    protected ProvenanceService $provenance;
    protected Web3Client $web3;

    public function __construct(ProvenanceService $provenanceService, Web3Client $web3Client)
    {
        $this->provenance = $provenanceService;
        $this->web3 = $web3Client;
    }

    /**
     * Create a provenance event for a product.
     * 
     * POST /api/provenance/events
     * 
     * Request:
     * {
     *   "product_id": 1,                    // Required: Product ID
     *   "event_type": "MANUFACTURED",       // Required: Event type
     *   "data": {                           // Required: Event-specific data
     *     "location": "Factory A",
     *     "inspector": "John Doe",
     *     "timestamp": "2024-01-01T00:00:00Z"
     *   },
     *   "anchor": true                      // Optional: Anchor hash to blockchain (default: false)
     * }
     * 
     * Response:
     * {
     *   "success": true,
     *   "event": {
     *     "id": 1,
     *     "product_id": 1,
     *     "event_type": "MANUFACTURED",
     *     "data": {...},
     *     "ipfs_hash": "QmXxx...",
     *     "ipfs_url": "https://gateway.pinata.cloud/ipfs/QmXxx...",
     *     "sha256": "abc123...",
     *     "tx_hash": "0x123...",             // If anchored
     *     "polygonscan_url": "https://...",  // If anchored
     *     "created_at": "2024-01-01T00:00:00Z"
     *   },
     *   "anchored": true,
     *   "mock_mode": true
     * }
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'event_type' => 'required|string|max:100',
            'data' => 'required|array',
            'anchor' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $productId = $request->input('product_id');
        $eventType = $request->input('event_type');
        $data = $request->input('data');
        $shouldAnchor = $request->input('anchor', false);

        try {
            DB::beginTransaction();

            // Prepare provenance metadata
            $metadata = [
                'product_id' => $productId,
                'event_type' => $eventType,
                'data' => $data,
                'timestamp' => now()->toIso8601String(),
            ];

            // Upload metadata to IPFS via Pinata
            $pinName = "provenance-{$productId}-{$eventType}-" . time();
            $uploadResult = $this->provenance->uploadJson($metadata, $pinName);

            Log::info('ProvenanceController: IPFS upload complete', [
                'product_id' => $productId,
                'event_type' => $eventType,
                'ipfs_hash' => $uploadResult['ipfs_hash'],
                'sha256' => $uploadResult['sha256'],
            ]);

            // Optionally anchor hash to blockchain
            $txHash = null;
            if ($shouldAnchor) {
                try {
                    $anchorResult = $this->web3->anchorHash($uploadResult['sha256']);
                    $txHash = $anchorResult['txHash'];
                    
                    Log::info('ProvenanceController: Hash anchored to blockchain', [
                        'sha256' => $uploadResult['sha256'],
                        'tx_hash' => $txHash,
                    ]);
                } catch (\Exception $e) {
                    // Log but don't fail the request if anchoring fails
                    Log::warning('ProvenanceController: Blockchain anchoring failed', [
                        'error' => $e->getMessage(),
                        'sha256' => $uploadResult['sha256'],
                    ]);
                }
            }

            // Save provenance event to database
            $event = ProvenanceEvent::create([
                'product_id' => $productId,
                'event_type' => $eventType,
                'data' => $data,
                'ipfs_hash' => $uploadResult['ipfs_hash'],
                'tx_hash' => $txHash,
            ]);

            DB::commit();

            Log::info('ProvenanceController: Event created', [
                'event_id' => $event->id,
                'product_id' => $productId,
            ]);

            return response()->json([
                'success' => true,
                'event' => [
                    'id' => $event->id,
                    'product_id' => $event->product_id,
                    'event_type' => $event->event_type,
                    'data' => $event->data,
                    'ipfs_hash' => $event->ipfs_hash,
                    'ipfs_url' => $event->ipfs_url,
                    'sha256' => $uploadResult['sha256'],
                    'tx_hash' => $event->tx_hash,
                    'polygonscan_url' => $event->polygonscan_url,
                    'created_at' => $event->created_at->toIso8601String(),
                ],
                'anchored' => $shouldAnchor && !empty($txHash),
                'mock_mode' => $this->provenance->isMockMode(),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('ProvenanceController: Event creation failed', [
                'error' => $e->getMessage(),
                'product_id' => $productId,
                'event_type' => $eventType,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create provenance event',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get provenance event details by ID.
     * 
     * GET /api/provenance/events/{id}
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $event = ProvenanceEvent::with('product:id,title,sku')->find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Provenance event not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'event' => [
                'id' => $event->id,
                'product_id' => $event->product_id,
                'product' => $event->product,
                'event_type' => $event->event_type,
                'data' => $event->data,
                'ipfs_hash' => $event->ipfs_hash,
                'ipfs_url' => $event->ipfs_url,
                'tx_hash' => $event->tx_hash,
                'polygonscan_url' => $event->polygonscan_url,
                'created_at' => $event->created_at->toIso8601String(),
                'updated_at' => $event->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get all provenance events for a product.
     * 
     * GET /api/products/{productId}/provenance
     * 
     * Returns events ordered chronologically (oldest first - supply chain order).
     * 
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByProduct(int $productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $events = ProvenanceEvent::where('product_id', $productId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'data' => $event->data,
                    'ipfs_hash' => $event->ipfs_hash,
                    'ipfs_url' => $event->ipfs_url,
                    'tx_hash' => $event->tx_hash,
                    'polygonscan_url' => $event->polygonscan_url,
                    'created_at' => $event->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'sku' => $product->sku,
            ],
            'events' => $events,
            'count' => $events->count(),
        ]);
    }

    /**
     * Get provenance statistics.
     * 
     * GET /api/provenance/stats
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $totalEvents = ProvenanceEvent::count();
        $anchoredEvents = ProvenanceEvent::whereNotNull('tx_hash')->count();
        $eventsByType = ProvenanceEvent::select('event_type', DB::raw('count(*) as count'))
            ->groupBy('event_type')
            ->get()
            ->pluck('count', 'event_type');

        return response()->json([
            'success' => true,
            'stats' => [
                'total_events' => $totalEvents,
                'anchored_events' => $anchoredEvents,
                'anchored_percentage' => $totalEvents > 0 ? round(($anchoredEvents / $totalEvents) * 100, 2) : 0,
                'events_by_type' => $eventsByType,
            ],
            'mock_mode' => $this->provenance->isMockMode(),
        ]);
    }

    /**
     * Test IPFS/Pinata connection.
     * 
     * GET /api/provenance/health
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function health()
    {
        try {
            $connectionTest = $this->provenance->testConnection();
            
            return response()->json([
                'status' => $connectionTest['status'],
                'provenance_service' => $connectionTest,
            ], $connectionTest['status'] === 'ok' ? 200 : 503);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Provenance service unavailable',
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}
