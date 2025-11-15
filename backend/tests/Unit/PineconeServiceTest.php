<?php

namespace Tests\Unit;

use App\Services\PineconeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PineconeServiceTest - Unit tests for PineconeService.
 * 
 * Tests:
 * - Vector upsert (real API + mock)
 * - Vector query (real API + mock)
 * - Vector delete (real API + mock)
 * - Cosine similarity calculation
 * - Mock mode behavior
 */
class PineconeServiceTest extends TestCase
{
    protected PineconeService $service;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure array cache is used in tests
        config(['cache.default' => 'array']);
        
        $this->service = new PineconeService();
        
        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test mock upsert stores vectors in cache.
     */
    public function test_mock_upsert_stores_vectors(): void
    {
        $this->service->setMockMode(true);

        $vectors = [
            [
                'id' => 'product_1',
                'values' => array_fill(0, 1536, 0.5),
                'metadata' => ['product_id' => 1, 'title' => 'Test Product'],
            ],
        ];

        $result = $this->service->upsert($vectors);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['upserted_count']);

        // Verify stored in cache
        $cached = Cache::get('pinecone_mock:products:vectors');
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('product_1', $cached);
    }

    /**
     * Test mock upsert with multiple vectors.
     */
    public function test_mock_upsert_multiple_vectors(): void
    {
        $this->service->setMockMode(true);

        $vectors = [];
        for ($i = 1; $i <= 5; $i++) {
            $vectors[] = [
                'id' => "product_{$i}",
                'values' => array_fill(0, 1536, $i * 0.1),
                'metadata' => ['product_id' => $i],
            ];
        }

        $result = $this->service->upsert($vectors);

        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['upserted_count']);
    }

    /**
     * Test mock upsert with custom namespace.
     */
    public function test_mock_upsert_with_custom_namespace(): void
    {
        $this->service->setMockMode(true);
        $this->service->setNamespace('custom-namespace');

        $vectors = [
            [
                'id' => 'test_1',
                'values' => array_fill(0, 1536, 0.5),
                'metadata' => [],
            ],
        ];

        $result = $this->service->upsert($vectors);

        $this->assertTrue($result['success']);

        // Verify stored in correct namespace
        $cached = Cache::get('pinecone_mock:custom-namespace:vectors');
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('test_1', $cached);
    }

    /**
     * Test mock query returns similar vectors.
     */
    public function test_mock_query_returns_similar_vectors(): void
    {
        $this->service->setMockMode(true);

        // Insert test vectors
        $vectors = [
            [
                'id' => 'product_1',
                'values' => array_fill(0, 1536, 0.5),
                'metadata' => ['product_id' => 1, 'title' => 'Product 1'],
            ],
            [
                'id' => 'product_2',
                'values' => array_fill(0, 1536, 0.6),
                'metadata' => ['product_id' => 2, 'title' => 'Product 2'],
            ],
        ];

        $this->service->upsert($vectors);

        // Query for similar vectors
        $queryVector = array_fill(0, 1536, 0.55);
        $result = $this->service->query($queryVector, 2);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['matches']);
        $this->assertLessThanOrEqual(2, count($result['matches']));

        // Check structure
        foreach ($result['matches'] as $match) {
            $this->assertArrayHasKey('id', $match);
            $this->assertArrayHasKey('score', $match);
            $this->assertArrayHasKey('metadata', $match);
        }
    }

    /**
     * Test mock query sorts by similarity score.
     */
    public function test_mock_query_sorts_by_score(): void
    {
        $this->service->setMockMode(true);

        // Insert vectors with varying similarity
        $vectors = [
            ['id' => 'p1', 'values' => array_fill(0, 1536, 0.1), 'metadata' => ['id' => 1]],
            ['id' => 'p2', 'values' => array_fill(0, 1536, 0.5), 'metadata' => ['id' => 2]],
            ['id' => 'p3', 'values' => array_fill(0, 1536, 0.9), 'metadata' => ['id' => 3]],
        ];

        $this->service->upsert($vectors);

        // Query with vector close to 0.9
        $queryVector = array_fill(0, 1536, 0.85);
        $result = $this->service->query($queryVector, 3);

        $scores = array_column($result['matches'], 'score');

        // Scores should be in descending order
        for ($i = 1; $i < count($scores); $i++) {
            $this->assertGreaterThanOrEqual($scores[$i], $scores[$i - 1]);
        }
    }

    /**
     * Test mock query with metadata filter.
     */
    public function test_mock_query_with_metadata_filter(): void
    {
        $this->service->setMockMode(true);

        // Insert vectors with different categories
        $vectors = [
            ['id' => 'p1', 'values' => array_fill(0, 1536, 0.5), 'metadata' => ['category' => 'Electronics']],
            ['id' => 'p2', 'values' => array_fill(0, 1536, 0.5), 'metadata' => ['category' => 'Clothing']],
            ['id' => 'p3', 'values' => array_fill(0, 1536, 0.5), 'metadata' => ['category' => 'Electronics']],
        ];

        $this->service->upsert($vectors);

        // Query with category filter
        $queryVector = array_fill(0, 1536, 0.5);
        $result = $this->service->query($queryVector, 10, ['category' => 'Electronics']);

        $this->assertTrue($result['success']);

        // All results should be Electronics
        foreach ($result['matches'] as $match) {
            $this->assertEquals('Electronics', $match['metadata']['category']);
        }
    }

    /**
     * Test mock query respects topK limit.
     */
    public function test_mock_query_respects_topk(): void
    {
        $this->service->setMockMode(true);

        // Insert 10 vectors
        $vectors = [];
        for ($i = 1; $i <= 10; $i++) {
            $vectors[] = [
                'id' => "p{$i}",
                'values' => array_fill(0, 1536, $i * 0.1),
                'metadata' => ['id' => $i],
            ];
        }

        $this->service->upsert($vectors);

        // Query with topK=3
        $queryVector = array_fill(0, 1536, 0.5);
        $result = $this->service->query($queryVector, 3);

        $this->assertCount(3, $result['matches']);
    }

    /**
     * Test mock delete removes vectors.
     */
    public function test_mock_delete_removes_vectors(): void
    {
        $this->service->setMockMode(true);

        // Insert vectors
        $vectors = [
            ['id' => 'p1', 'values' => array_fill(0, 1536, 0.5), 'metadata' => []],
            ['id' => 'p2', 'values' => array_fill(0, 1536, 0.5), 'metadata' => []],
        ];

        $this->service->upsert($vectors);

        // Delete one vector
        $result = $this->service->delete(['p1']);

        $this->assertTrue($result['success']);

        // Query should not return deleted vector
        $queryResult = $this->service->query(array_fill(0, 1536, 0.5), 10);
        $ids = array_column($queryResult['matches'], 'id');
        
        $this->assertNotContains('p1', $ids);
        $this->assertContains('p2', $ids);
    }

    /**
     * Test cosine similarity calculation.
     */
    public function test_cosine_similarity_calculation(): void
    {
        // Identical vectors should have similarity 1.0
        $vec1 = array_fill(0, 100, 0.5);
        $vec2 = array_fill(0, 100, 0.5);
        
        $similarity = $this->service->cosineSimilarity($vec1, $vec2);
        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);

        // Orthogonal vectors should have similarity 0.0
        $vec3 = array_merge(array_fill(0, 50, 1.0), array_fill(0, 50, 0.0));
        $vec4 = array_merge(array_fill(0, 50, 0.0), array_fill(0, 50, 1.0));
        
        $similarity = $this->service->cosineSimilarity($vec3, $vec4);
        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);

        // Opposite vectors should have similarity -1.0
        $vec5 = array_fill(0, 100, 1.0);
        $vec6 = array_fill(0, 100, -1.0);
        
        $similarity = $this->service->cosineSimilarity($vec5, $vec6);
        $this->assertEqualsWithDelta(-1.0, $similarity, 0.0001);
    }

    /**
     * Test real API upsert with mocked HTTP.
     */
    public function test_real_api_upsert(): void
    {
        // Set fake config
        config(['services.pinecone.api_key' => 'test-key']);
        config(['services.pinecone.host' => 'https://test.pinecone.io']);
        $this->service = new PineconeService();
        $this->service->setMockMode(false);

        Http::fake([
            '*/vectors/upsert' => Http::response([
                'upsertedCount' => 1,
            ], 200),
        ]);

        $vectors = [
            [
                'id' => 'product_1',
                'values' => array_fill(0, 1536, 0.5),
                'metadata' => ['product_id' => 1],
            ],
        ];

        $result = $this->service->upsert($vectors);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['upserted_count']);
    }

    /**
     * Test real API query with mocked HTTP.
     */
    public function test_real_api_query(): void
    {
        // Set fake config
        config(['services.pinecone.api_key' => 'test-key']);
        config(['services.pinecone.host' => 'https://test.pinecone.io']);
        $this->service = new PineconeService();
        $this->service->setMockMode(false);

        Http::fake([
            '*/query' => Http::response([
                'matches' => [
                    [
                        'id' => 'product_1',
                        'score' => 0.95,
                        'metadata' => ['product_id' => 1],
                    ],
                ],
            ], 200),
        ]);

        $queryVector = array_fill(0, 1536, 0.5);
        $result = $this->service->query($queryVector, 10);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['matches']);
        $this->assertEquals('product_1', $result['matches'][0]['id']);
    }

    /**
     * Test real API delete with mocked HTTP.
     */
    public function test_real_api_delete(): void
    {
        // Set fake config
        config(['services.pinecone.api_key' => 'test-key']);
        config(['services.pinecone.host' => 'https://test.pinecone.io']);
        $this->service = new PineconeService();
        $this->service->setMockMode(false);

        Http::fake([
            '*/vectors/delete' => Http::response([], 200),
        ]);

        $result = $this->service->delete(['product_1', 'product_2']);

        $this->assertTrue($result['success']);
    }

    /**
     * Test API error handling for upsert.
     */
    public function test_upsert_api_error_handling(): void
    {
        // Set fake config
        config(['services.pinecone.api_key' => 'test-key']);
        config(['services.pinecone.host' => 'https://test.pinecone.io']);
        $this->service = new PineconeService();
        $this->service->setMockMode(false);

        Http::fake([
            '*/vectors/upsert' => Http::response([
                'error' => 'Unauthorized',
            ], 401),
        ]);

        $vectors = [
            ['id' => 'p1', 'values' => array_fill(0, 1536, 0.5), 'metadata' => []],
        ];

        $result = $this->service->upsert($vectors);

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    /**
     * Test API error handling for query.
     */
    public function test_query_api_error_handling(): void
    {
        // Set fake config
        config(['services.pinecone.api_key' => 'test-key']);
        config(['services.pinecone.host' => 'https://test.pinecone.io']);
        $this->service = new PineconeService();
        $this->service->setMockMode(false);

        Http::fake([
            '*/query' => Http::response([
                'error' => 'Index not found',
            ], 404),
        ]);

        $result = $this->service->query(array_fill(0, 1536, 0.5), 10);

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    /**
     * Test isMockMode returns correct value.
     */
    public function test_is_mock_mode(): void
    {
        $this->service->setMockMode(true);
        $this->assertTrue($this->service->isMockMode());

        $this->service->setMockMode(false);
        $this->assertFalse($this->service->isMockMode());
    }

    /**
     * Test query with empty database returns empty results.
     */
    public function test_query_with_empty_database(): void
    {
        $this->service->setMockMode(true);

        $queryVector = array_fill(0, 1536, 0.5);
        $result = $this->service->query($queryVector, 10);

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['matches']);
    }

    /**
     * Test upsert updates existing vectors.
     */
    public function test_upsert_updates_existing_vectors(): void
    {
        $this->service->setMockMode(true);

        // Insert initial vector
        $vectors = [
            ['id' => 'p1', 'values' => array_fill(0, 1536, 0.3), 'metadata' => ['version' => 1]],
        ];
        $this->service->upsert($vectors);

        // Update the same vector
        $updatedVectors = [
            ['id' => 'p1', 'values' => array_fill(0, 1536, 0.7), 'metadata' => ['version' => 2]],
        ];
        $this->service->upsert($updatedVectors);

        // Query should return updated version
        $result = $this->service->query(array_fill(0, 1536, 0.7), 10);
        
        $this->assertCount(1, $result['matches']);
        $this->assertEquals(2, $result['matches'][0]['metadata']['version']);
    }

    /**
     * Test delete with non-existent IDs doesn't error.
     */
    public function test_delete_non_existent_ids(): void
    {
        $this->service->setMockMode(true);

        $result = $this->service->delete(['non_existent_1', 'non_existent_2']);

        $this->assertTrue($result['success']);
    }

    /**
     * Test batch operations with large vector count.
     */
    public function test_batch_operations_with_many_vectors(): void
    {
        $this->service->setMockMode(true);

        // Insert 100 vectors
        $vectors = [];
        for ($i = 1; $i <= 100; $i++) {
            $vectors[] = [
                'id' => "p{$i}",
                'values' => array_fill(0, 1536, $i * 0.01),
                'metadata' => ['id' => $i],
            ];
        }

        $result = $this->service->upsert($vectors);

        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['upserted_count']);

        // Query should handle large result set
        $queryResult = $this->service->query(array_fill(0, 1536, 0.5), 50);
        
        $this->assertLessThanOrEqual(50, count($queryResult['matches']));
    }
}
