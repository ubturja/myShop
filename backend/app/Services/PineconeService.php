<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * PineconeService handles vector database operations.
 * 
 * Features:
 * - Upsert product embeddings with metadata
 * - Query similar products by vector similarity
 * - Delete embeddings
 * - Namespace management
 * 
 * Mock mode available for development/testing without Pinecone API.
 */
class PineconeService
{
    /**
     * Pinecone API key from config.
     */
    protected ?string $apiKey;

    /**
     * Pinecone environment/host.
     */
    protected ?string $host;

    /**
     * Pinecone index name.
     */
    protected string $indexName;

    /**
     * Whether to use mock mode (in-memory cache instead of Pinecone).
     */
    protected bool $mockMode;

    /**
     * Namespace for organizing vectors.
     */
    protected string $namespace = 'products';

    /**
     * Constructor - initialize from config.
     */
    public function __construct()
    {
        $this->apiKey = config('services.pinecone.api_key');
        $this->host = config('services.pinecone.host');
        $this->indexName = config('services.pinecone.index', 'myshop-products');
        $this->mockMode = config('services.pinecone.mock_mode', true);

        if (!$this->apiKey && !$this->mockMode) {
            Log::warning('Pinecone API key not configured and mock mode is disabled');
        }
    }

    /**
     * Upsert vectors into Pinecone index.
     * 
     * Inserts or updates product embeddings with metadata for filtering.
     * 
     * @param array $vectors Array of vector objects with structure:
     *   [
     *     'id' => 'product_123',
     *     'values' => [0.1, 0.2, ...], // 1536-dimensional embedding
     *     'metadata' => ['title' => 'Product Name', 'price' => 1999, ...]
     *   ]
     * @return array{success: bool, upserted_count: int|null, error: string|null}
     */
    public function upsert(array $vectors): array
    {
        // Mock mode - store in cache
        if ($this->mockMode) {
            return $this->mockUpsert($vectors);
        }

        if (!$this->apiKey || !$this->host) {
            return [
                'success' => false,
                'upserted_count' => null,
                'error' => 'Pinecone API credentials not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post("{$this->host}/vectors/upsert", [
                'vectors' => $vectors,
                'namespace' => $this->namespace,
            ]);

            if ($response->failed()) {
                $error = $response->json('message') ?? 'Unknown error';
                Log::error('Pinecone upsert failed', [
                    'status' => $response->status(),
                    'error' => $error,
                    'vector_count' => count($vectors),
                ]);

                return [
                    'success' => false,
                    'upserted_count' => null,
                    'error' => $error,
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'upserted_count' => $data['upsertedCount'] ?? count($vectors),
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('Pinecone upsert exception', [
                'message' => $e->getMessage(),
                'vector_count' => count($vectors),
            ]);

            return [
                'success' => false,
                'upserted_count' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Query similar vectors from Pinecone.
     * 
     * Finds the most similar product embeddings based on vector similarity (cosine).
     * 
     * @param array $queryVector 1536-dimensional query embedding
     * @param int $topK Number of results to return (default 10)
     * @param array $filter Optional metadata filter (e.g., ['category' => 'Electronics'])
     * @return array{success: bool, matches: array|null, error: string|null}
     */
    public function query(array $queryVector, int $topK = 10, array $filter = []): array
    {
        // Mock mode - return cached vectors
        if ($this->mockMode) {
            return $this->mockQuery($queryVector, $topK, $filter);
        }

        if (!$this->apiKey || !$this->host) {
            return [
                'success' => false,
                'matches' => null,
                'error' => 'Pinecone API credentials not configured',
            ];
        }

        try {
            $payload = [
                'vector' => $queryVector,
                'topK' => $topK,
                'namespace' => $this->namespace,
                'includeMetadata' => true,
            ];

            if (!empty($filter)) {
                $payload['filter'] = $filter;
            }

            $response = Http::withHeaders([
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->host}/query", $payload);

            if ($response->failed()) {
                $error = $response->json('message') ?? 'Unknown error';
                Log::error('Pinecone query failed', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'matches' => null,
                    'error' => $error,
                ];
            }

            $data = $response->json();
            $matches = $data['matches'] ?? [];

            return [
                'success' => true,
                'matches' => $matches,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('Pinecone query exception', [
                'message' => $e->getMessage(),
                'topK' => $topK,
            ]);

            return [
                'success' => false,
                'matches' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete vectors by IDs.
     * 
     * @param array $ids Array of vector IDs to delete
     * @return array{success: bool, error: string|null}
     */
    public function delete(array $ids): array
    {
        // Mock mode - remove from cache
        if ($this->mockMode) {
            return $this->mockDelete($ids);
        }

        if (!$this->apiKey || !$this->host) {
            return [
                'success' => false,
                'error' => 'Pinecone API credentials not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->host}/vectors/delete", [
                'ids' => $ids,
                'namespace' => $this->namespace,
            ]);

            if ($response->failed()) {
                $error = $response->json('message') ?? 'Unknown error';
                Log::error('Pinecone delete failed', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'error' => $error,
                ];
            }

            return [
                'success' => true,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('Pinecone delete exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mock upsert - store vectors in cache.
     * 
     * @param array $vectors
     * @return array
     */
    protected function mockUpsert(array $vectors): array
    {
        $cacheKey = "pinecone_mock:{$this->namespace}:vectors";
        
        // Get existing vectors
        $existingVectors = Cache::get($cacheKey, []);

        // Upsert (update or insert)
        foreach ($vectors as $vector) {
            $existingVectors[$vector['id']] = $vector;
        }

        // Store back in cache (24 hours)
        Cache::put($cacheKey, $existingVectors, now()->addHours(24));

        return [
            'success' => true,
            'upserted_count' => count($vectors),
            'error' => null,
            'mock' => true,
        ];
    }

    /**
     * Mock query - calculate cosine similarity with cached vectors.
     * 
     * @param array $queryVector
     * @param int $topK
     * @param array $filter
     * @return array
     */
    protected function mockQuery(array $queryVector, int $topK = 10, array $filter = []): array
    {
        $cacheKey = "pinecone_mock:{$this->namespace}:vectors";
        $vectors = Cache::get($cacheKey, []);

        if (empty($vectors)) {
            return [
                'success' => true,
                'matches' => [],
                'error' => null,
                'mock' => true,
            ];
        }

        // Calculate cosine similarity for each vector
        $similarities = [];
        
        foreach ($vectors as $id => $vector) {
            // Apply metadata filter if specified
            if (!empty($filter)) {
                $passesFilter = true;
                foreach ($filter as $key => $value) {
                    if (!isset($vector['metadata'][$key]) || $vector['metadata'][$key] !== $value) {
                        $passesFilter = false;
                        break;
                    }
                }
                if (!$passesFilter) {
                    continue;
                }
            }

            $similarity = $this->cosineSimilarity($queryVector, $vector['values']);
            
            $similarities[] = [
                'id' => $id,
                'score' => $similarity,
                'metadata' => $vector['metadata'] ?? [],
            ];
        }

        // Sort by similarity (highest first)
        usort($similarities, fn($a, $b) => $b['score'] <=> $a['score']);

        // Take top K
        $matches = array_slice($similarities, 0, $topK);

        return [
            'success' => true,
            'matches' => $matches,
            'error' => null,
            'mock' => true,
        ];
    }

    /**
     * Mock delete - remove from cache.
     * 
     * @param array $ids
     * @return array
     */
    protected function mockDelete(array $ids): array
    {
        $cacheKey = "pinecone_mock:{$this->namespace}:vectors";
        $vectors = Cache::get($cacheKey, []);

        foreach ($ids as $id) {
            unset($vectors[$id]);
        }

        Cache::put($cacheKey, $vectors, now()->addHours(24));

        return [
            'success' => true,
            'error' => null,
            'mock' => true,
        ];
    }

    /**
     * Calculate cosine similarity between two vectors.
     * 
     * @param array $a First vector
     * @param array $b Second vector
     * @return float Similarity score (0 to 1)
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Check if service is in mock mode.
     * 
     * @return bool
     */
    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    /**
     * Set mock mode (useful for testing).
     * 
     * @param bool $enabled
     * @return void
     */
    public function setMockMode(bool $enabled): void
    {
        $this->mockMode = $enabled;
    }

    /**
     * Set namespace for vector operations.
     * 
     * @param string $namespace
     * @return void
     */
    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * Clear mock cache (useful for testing).
     * 
     * @return void
     */
    public function clearMockCache(): void
    {
        if ($this->mockMode) {
            $cacheKey = "pinecone_mock:{$this->namespace}:vectors";
            Cache::forget($cacheKey);
        }
    }
}
