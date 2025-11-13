<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\NFTToken;
use App\Services\Web3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;

/**
 * Feature tests for Web3Controller endpoints.
 * 
 * Tests NFT minting, retrieval, and web3-service integration.
 */
class Web3ControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate user
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /** @test */
    public function health_endpoint_returns_web3_service_status()
    {
        // Mock Web3Client
        $mockWeb3 = Mockery::mock(Web3Client::class);
        $mockWeb3->shouldReceive('health')
            ->once()
            ->andReturn([
                'status' => 'healthy',
                'service' => 'myShop Web3 Service',
                'mock_mode' => true,
            ]);

        $this->app->instance(Web3Client::class, $mockWeb3);

        $response = $this->withToken($this->token)
            ->getJson('/api/web3/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'web3_service' => [
                    'status' => 'healthy',
                    'service' => 'myShop Web3 Service',
                    'mock_mode' => true,
                ],
            ]);
    }

    /** @test */
    public function health_endpoint_handles_service_unavailable()
    {
        $mockWeb3 = Mockery::mock(Web3Client::class);
        $mockWeb3->shouldReceive('health')
            ->once()
            ->andThrow(new \Exception('Connection refused'));

        $this->app->instance(Web3Client::class, $mockWeb3);

        $response = $this->withToken($this->token)
            ->getJson('/api/web3/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'error',
                'message' => 'Web3 service unavailable',
            ]);
    }

    /** @test */
    public function mint_loyalty_nft_creates_token_in_database()
    {
        // Mock Web3Client to return successful mint
        $mockWeb3 = Mockery::mock(Web3Client::class);
        $mockWeb3->shouldReceive('mintLoyaltyNFT')
            ->once()
            ->with($this->user->id, 'ipfs://QmTest123', null)
            ->andReturn([
                'success' => true,
                'txHash' => '0x' . str_repeat('a', 64),
                'tokenId' => '12345',
            ]);
        $mockWeb3->shouldReceive('isMockMode')
            ->once()
            ->andReturn(true);

        $this->app->instance(Web3Client::class, $mockWeb3);

        $response = $this->withToken($this->token)
            ->postJson('/api/web3/mint-loyalty', [
                'user_id' => $this->user->id,
                'metadata_uri' => 'ipfs://QmTest123',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'mock_mode' => true,
                'nft' => [
                    'user_id' => $this->user->id,
                    'token_id' => '12345',
                    'metadata_uri' => 'ipfs://QmTest123',
                ],
            ]);

        // Verify database record
        $this->assertDatabaseHas('nft_tokens', [
            'user_id' => $this->user->id,
            'token_id' => '12345',
            'metadata_uri' => 'ipfs://QmTest123',
        ]);
    }

    /** @test */
    public function mint_loyalty_with_custom_contract_address()
    {
        $contractAddress = '0x' . str_repeat('1', 40);

        $mockWeb3 = Mockery::mock(Web3Client::class);
        $mockWeb3->shouldReceive('mintLoyaltyNFT')
            ->once()
            ->with($this->user->id, 'ipfs://QmCustom', $contractAddress)
            ->andReturn([
                'success' => true,
                'txHash' => '0x' . str_repeat('b', 64),
                'tokenId' => '67890',
            ]);
        $mockWeb3->shouldReceive('isMockMode')->andReturn(true);

        $this->app->instance(Web3Client::class, $mockWeb3);

        $response = $this->withToken($this->token)
            ->postJson('/api/web3/mint-loyalty', [
                'user_id' => $this->user->id,
                'metadata_uri' => 'ipfs://QmCustom',
                'contract_address' => $contractAddress,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('nft_tokens', [
            'user_id' => $this->user->id,
            'token_id' => '67890',
            'contract_address' => $contractAddress,
        ]);
    }

    /** @test */
    public function mint_requires_authentication()
    {
        $response = $this->postJson('/api/web3/mint-loyalty', [
            'user_id' => $this->user->id,
            'metadata_uri' => 'ipfs://QmTest',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function mint_validates_required_fields()
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/web3/mint-loyalty', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'metadata_uri']);
    }

    /** @test */
    public function mint_validates_user_exists()
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/web3/mint-loyalty', [
                'user_id' => 99999,
                'metadata_uri' => 'ipfs://QmTest',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    /** @test */
    public function mint_validates_metadata_uri_format()
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/web3/mint-loyalty', [
                'user_id' => $this->user->id,
                'metadata_uri' => str_repeat('a', 501), // Too long
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['metadata_uri']);
    }

    /** @test */
    public function mint_rolls_back_on_web3_service_error()
    {
        $mockWeb3 = Mockery::mock(Web3Client::class);
        $mockWeb3->shouldReceive('mintLoyaltyNFT')
            ->once()
            ->andThrow(new \Exception('Transaction failed'));

        $this->app->instance(Web3Client::class, $mockWeb3);

        $response = $this->withToken($this->token)
            ->postJson('/api/web3/mint-loyalty', [
                'user_id' => $this->user->id,
                'metadata_uri' => 'ipfs://QmTest',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to mint NFT',
            ]);

        // Verify no database record was created
        $this->assertDatabaseCount('nft_tokens', 0);
    }

    /** @test */
    public function show_returns_nft_details()
    {
        $nft = NFTToken::factory()->create([
            'user_id' => $this->user->id,
            'token_id' => '12345',
            'metadata_uri' => 'ipfs://QmTest',
            'tx_hash' => '0x' . str_repeat('a', 64),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/web3/tokens/{$nft->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'nft' => [
                    'id' => $nft->id,
                    'user_id' => $this->user->id,
                    'token_id' => '12345',
                    'metadata_uri' => 'ipfs://QmTest',
                    'user' => [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                    ],
                ],
            ]);
    }

    /** @test */
    public function show_returns_404_for_nonexistent_nft()
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/web3/tokens/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'NFT not found',
            ]);
    }

    /** @test */
    public function my_tokens_returns_user_nfts()
    {
        // Create NFTs for this user
        NFTToken::factory()->count(3)->create(['user_id' => $this->user->id]);

        // Create NFTs for another user
        $otherUser = User::factory()->create();
        NFTToken::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/web3/my-tokens');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 3,
            ])
            ->assertJsonCount(3, 'tokens');

        // Verify all returned tokens belong to authenticated user
        $tokens = $response->json('tokens');
        foreach ($tokens as $token) {
            $this->assertDatabaseHas('nft_tokens', [
                'id' => $token['id'],
                'user_id' => $this->user->id,
            ]);
        }
    }

    /** @test */
    public function my_tokens_returns_empty_array_when_no_tokens()
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/web3/my-tokens');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 0,
                'tokens' => [],
            ]);
    }

    /** @test */
    public function my_tokens_orders_by_minted_at_descending()
    {
        // Create NFTs with different timestamps
        $nft1 = NFTToken::factory()->create([
            'user_id' => $this->user->id,
            'token_id' => '111',
            'minted_at' => now()->subDays(3),
        ]);
        $nft2 = NFTToken::factory()->create([
            'user_id' => $this->user->id,
            'token_id' => '222',
            'minted_at' => now()->subDays(1),
        ]);
        $nft3 = NFTToken::factory()->create([
            'user_id' => $this->user->id,
            'token_id' => '333',
            'minted_at' => now()->subDays(2),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/web3/my-tokens');

        $response->assertStatus(200);

        $tokens = $response->json('tokens');
        $this->assertEquals('222', $tokens[0]['token_id']); // Most recent
        $this->assertEquals('333', $tokens[1]['token_id']);
        $this->assertEquals('111', $tokens[2]['token_id']); // Oldest
    }

    /** @test */
    public function nft_includes_polygonscan_url()
    {
        $txHash = '0x' . str_repeat('a', 64);
        $nft = NFTToken::factory()->create([
            'user_id' => $this->user->id,
            'tx_hash' => $txHash,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/web3/tokens/{$nft->id}");

        $response->assertStatus(200)
            ->assertJson([
                'nft' => [
                    'polygonscan_url' => "https://mumbai.polygonscan.com/tx/{$txHash}",
                ],
            ]);
    }

    /** @test */
    public function all_endpoints_require_authentication()
    {
        $nft = NFTToken::factory()->create(['user_id' => $this->user->id]);

        // Test all endpoints without token
        $this->getJson('/api/web3/health')->assertStatus(401);
        $this->postJson('/api/web3/mint-loyalty')->assertStatus(401);
        $this->getJson("/api/web3/tokens/{$nft->id}")->assertStatus(401);
        $this->getJson('/api/web3/my-tokens')->assertStatus(401);
    }

    /**
     * Helper to add Bearer token to request.
     */
    public function withToken(string $token, string $type = 'Bearer')
    {
        return $this->withHeader('Authorization', "{$type} {$token}");
    }
}
