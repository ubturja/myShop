<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ProvenanceEvent;
use App\Services\ProvenanceService;
use App\Services\Web3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

/**
 * Feature tests for ProvenanceController endpoints.
 * 
 * Tests provenance event creation, IPFS upload, blockchain anchoring.
 */
class ProvenanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate user
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        
        // Create test product
        $this->product = Product::factory()->create();
    }

    /** @test */
    public function health_endpoint_returns_service_status()
    {
        $mockProvenance = Mockery::mock(ProvenanceService::class);
        $mockProvenance->shouldReceive('testConnection')
            ->once()
            ->andReturn([
                'status' => 'ok',
                'mock_mode' => true,
                'message' => 'Mock mode enabled',
            ]);

        $this->app->instance(ProvenanceService::class, $mockProvenance);

        $response = $this->withToken($this->token)
            ->getJson('/api/provenance/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'provenance_service' => [
                    'status' => 'ok',
                    'mock_mode' => true,
                ],
            ]);
    }

    /** @test */
    public function health_endpoint_handles_service_error()
    {
        $mockProvenance = Mockery::mock(ProvenanceService::class);
        $mockProvenance->shouldReceive('testConnection')
            ->once()
            ->andReturn([
                'status' => 'error',
                'mock_mode' => false,
                'message' => 'Connection failed',
            ]);

        $this->app->instance(ProvenanceService::class, $mockProvenance);

        $response = $this->withToken($this->token)
            ->getJson('/api/provenance/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    /** @test */
    public function create_event_without_anchoring()
    {
        $mockProvenance = Mockery::mock(ProvenanceService::class);
        $mockProvenance->shouldReceive('uploadJson')
            ->once()
            ->andReturn([
                'ipfs_hash' => 'QmTest123',
                'ipfs_url' => 'https://gateway.pinata.cloud/ipfs/QmTest123',
                'sha256' => hash('sha256', 'test'),
                'size' => 100,
                'timestamp' => now()->toIso8601String(),
            ]);
        $mockProvenance->shouldReceive('isMockMode')->andReturn(true);

        $this->app->instance(ProvenanceService::class, $mockProvenance);

        $response = $this->withToken($this->token)
            ->postJson('/api/provenance/events', [
                'product_id' => $this->product->id,
                'event_type' => 'MANUFACTURED',
                'data' => [
                    'location' => 'Factory A',
                    'inspector' => 'John Doe',
                ],
                'anchor' => false,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'anchored' => false,
                'mock_mode' => true,
                'event' => [
                    'product_id' => $this->product->id,
                    'event_type' => 'MANUFACTURED',
                    'ipfs_hash' => 'QmTest123',
                ],
            ]);

        // Verify database record
        $this->assertDatabaseHas('provenance_events', [
            'product_id' => $this->product->id,
            'event_type' => 'MANUFACTURED',
            'ipfs_hash' => 'QmTest123',
            'tx_hash' => null,
        ]);
    }

    /** @test */
    public function create_event_with_blockchain_anchoring()
    {
        $sha256 = hash('sha256', 'test');
        $txHash = '0x' . str_repeat('a', 64);

        $mockProvenance = Mockery::mock(ProvenanceService::class);
        $mockProvenance->shouldReceive('uploadJson')
            ->once()
            ->andReturn([
                'ipfs_hash' => 'QmTest456',
                'ipfs_url' => 'https://gateway.pinata.cloud/ipfs/QmTest456',
                'sha256' => $sha256,
                'size' => 100,
                'timestamp' => now()->toIso8601String(),
            ]);
        $mockProvenance->shouldReceive('isMockMode')->andReturn(true);

        $mockWeb3 = Mockery::mock(Web3Client::class);
        $mockWeb3->shouldReceive('anchorHash')
            ->once()
            ->with($sha256)
            ->andReturn([
                'success' => true,
                'txHash' => $txHash,
            ]);

        $this->app->instance(ProvenanceService::class, $mockProvenance);
        $this->app->instance(Web3Client::class, $mockWeb3);

        $response = $this->withToken($this->token)
            ->postJson('/api/provenance/events', [
                'product_id' => $this->product->id,
                'event_type' => 'SHIPPED',
                'data' => [
                    'carrier' => 'DHL',
                    'tracking' => 'DHL123456',
                ],
                'anchor' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'anchored' => true,
                'event' => [
                    'product_id' => $this->product->id,
                    'event_type' => 'SHIPPED',
                    'ipfs_hash' => 'QmTest456',
                    'tx_hash' => $txHash,
                ],
            ]);

        // Verify blockchain anchoring
        $this->assertDatabaseHas('provenance_events', [
            'product_id' => $this->product->id,
            'event_type' => 'SHIPPED',
            'tx_hash' => $txHash,
        ]);
    }

    /** @test */
    public function create_event_continues_on_anchor_failure()
    {
        $mockProvenance = Mockery::mock(ProvenanceService::class);
        $mockProvenance->shouldReceive('uploadJson')
            ->once()
            ->andReturn([
                'ipfs_hash' => 'QmTest789',
                'ipfs_url' => 'https://gateway.pinata.cloud/ipfs/QmTest789',
                'sha256' => hash('sha256', 'test'),
                'size' => 100,
                'timestamp' => now()->toIso8601String(),
            ]);
        $mockProvenance->shouldReceive('isMockMode')->andReturn(true);

        $mockWeb3 = Mockery::mock(Web3Client::class);
        $mockWeb3->shouldReceive('anchorHash')
            ->once()
            ->andThrow(new \Exception('Blockchain error'));

        $this->app->instance(ProvenanceService::class, $mockProvenance);
        $this->app->instance(Web3Client::class, $mockWeb3);

        $response = $this->withToken($this->token)
            ->postJson('/api/provenance/events', [
                'product_id' => $this->product->id,
                'event_type' => 'QUALITY_CHECK',
                'data' => ['passed' => true],
                'anchor' => true,
            ]);

        // Should still succeed despite anchor failure
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'anchored' => false, // Anchoring failed
                'event' => [
                    'ipfs_hash' => 'QmTest789',
                    'tx_hash' => null,
                ],
            ]);
    }

    /** @test */
    public function create_event_requires_authentication()
    {
        $response = $this->postJson('/api/provenance/events', [
            'product_id' => $this->product->id,
            'event_type' => 'TEST',
            'data' => [],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function create_event_validates_required_fields()
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/provenance/events', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'event_type', 'data']);
    }

    /** @test */
    public function create_event_validates_product_exists()
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/provenance/events', [
                'product_id' => 99999,
                'event_type' => 'TEST',
                'data' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    /** @test */
    public function create_event_validates_data_is_array()
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/provenance/events', [
                'product_id' => $this->product->id,
                'event_type' => 'TEST',
                'data' => 'not-an-array',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
    }

    /** @test */
    public function create_event_rolls_back_on_ipfs_error()
    {
        $mockProvenance = Mockery::mock(ProvenanceService::class);
        $mockProvenance->shouldReceive('uploadJson')
            ->once()
            ->andThrow(new \Exception('IPFS upload failed'));

        $this->app->instance(ProvenanceService::class, $mockProvenance);

        $response = $this->withToken($this->token)
            ->postJson('/api/provenance/events', [
                'product_id' => $this->product->id,
                'event_type' => 'TEST',
                'data' => ['test' => 'value'],
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to create provenance event',
            ]);

        // Verify no database record created
        $this->assertDatabaseCount('provenance_events', 0);
    }

    /** @test */
    public function show_returns_event_details()
    {
        $event = ProvenanceEvent::factory()->create([
            'product_id' => $this->product->id,
            'event_type' => 'MANUFACTURED',
            'data' => ['location' => 'Factory A'],
            'ipfs_hash' => 'QmTest123',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/provenance/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'event' => [
                    'id' => $event->id,
                    'product_id' => $this->product->id,
                    'event_type' => 'MANUFACTURED',
                    'ipfs_hash' => 'QmTest123',
                    'product' => [
                        'id' => $this->product->id,
                    ],
                ],
            ]);
    }

    /** @test */
    public function show_returns_404_for_nonexistent_event()
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/provenance/events/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Provenance event not found',
            ]);
    }

    /** @test */
    public function get_by_product_returns_events_chronologically()
    {
        // Create events with different timestamps
        $event1 = ProvenanceEvent::factory()->create([
            'product_id' => $this->product->id,
            'event_type' => 'MANUFACTURED',
            'created_at' => now()->subDays(3),
        ]);
        $event2 = ProvenanceEvent::factory()->create([
            'product_id' => $this->product->id,
            'event_type' => 'SHIPPED',
            'created_at' => now()->subDays(2),
        ]);
        $event3 = ProvenanceEvent::factory()->create([
            'product_id' => $this->product->id,
            'event_type' => 'DELIVERED',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/products/{$this->product->id}/provenance");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 3,
                'product' => [
                    'id' => $this->product->id,
                ],
            ]);

        $events = $response->json('events');
        $this->assertEquals('MANUFACTURED', $events[0]['event_type']); // Oldest
        $this->assertEquals('SHIPPED', $events[1]['event_type']);
        $this->assertEquals('DELIVERED', $events[2]['event_type']); // Newest
    }

    /** @test */
    public function get_by_product_returns_404_for_nonexistent_product()
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/products/99999/provenance');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Product not found',
            ]);
    }

    /** @test */
    public function get_by_product_returns_empty_array_when_no_events()
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/products/{$this->product->id}/provenance");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 0,
                'events' => [],
            ]);
    }

    /** @test */
    public function stats_returns_aggregated_data()
    {
        // Create events with specific anchoring status
        ProvenanceEvent::factory()->count(3)->create([
            'event_type' => 'MANUFACTURED',
            'tx_hash' => null,
        ]);
        ProvenanceEvent::factory()->count(2)->create([
            'event_type' => 'SHIPPED',
            'tx_hash' => '0x' . str_repeat('a', 64),
        ]);

        $mockProvenance = Mockery::mock(ProvenanceService::class);
        $mockProvenance->shouldReceive('isMockMode')->andReturn(true);
        $this->app->instance(ProvenanceService::class, $mockProvenance);

        $response = $this->withToken($this->token)
            ->getJson('/api/provenance/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'mock_mode' => true,
                'stats' => [
                    'total_events' => 5,
                    'anchored_events' => 2,
                    'anchored_percentage' => 40.0,
                ],
            ]);

        $eventsByType = $response->json('stats.events_by_type');
        $this->assertEquals(3, $eventsByType['MANUFACTURED']);
        $this->assertEquals(2, $eventsByType['SHIPPED']);
    }

    /** @test */
    public function all_endpoints_require_authentication()
    {
        $event = ProvenanceEvent::factory()->create(['product_id' => $this->product->id]);

        $this->getJson('/api/provenance/health')->assertStatus(401);
        $this->postJson('/api/provenance/events')->assertStatus(401);
        $this->getJson("/api/provenance/events/{$event->id}")->assertStatus(401);
        $this->getJson("/api/products/{$this->product->id}/provenance")->assertStatus(401);
        $this->getJson('/api/provenance/stats')->assertStatus(401);
    }

    /**
     * Helper to add Bearer token to request.
     */
    public function withToken(string $token, string $type = 'Bearer')
    {
        return $this->withHeader('Authorization', "{$type} {$token}");
    }
}
