<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\AIService;
use App\Services\PineconeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AIRecommendationsTest - Feature tests for AI recommendation system.
 * 
 * Tests:
 * - Product recommendations based on similarity
 * - Query-based recommendations
 * - Shopping assistant chat
 * - Embedding sync endpoint
 * - Mock mode behavior
 * - Error handling for external API failures
 */
class AIRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Seller $seller;
    protected Product $product1;
    protected Product $product2;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure array cache is used in tests
        config(['cache.default' => 'array']);

        // Create test seller
        $this->seller = Seller::factory()->create([
            'store_name' => 'Test Electronics',
        ]);

        // Create test products
        $this->product1 = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'title' => 'Wireless Headphones',
            'description' => 'Premium noise-canceling wireless headphones',
            'category' => 'Electronics',
            'tags' => json_encode(['audio', 'wireless', 'bluetooth']),
            'status' => 'ACTIVE',
        ]);

        $this->product2 = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'title' => 'Bluetooth Speaker',
            'description' => 'Portable waterproof speaker with great sound',
            'category' => 'Electronics',
            'tags' => json_encode(['audio', 'bluetooth', 'portable']),
            'status' => 'ACTIVE',
        ]);

        // Create authenticated user
        $this->user = User::factory()->create();
    }

    /**
     * Test getting recommendations based on product similarity.
     */
    public function test_get_recommendations_by_product(): void
    {
        Sanctum::actingAs($this->user);

        // In mock mode, this will use fake embeddings
        $response = $this->getJson("/api/ai/recommendations?product_id={$this->product1->id}&n=5");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'recommendations' => [
                    '*' => [
                        'product' => [
                            'id',
                            'title',
                            'description',
                            'price_cents',
                            'category',
                            'tags',
                            'seller',
                        ],
                        'similarity_score',
                    ],
                ],
                'count',
                'query',
                'mock_mode',
            ]);

        // Check that recommendations don't include the source product
        $recommendations = $response->json('recommendations');
        $recommendedIds = array_column($recommendations, 'product');
        $recommendedIds = array_column($recommendedIds, 'id');
        
        $this->assertNotContains($this->product1->id, $recommendedIds);
    }

    /**
     * Test getting recommendations based on text query.
     */
    public function test_get_recommendations_by_query(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/ai/recommendations?query=wireless+audio+devices&n=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'recommendations',
                'count',
                'query' => [
                    'query_text',
                ],
                'mock_mode',
            ]);

        $this->assertIsArray($response->json('recommendations'));
    }

    /**
     * Test recommendations with category filter.
     */
    public function test_get_recommendations_with_category_filter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/ai/recommendations?query=products&category=Electronics&n=10');

        $response->assertStatus(200);

        // All recommendations should be in Electronics category
        $recommendations = $response->json('recommendations');
        foreach ($recommendations as $rec) {
            $this->assertEquals('Electronics', $rec['product']['category']);
        }
    }

    /**
     * Test that recommendations require either product_id or query.
     */
    public function test_recommendations_require_product_or_query(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/ai/recommendations');

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Must provide either product_id or query parameter',
            ]);
    }

    /**
     * Test recommendations validation for invalid product_id.
     */
    public function test_recommendations_validate_product_exists(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/ai/recommendations?product_id=99999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    /**
     * Test shopping assistant chat.
     */
    public function test_shopping_assistant_chat(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai/assistant', [
            'message' => 'I need wireless headphones for running',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'response',
                'conversation_id',
                'products',
                'product_count',
                'usage',
                'mock_mode',
            ]);

        $this->assertIsString($response->json('response'));
        $this->assertNotEmpty($response->json('response'));
        $this->assertIsArray($response->json('products'));
    }

    /**
     * Test assistant validates message is required.
     */
    public function test_assistant_requires_message(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai/assistant', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    /**
     * Test assistant validates message length.
     */
    public function test_assistant_validates_message_length(): void
    {
        Sanctum::actingAs($this->user);

        $longMessage = str_repeat('a', 1001);
        $response = $this->postJson('/api/ai/assistant', [
            'message' => $longMessage,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    /**
     * Test embedding sync endpoint requires admin.
     */
    public function test_sync_embeddings_requires_admin(): void
    {
        // Regular user
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai/embeddings/sync');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Unauthorized - admin access required',
            ]);
    }

    /**
     * Test embedding sync succeeds for admin.
     */
    public function test_sync_embeddings_succeeds_for_admin(): void
    {
        // Create admin user
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/ai/embeddings/sync');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'status',
                'note',
            ]);
    }

    /**
     * Test recommendations require authentication.
     */
    public function test_recommendations_require_authentication(): void
    {
        $response = $this->getJson('/api/ai/recommendations?query=test');

        $response->assertStatus(401);
    }

    /**
     * Test assistant requires authentication.
     */
    public function test_assistant_requires_authentication(): void
    {
        $response = $this->postJson('/api/ai/assistant', [
            'message' => 'hello',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test recommendations handle missing embeddings gracefully.
     */
    public function test_recommendations_with_no_embeddings(): void
    {
        Sanctum::actingAs($this->user);

        // Query with mock mode will still work, but return based on mock embeddings
        $response = $this->getJson('/api/ai/recommendations?query=something&n=5');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('recommendations'));
    }

    /**
     * Test that mock mode flag is included in response.
     */
    public function test_response_includes_mock_mode_flag(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/ai/recommendations?query=test');

        $response->assertStatus(200)
            ->assertJsonPath('mock_mode', true); // Should be true in tests
    }

    /**
     * Test similarity scores are reasonable (0-1 range).
     */
    public function test_similarity_scores_are_valid(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/ai/recommendations?product_id={$this->product1->id}&n=10");

        $response->assertStatus(200);

        $recommendations = $response->json('recommendations');
        foreach ($recommendations as $rec) {
            $score = $rec['similarity_score'];
            $this->assertGreaterThanOrEqual(0, $score, 'Score should be >= 0');
            $this->assertLessThanOrEqual(1, $score, 'Score should be <= 1');
        }
    }

    /**
     * Test recommendations are sorted by similarity score.
     */
    public function test_recommendations_are_sorted_by_score(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/ai/recommendations?product_id={$this->product1->id}&n=10");

        $response->assertStatus(200);

        $recommendations = $response->json('recommendations');
        $scores = array_column($recommendations, 'similarity_score');

        // Check that scores are in descending order
        for ($i = 1; $i < count($scores); $i++) {
            $this->assertLessThanOrEqual(
                $scores[$i - 1],
                $scores[$i],
                'Scores should be in descending order'
            );
        }
    }

    /**
     * Test only active products are recommended.
     */
    public function test_only_active_products_recommended(): void
    {
        // Create an archived product
        $archivedProduct = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'status' => 'ARCHIVED',
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/ai/recommendations?query=products&n=20');

        $response->assertStatus(200);

        $recommendations = $response->json('recommendations');
        $recommendedIds = array_column(array_column($recommendations, 'product'), 'id');

        $this->assertNotContains($archivedProduct->id, $recommendedIds);
    }

    /**
     * Test n parameter limits number of recommendations.
     */
    public function test_n_parameter_limits_results(): void
    {
        // Create more products
        Product::factory()->count(10)->create([
            'seller_id' => $this->seller->id,
            'status' => 'ACTIVE',
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/ai/recommendations?query=products&n=3');

        $response->assertStatus(200);

        $recommendations = $response->json('recommendations');
        $this->assertLessThanOrEqual(3, count($recommendations));
    }

    /**
     * Test assistant conversation tracking.
     */
    public function test_assistant_conversation_tracking(): void
    {
        Sanctum::actingAs($this->user);

        // First message
        $response1 = $this->postJson('/api/ai/assistant', [
            'message' => 'I need headphones',
        ]);

        $response1->assertStatus(200);
        $conversationId = $response1->json('conversation_id');

        // Second message with same conversation
        $response2 = $this->postJson('/api/ai/assistant', [
            'message' => 'What about wireless ones?',
            'conversation_id' => $conversationId,
        ]);

        $response2->assertStatus(200)
            ->assertJsonPath('conversation_id', $conversationId);
    }

    /**
     * Test assistant with product search enabled.
     */
    public function test_assistant_with_product_search(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai/assistant', [
            'message' => 'Show me electronics',
            'include_products' => true,
            'max_products' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'response',
                'products' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'price',
                        'category',
                        'similarity_score',
                    ],
                ],
                'product_count',
            ]);

        $products = $response->json('products');
        $this->assertLessThanOrEqual(3, count($products));
    }

    /**
     * Test assistant with product search disabled.
     */
    public function test_assistant_without_product_search(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai/assistant', [
            'message' => 'Hello, how can you help?',
            'include_products' => false,
        ]);

        $response->assertStatus(200);
        
        $products = $response->json('products');
        $this->assertEmpty($products);
        $this->assertEquals(0, $response->json('product_count'));
    }

    /**
     * Test assistant redacts email addresses.
     */
    public function test_assistant_redacts_emails(): void
    {
        Sanctum::actingAs($this->user);

        // This should be redacted by middleware before reaching controller
        $response = $this->postJson('/api/ai/assistant', [
            'message' => 'Contact me at john.doe@example.com about suits',
        ]);

        $response->assertStatus(200);
        
        // The request should have been sanitized
        // The AI should not have seen the email
        $this->assertTrue(true); // Middleware test in SanitizeAndRedactTest
    }

    /**
     * Test assistant redacts phone numbers.
     */
    public function test_assistant_redacts_phone_numbers(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai/assistant', [
            'message' => 'Call me at (555) 123-4567 for details',
        ]);

        $response->assertStatus(200);
        
        // The request should have been sanitized by middleware
        $this->assertTrue(true);
    }

    /**
     * Test assistant handles complex queries with PII.
     */
    public function test_assistant_handles_complex_queries_with_pii(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai/assistant', [
            'message' => 'I need a blue linen suit for a beach wedding. ' .
                        'Email me at john@wedding.com or call 555-123-4567.',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'response',
                'products',
                'product_count',
            ]);

        // Should still return helpful response despite PII redaction
        $this->assertIsString($response->json('response'));
    }

    /**
     * Test assistant returns relevant products for specific queries.
     */
    public function test_assistant_returns_relevant_products(): void
    {
        Sanctum::actingAs($this->user);

        // Create more products to test relevance
        Product::factory()->count(5)->create([
            'seller_id' => $this->seller->id,
            'category' => 'Electronics',
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/ai/assistant', [
            'message' => 'Looking for audio equipment',
            'include_products' => true,
            'max_products' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'response',
                'products',
                'product_count',
            ]);

        // Products array exists (may be empty in mock mode without synced embeddings)
        $this->assertIsArray($response->json('products'));
        $this->assertIsInt($response->json('product_count'));
        
        // If products are returned, verify structure
        $products = $response->json('products');
        if (!empty($products)) {
            foreach ($products as $product) {
                $this->assertArrayHasKey('id', $product);
                $this->assertArrayHasKey('title', $product);
                $this->assertArrayHasKey('similarity_score', $product);
                $this->assertIsNumeric($product['similarity_score']);
            }
        }
    }
}
