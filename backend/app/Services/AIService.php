<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AIService handles AI-powered features using OpenAI.
 * 
 * Features:
 * - Generate text embeddings for semantic search
 * - Chat completions for shopping assistant
 * - Product recommendations based on embeddings
 * 
 * Mock mode available for development/testing without API keys.
 */
class AIService
{
    /**
     * OpenAI API key from config.
     */
    protected ?string $apiKey;

    /**
     * OpenAI API base URL.
     */
    protected string $baseUrl = 'https://api.openai.com/v1';

    /**
     * Whether to use mock mode (no real API calls).
     */
    protected bool $mockMode;

    /**
     * Embedding model to use.
     */
    protected string $embeddingModel = 'text-embedding-3-small';

    /**
     * Chat completion model to use.
     */
    protected string $chatModel = 'gpt-4o-mini';

    /**
     * Constructor - initialize from config.
     */
    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->mockMode = config('services.openai.mock_mode', true);

        if (!$this->apiKey && !$this->mockMode) {
            Log::warning('OpenAI API key not configured and mock mode is disabled');
        }
    }

    /**
     * Generate embedding vector for given text.
     * 
     * Converts text into a dense vector representation (1536 dimensions for text-embedding-3-small).
     * Used for semantic similarity search in vector database.
     * 
     * @param string $text Text to embed (max ~8000 tokens)
     * @return array{success: bool, embedding: array<float>|null, dimensions: int|null, error: string|null}
     */
    public function generateEmbedding(string $text): array
    {
        // Mock mode - return deterministic fake embedding
        if ($this->mockMode) {
            return $this->mockGenerateEmbedding($text);
        }

        if (!$this->apiKey) {
            return [
                'success' => false,
                'embedding' => null,
                'dimensions' => null,
                'error' => 'OpenAI API key not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->baseUrl}/embeddings", [
                'model' => $this->embeddingModel,
                'input' => $text,
            ]);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';
                Log::error('OpenAI embedding generation failed', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'embedding' => null,
                    'dimensions' => null,
                    'error' => $error,
                ];
            }

            $data = $response->json();
            $embedding = $data['data'][0]['embedding'] ?? null;

            if (!$embedding) {
                return [
                    'success' => false,
                    'embedding' => null,
                    'dimensions' => null,
                    'error' => 'No embedding returned from API',
                ];
            }

            return [
                'success' => true,
                'embedding' => $embedding,
                'dimensions' => count($embedding),
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI embedding generation exception', [
                'message' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);

            return [
                'success' => false,
                'embedding' => null,
                'dimensions' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate chat completion for shopping assistant.
     * 
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param array $options Optional parameters (temperature, max_tokens, etc.)
     * @return array{success: bool, content: string|null, usage: array|null, error: string|null}
     */
    public function chatCompletion(array $messages, array $options = []): array
    {
        // Mock mode - return simple response
        if ($this->mockMode) {
            return $this->mockChatCompletion($messages);
        }

        if (!$this->apiKey) {
            return [
                'success' => false,
                'content' => null,
                'usage' => null,
                'error' => 'OpenAI API key not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post("{$this->baseUrl}/chat/completions", array_merge([
                'model' => $this->chatModel,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 500,
            ], $options));

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';
                Log::error('OpenAI chat completion failed', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'content' => null,
                    'usage' => null,
                    'error' => $error,
                ];
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            $usage = $data['usage'] ?? null;

            return [
                'success' => true,
                'content' => $content,
                'usage' => $usage,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI chat completion exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'content' => null,
                'usage' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mock embedding generation for development/testing.
     * 
     * Generates a deterministic "embedding" based on text hash.
     * Not semantically meaningful, but consistent and testable.
     * 
     * @param string $text
     * @return array
     */
    protected function mockGenerateEmbedding(string $text): array
    {
        // Generate pseudo-random but deterministic vector from text hash
        $hash = md5($text);
        $seed = hexdec(substr($hash, 0, 8));
        
        // Generate 1536-dimensional vector (matches text-embedding-3-small)
        $embedding = [];
        mt_srand($seed);
        
        for ($i = 0; $i < 1536; $i++) {
            // Generate values between -1 and 1
            $embedding[] = (mt_rand() / mt_getrandmax()) * 2 - 1;
        }

        // Normalize vector to unit length (like real embeddings)
        $magnitude = sqrt(array_sum(array_map(fn($x) => $x * $x, $embedding)));
        $embedding = array_map(fn($x) => $x / $magnitude, $embedding);

        return [
            'success' => true,
            'embedding' => $embedding,
            'dimensions' => 1536,
            'error' => null,
            'mock' => true,
        ];
    }

    /**
     * Mock chat completion for development/testing.
     * 
     * @param array $messages
     * @return array
     */
    protected function mockChatCompletion(array $messages): array
    {
        $lastMessage = end($messages);
        $userContent = $lastMessage['content'] ?? '';

        // Generate simple mock response
        $mockResponse = "I'd be happy to help you find products related to: {$userContent}. " .
                       "Based on your interests, I recommend checking our featured items. " .
                       "[This is a mock AI response - configure OpenAI API key for real responses]";

        return [
            'success' => true,
            'content' => $mockResponse,
            'usage' => [
                'prompt_tokens' => 50,
                'completion_tokens' => 50,
                'total_tokens' => 100,
            ],
            'error' => null,
            'mock' => true,
        ];
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
}
