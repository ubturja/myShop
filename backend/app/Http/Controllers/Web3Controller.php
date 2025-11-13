<?php

namespace App\Http\Controllers;

use App\Models\NFTToken;
use App\Services\Web3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Web3Controller - Handles blockchain NFT operations.
 * 
 * Endpoints:
 * - POST /web3/mint-loyalty - Mint loyalty NFT
 * - GET /web3/tokens/{id} - Get NFT details
 * - GET /web3/health - Check web3-service health
 */
class Web3Controller extends Controller
{
    protected Web3Client $web3;

    public function __construct(Web3Client $web3Client)
    {
        $this->web3 = $web3Client;
    }

    /**
     * Get web3-service health status.
     * 
     * GET /api/web3/health
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function health()
    {
        try {
            $health = $this->web3->health();
            
            return response()->json([
                'status' => 'healthy',
                'web3_service' => $health,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Web3 service unavailable',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Mint a loyalty NFT for a user.
     * 
     * POST /api/web3/mint-loyalty
     * 
     * Request:
     * {
     *   "user_id": 1,              // Required: User ID to mint for
     *   "metadata_uri": "ipfs://...", // Required: IPFS or HTTP URL
     *   "contract_address": "0x..."   // Optional: Contract address override
     * }
     * 
     * Response:
     * {
     *   "success": true,
     *   "nft": {
     *     "id": 1,
     *     "user_id": 1,
     *     "token_id": "12345",
     *     "contract_address": "0x...",
     *     "metadata_uri": "ipfs://...",
     *     "tx_hash": "0x...",
     *     "minted_at": "2024-01-01T00:00:00Z",
     *     "polygonscan_url": "https://mumbai.polygonscan.com/tx/0x..."
     *   },
     *   "mock_mode": true
     * }
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function mintLoyalty(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'metadata_uri' => 'required|string|max:500',
            'contract_address' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->input('user_id');
        $metadataUri = $request->input('metadata_uri');
        $contractAddress = $request->input('contract_address');

        try {
            DB::beginTransaction();

            // Call web3-service to mint NFT
            $mintResponse = $this->web3->mintLoyaltyNFT($userId, $metadataUri, $contractAddress);

            // Use provided contract address or default from config
            $finalContractAddress = $contractAddress ?? config('services.web3.contract_address', '0x0000000000000000000000000000000000000000');

            // Persist NFT token to database
            $nft = NFTToken::create([
                'user_id' => $userId,
                'token_id' => $mintResponse['tokenId'],
                'contract_address' => $finalContractAddress,
                'metadata_uri' => $metadataUri,
                'tx_hash' => $mintResponse['txHash'],
                'minted_at' => now(),
            ]);

            DB::commit();

            Log::info('NFT minted and persisted', [
                'nft_id' => $nft->id,
                'user_id' => $userId,
                'token_id' => $nft->token_id,
            ]);

            return response()->json([
                'success' => true,
                'nft' => [
                    'id' => $nft->id,
                    'user_id' => $nft->user_id,
                    'token_id' => $nft->token_id,
                    'contract_address' => $nft->contract_address,
                    'metadata_uri' => $nft->metadata_uri,
                    'tx_hash' => $nft->tx_hash,
                    'minted_at' => $nft->minted_at->toIso8601String(),
                    'polygonscan_url' => $nft->polygonscan_url,
                ],
                'mock_mode' => $this->web3->isMockMode(),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Mint loyalty NFT failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'metadata_uri' => $metadataUri,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mint NFT',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get NFT token details by ID.
     * 
     * GET /api/web3/tokens/{id}
     * 
     * @param int $id NFT token database ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $nft = NFTToken::with('user:id,name,email')->find($id);

        if (!$nft) {
            return response()->json([
                'success' => false,
                'message' => 'NFT not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'nft' => [
                'id' => $nft->id,
                'user_id' => $nft->user_id,
                'user' => $nft->user,
                'token_id' => $nft->token_id,
                'contract_address' => $nft->contract_address,
                'metadata_uri' => $nft->metadata_uri,
                'tx_hash' => $nft->tx_hash,
                'minted_at' => $nft->minted_at->toIso8601String(),
                'polygonscan_url' => $nft->polygonscan_url,
            ],
        ]);
    }

    /**
     * Get all NFTs for authenticated user.
     * 
     * GET /api/web3/my-tokens
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myTokens(Request $request)
    {
        $user = $request->user();
        
        $nfts = NFTToken::where('user_id', $user->id)
            ->orderBy('minted_at', 'desc')
            ->get()
            ->map(function ($nft) {
                return [
                    'id' => $nft->id,
                    'token_id' => $nft->token_id,
                    'contract_address' => $nft->contract_address,
                    'metadata_uri' => $nft->metadata_uri,
                    'tx_hash' => $nft->tx_hash,
                    'minted_at' => $nft->minted_at->toIso8601String(),
                    'polygonscan_url' => $nft->polygonscan_url,
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $nfts->count(),
            'tokens' => $nfts,
        ]);
    }
}
