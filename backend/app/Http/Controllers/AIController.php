<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\AIService;
use App\Services\PineconeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * AIController handles AI-powered features.
 * 
 * Endpoints:
 * - POST /api/ai/embeddings/sync - Trigger embedding sync (admin only)
 * - GET /api/ai/recommendations - Get product recommendations
 * - POST /api/ai/assistant - Chat with shopping assistant
 * 
 * Uses OpenAI for embeddings and Pinecone for vector search.
 * Supports mock mode for development without API keys.
 */
class AIController extends Controller
{
    /**
     * AIService instance.
     */
    protected AIService $aiService;

    /**
     * PineconeService instance.
     */
    protected PineconeService $pineconeService;

    /**
     * Constructor - inject dependencies.
     */
    public function __construct(AIService $aiService, PineconeService $pineconeService)
    {
        $this->aiService = $aiService;
        $this->pineconeService = $pineconeService;
    }

    /**
     * Trigger product embedding sync.
     * 
     * Asynchronously syncs all product embeddings to vector database.
     * Admin-only endpoint for maintenance.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function syncEmbeddings(Request $request): JsonResponse
    {
        // Check if user is admin
        if (!$request->user() || $request->user()->role !== 'ADMIN') {
            return response()->json([
                'error' => 'Unauthorized - admin access required',
            ], 403);
        }

        // In production, this should dispatch a job
        // For now, return acknowledgment
        // Artisan::call('products:sync-embeddings');

        return response()->json([
            'message' => 'Embedding sync initiated',
            'status' => 'processing',
            'note' => 'Run: php artisan products:sync-embeddings',
        ]);
    }

    /**
     * Get AI-powered product recommendations.
     * 
     * Returns similar products based on:
     * - Semantic similarity (if productId provided)
     * - User query text (if query provided)
     * - Browsing history and preferences (future enhancement)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function recommendations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|integer|exists:products,id',
            'query' => 'nullable|string|max:500',
            'n' => 'nullable|integer|min:1|max:50',
            'category' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $productId = $request->input('product_id');
        $query = $request->input('query');
        $n = $request->input('n', 10);
        $category = $request->input('category');

        // Must provide either product_id or query
        if (!$productId && !$query) {
            return response()->json([
                'error' => 'Must provide either product_id or query parameter',
            ], 422);
        }

        try {
            // Get query embedding
            if ($productId) {
                // Find similar products to given product
                $product = Product::findOrFail($productId);
                $embeddingText = $product->embedding_text;
            } else {
                // Find products matching text query
                $embeddingText = $query;
            }

            // Generate embedding for query
            $embeddingResult = $this->aiService->generateEmbedding($embeddingText);

            if (!$embeddingResult['success']) {
                return response()->json([
                    'error' => 'Failed to generate embedding',
                    'message' => $embeddingResult['error'],
                ], 500);
            }

            // Build filter for Pinecone query
            $filter = [];
            if ($category) {
                $filter['category'] = $category;
            }

            // Query Pinecone for similar vectors
            $queryResult = $this->pineconeService->query(
                $embeddingResult['embedding'],
                $n + ($productId ? 1 : 0), // Get extra if filtering out source product
                $filter
            );

            if (!$queryResult['success']) {
                return response()->json([
                    'error' => 'Failed to query recommendations',
                    'message' => $queryResult['error'],
                ], 500);
            }

            $matches = $queryResult['matches'] ?? [];

            // Extract product IDs and scores
            $recommendations = [];
            foreach ($matches as $match) {
                $matchProductId = $match['metadata']['product_id'] ?? null;
                
                // Skip the source product itself
                if ($productId && $matchProductId == $productId) {
                    continue;
                }

                if ($matchProductId) {
                    $recommendations[] = [
                        'product_id' => $matchProductId,
                        'score' => $match['score'] ?? 0,
                    ];
                }

                // Stop when we have enough recommendations
                if (count($recommendations) >= $n) {
                    break;
                }
            }

            // Fetch full product details
            $productIds = array_column($recommendations, 'product_id');
            $products = Product::with('seller')
                ->whereIn('id', $productIds)
                ->where('status', 'ACTIVE')
                ->get()
                ->keyBy('id');

            // Build response with products and scores
            $results = [];
            foreach ($recommendations as $rec) {
                $product = $products->get($rec['product_id']);
                
                if ($product) {
                    $results[] = [
                        'product' => [
                            'id' => $product->id,
                            'title' => $product->title,
                            'description' => $product->description,
                            'price_cents' => $product->price_cents,
                            'price' => $product->price,
                            'formatted_price' => $product->formatted_price,
                            'currency' => $product->currency,
                            'image_url' => $product->image_url,
                            'category' => $product->category,
                            'tags' => $product->tags,
                            'is_quick_item' => $product->is_quick_item,
                            'seller' => [
                                'id' => $product->seller->id,
                                'store_name' => $product->seller->store_name,
                            ],
                        ],
                        'similarity_score' => round($rec['score'], 4),
                    ];
                }
            }

            return response()->json([
                'recommendations' => $results,
                'count' => count($results),
                'query' => [
                    'product_id' => $productId,
                    'query_text' => $query,
                    'category_filter' => $category,
                ],
                'mock_mode' => $this->aiService->isMockMode() || $this->pineconeService->isMockMode(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate recommendations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Chat with AI shopping assistant.
     * 
     * Provides conversational product recommendations and shopping advice.
     * Uses vector search to find relevant products based on user query.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function assistant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|string',
            'include_products' => 'nullable|boolean',
            'max_products' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $userMessage = $request->input('message');
        $includeProducts = $request->input('include_products', true);
        $maxProducts = $request->input('max_products', 5);

        try {
            // Generate embedding for user message to find relevant products
            $relevantProducts = [];
            if ($includeProducts) {
                $embeddingResult = $this->aiService->generateEmbedding($userMessage);
                
                if ($embeddingResult['success']) {
                    // Query vector database for relevant products
                    $vectorResult = $this->pineconeService->query(
                        $embeddingResult['embedding'],
                        $maxProducts
                    );

                    if ($vectorResult['success'] && !empty($vectorResult['matches'])) {
                        // Fetch full product details
                        $productIds = array_column(
                            array_column($vectorResult['matches'], 'metadata'),
                            'product_id'
                        );

                        $products = Product::with('seller')
                            ->whereIn('id', $productIds)
                            ->where('status', 'ACTIVE')
                            ->get()
                            ->keyBy('id');

                        // Build product list with scores
                        foreach ($vectorResult['matches'] as $match) {
                            $productId = $match['metadata']['product_id'] ?? null;
                            if ($productId && isset($products[$productId])) {
                                $product = $products[$productId];
                                $relevantProducts[] = [
                                    'id' => $product->id,
                                    'title' => $product->title,
                                    'description' => $product->description,
                                    'price' => $product->formatted_price,
                                    'category' => $product->category,
                                    'similarity_score' => round($match['score'], 3),
                                ];
                            }
                        }
                    }
                }
            }

            // Build conversation context with product information
            $systemPrompt = 'You are a helpful shopping assistant for myShop, an e-commerce platform. ' .
                           'Help users find products, answer questions, and provide recommendations. ' .
                           'Be concise, friendly, and focus on helping users make purchase decisions.';

            if (!empty($relevantProducts)) {
                $systemPrompt .= "\n\nRelevant products found:\n";
                foreach ($relevantProducts as $index => $product) {
                    $systemPrompt .= sprintf(
                        "%d. %s (%s) - %s\n",
                        $index + 1,
                        $product['title'],
                        $product['price'],
                        $product['category']
                    );
                }
                $systemPrompt .= "\nReference these products naturally in your response when relevant.";
            }

            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userMessage,
                ],
            ];

            // Get AI response
            $chatResult = $this->aiService->chatCompletion($messages);

            if (!$chatResult['success']) {
                return response()->json([
                    'error' => 'Failed to generate response',
                    'message' => $chatResult['error'],
                ], 500);
            }

            return response()->json([
                'response' => $chatResult['content'],
                'conversation_id' => $request->input('conversation_id', uniqid('conv_')),
                'products' => $relevantProducts,
                'product_count' => count($relevantProducts),
                'usage' => $chatResult['usage'],
                'mock_mode' => $this->aiService->isMockMode() || $this->pineconeService->isMockMode(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process message',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
