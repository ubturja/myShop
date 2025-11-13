<?php

namespace Tests\Unit;

use App\Services\AIService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AIServiceTest - Unit tests for AIService.
 * 
 * Tests:
 * - Embedding generation (real API + mock)
 * - Chat completions (real API + mock)
 * - Error handling
 * - Mock mode behavior
 */
class AIServiceTest extends TestCase
{
    protected AIService $service;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AIService();
    }

    /**
     * Test mock embedding generation returns valid format.
     */
    public function test_mock_generate_embedding_returns_valid_format(): void
    {
        $this->service->setMockMode(true);

        $result = $this->service->generateEmbedding('test product description');

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['embedding']);
        $this->assertEquals(1536, $result['dimensions']);
        $this->assertCount(1536, $result['embedding']);
        $this->assertNull($result['error']);
    }

    /**
     * Test mock embeddings are deterministic.
     */
    public function test_mock_embeddings_are_deterministic(): void
    {
        $this->service->setMockMode(true);

        $text = 'wireless headphones bluetooth';
        
        $result1 = $this->service->generateEmbedding($text);
        $result2 = $this->service->generateEmbedding($text);

        // Same text should produce same embedding
        $this->assertEquals($result1['embedding'], $result2['embedding']);
    }

    /**
     * Test mock embeddings differ for different text.
     */
    public function test_mock_embeddings_differ_for_different_text(): void
    {
        $this->service->setMockMode(true);

        $result1 = $this->service->generateEmbedding('wireless headphones');
        $result2 = $this->service->generateEmbedding('bluetooth speaker');

        // Different text should produce different embeddings
        $this->assertNotEquals($result1['embedding'], $result2['embedding']);
    }

    /**
     * Test mock embeddings are normalized (unit vectors).
     */
    public function test_mock_embeddings_are_normalized(): void
    {
        $this->service->setMockMode(true);

        $result = $this->service->generateEmbedding('test text');

        $embedding = $result['embedding'];
        
        // Calculate magnitude
        $sumSquares = array_sum(array_map(fn($x) => $x * $x, $embedding));
        $magnitude = sqrt($sumSquares);

        // Should be very close to 1.0 (allowing for floating point precision)
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);
    }

    /**
     * Test real API embedding generation with mocked HTTP.
     */
    public function test_real_api_embedding_generation(): void
    {
        // Set a fake API key first
        config(['services.openai.api_key' => 'sk-test-fake-key']);
        $this->service = new AIService();
        $this->service->setMockMode(false);

        // Mock the HTTP response
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    [
                        'embedding' => array_fill(0, 1536, 0.5),
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'total_tokens' => 10,
                ],
            ], 200),
        ]);

        $result = $this->service->generateEmbedding('test text');

        $this->assertTrue($result['success']);
        $this->assertCount(1536, $result['embedding']);
        $this->assertEquals(1536, $result['dimensions']);
    }

    /**
     * Test API error handling for embeddings.
     */
    public function test_embedding_api_error_handling(): void
    {
        // Set a fake API key first
        config(['services.openai.api_key' => 'sk-test-fake-key']);
        $this->service = new AIService();
        $this->service->setMockMode(false);

        // Mock a failed HTTP response
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'error' => [
                    'message' => 'Invalid API key',
                ],
            ], 401),
        ]);

        $result = $this->service->generateEmbedding('test text');

        $this->assertFalse($result['success']);
        $this->assertNull($result['embedding']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Invalid API key', $result['error']);
    }

    /**
     * Test mock chat completion returns valid format.
     */
    public function test_mock_chat_completion_returns_valid_format(): void
    {
        $this->service->setMockMode(true);

        $messages = [
            ['role' => 'user', 'content' => 'Hello, I need help finding products'],
        ];

        $result = $this->service->chatCompletion($messages);

        $this->assertTrue($result['success']);
        $this->assertIsString($result['content']);
        $this->assertNotEmpty($result['content']);
        $this->assertIsArray($result['usage']);
        $this->assertArrayHasKey('prompt_tokens', $result['usage']);
        $this->assertArrayHasKey('completion_tokens', $result['usage']);
    }

    /**
     * Test mock chat includes user message context.
     */
    public function test_mock_chat_includes_user_context(): void
    {
        $this->service->setMockMode(true);

        $messages = [
            ['role' => 'user', 'content' => 'wireless headphones'],
        ];

        $result = $this->service->chatCompletion($messages);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('wireless headphones', $result['content']);
    }

    /**
     * Test real API chat completion with mocked HTTP.
     */
    public function test_real_api_chat_completion(): void
    {
        // Set a fake API key first
        config(['services.openai.api_key' => 'sk-test-fake-key']);
        $this->service = new AIService();
        $this->service->setMockMode(false);

        // Mock the HTTP response
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'I can help you find great wireless headphones.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 20,
                    'completion_tokens' => 15,
                    'total_tokens' => 35,
                ],
            ], 200),
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'I need wireless headphones'],
        ];

        $result = $this->service->chatCompletion($messages);

        $this->assertTrue($result['success']);
        $this->assertEquals('I can help you find great wireless headphones.', $result['content']);
        $this->assertEquals(35, $result['usage']['total_tokens']);
    }

    /**
     * Test API error handling for chat.
     */
    public function test_chat_api_error_handling(): void
    {
        // Set a fake API key first
        config(['services.openai.api_key' => 'sk-test-fake-key']);
        $this->service = new AIService();
        $this->service->setMockMode(false);

        // Mock a failed HTTP response
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Rate limit exceeded',
                ],
            ], 429),
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'test'],
        ];

        $result = $this->service->chatCompletion($messages);

        $this->assertFalse($result['success']);
        $this->assertNull($result['content']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Rate limit exceeded', $result['error']);
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
     * Test empty text embedding.
     */
    public function test_empty_text_embedding(): void
    {
        $this->service->setMockMode(true);

        $result = $this->service->generateEmbedding('');

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['embedding']);
        $this->assertCount(1536, $result['embedding']);
    }

    /**
     * Test very long text embedding.
     */
    public function test_long_text_embedding(): void
    {
        $this->service->setMockMode(true);

        $longText = str_repeat('product description ', 100);
        $result = $this->service->generateEmbedding($longText);

        $this->assertTrue($result['success']);
        $this->assertCount(1536, $result['embedding']);
    }

    /**
     * Test chat with empty messages array.
     */
    public function test_chat_with_empty_messages(): void
    {
        $this->service->setMockMode(true);

        $result = $this->service->chatCompletion([]);

        // Should still return a response (mock mode)
        $this->assertTrue($result['success']);
        $this->assertIsString($result['content']);
    }

    /**
     * Test chat with multiple messages.
     */
    public function test_chat_with_conversation_history(): void
    {
        $this->service->setMockMode(true);

        $messages = [
            ['role' => 'user', 'content' => 'Show me headphones'],
            ['role' => 'assistant', 'content' => 'Here are some options'],
            ['role' => 'user', 'content' => 'What about wireless ones?'],
        ];

        $result = $this->service->chatCompletion($messages);

        $this->assertTrue($result['success']);
        $this->assertIsString($result['content']);
    }
}
