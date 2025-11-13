<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ProvenanceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

/**
 * Unit tests for ProvenanceService.
 * 
 * Tests IPFS upload via Pinata API with mocked HTTP responses.
 */
class ProvenanceServiceTest extends TestCase
{
    protected ProvenanceService $provenance;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set mock mode and configure Pinata
        Config::set('services.pinata.mock_mode', true);
        Config::set('services.pinata.api_key', 'test_key');
        Config::set('services.pinata.api_secret', 'test_secret');
        Config::set('services.pinata.jwt', '');
        Config::set('services.pinata.gateway', 'gateway.pinata.cloud');
        Config::set('services.pinata.timeout', 30);
        
        $this->provenance = new ProvenanceService();
    }

    /** @test */
    public function is_mock_mode_returns_correct_value()
    {
        $this->assertTrue($this->provenance->isMockMode());
        
        Config::set('services.pinata.mock_mode', false);
        $service = new ProvenanceService();
        $this->assertFalse($service->isMockMode());
    }

    /** @test */
    public function mock_upload_json_returns_valid_response()
    {
        $data = [
            'product_id' => 1,
            'event_type' => 'MANUFACTURED',
            'data' => ['location' => 'Factory A'],
        ];

        $result = $this->provenance->uploadJson($data, 'test-event');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ipfs_hash', $result);
        $this->assertArrayHasKey('ipfs_url', $result);
        $this->assertArrayHasKey('sha256', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('mock', $result);
        $this->assertTrue($result['mock']);
        
        // IPFS hash should start with 'Qm' and be 46 chars
        $this->assertStringStartsWith('Qm', $result['ipfs_hash']);
        $this->assertEquals(46, strlen($result['ipfs_hash']));
        
        // SHA-256 should be 64 hex chars
        $this->assertEquals(64, strlen($result['sha256']));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['sha256']);
    }

    /** @test */
    public function mock_upload_is_deterministic()
    {
        $data = ['test' => 'data'];

        $result1 = $this->provenance->uploadJson($data);
        $result2 = $this->provenance->uploadJson($data);

        // Same data should produce same hashes
        $this->assertEquals($result1['sha256'], $result2['sha256']);
        $this->assertEquals($result1['ipfs_hash'], $result2['ipfs_hash']);
    }

    /** @test */
    public function mock_upload_different_data_produces_different_hashes()
    {
        $data1 = ['test' => 'data1'];
        $data2 = ['test' => 'data2'];

        $result1 = $this->provenance->uploadJson($data1);
        $result2 = $this->provenance->uploadJson($data2);

        $this->assertNotEquals($result1['sha256'], $result2['sha256']);
        $this->assertNotEquals($result1['ipfs_hash'], $result2['ipfs_hash']);
    }

    /** @test */
    public function real_api_upload_json_success()
    {
        Config::set('services.pinata.mock_mode', false);
        $service = new ProvenanceService();

        $ipfsHash = 'QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG';
        
        Http::fake([
            'api.pinata.cloud/pinning/pinJSONToIPFS' => Http::response([
                'IpfsHash' => $ipfsHash,
                'PinSize' => 123,
                'Timestamp' => '2024-01-01T00:00:00.000Z',
            ], 200),
        ]);

        $data = ['test' => 'data'];
        $result = $service->uploadJson($data, 'test-pin');

        $this->assertEquals($ipfsHash, $result['ipfs_hash']);
        $this->assertStringContainsString($ipfsHash, $result['ipfs_url']);
        $this->assertArrayHasKey('sha256', $result);
        $this->assertEquals(123, $result['size']);
    }

    /** @test */
    public function real_api_upload_handles_error()
    {
        Config::set('services.pinata.mock_mode', false);
        $service = new ProvenanceService();

        Http::fake([
            'api.pinata.cloud/pinning/pinJSONToIPFS' => Http::response([
                'error' => 'Invalid API key',
            ], 401),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pinata upload failed: Invalid API key');

        $service->uploadJson(['test' => 'data']);
    }

    /** @test */
    public function real_api_upload_validates_response_structure()
    {
        Config::set('services.pinata.mock_mode', false);
        $service = new ProvenanceService();

        Http::fake([
            'api.pinata.cloud/pinning/pinJSONToIPFS' => Http::response([
                'PinSize' => 123,
                // Missing IpfsHash
            ], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid Pinata response: missing IpfsHash');

        $service->uploadJson(['test' => 'data']);
    }

    /** @test */
    public function compute_hash_produces_valid_sha256()
    {
        $data = ['test' => 'data'];
        $hash = $this->provenance->computeHash($data);

        $this->assertEquals(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    /** @test */
    public function compute_hash_is_deterministic()
    {
        $data = ['key' => 'value', 'nested' => ['a' => 1, 'b' => 2]];

        $hash1 = $this->provenance->computeHash($data);
        $hash2 = $this->provenance->computeHash($data);

        $this->assertEquals($hash1, $hash2);
    }

    /** @test */
    public function compute_hash_changes_with_data()
    {
        $hash1 = $this->provenance->computeHash(['test' => 1]);
        $hash2 = $this->provenance->computeHash(['test' => 2]);

        $this->assertNotEquals($hash1, $hash2);
    }

    /** @test */
    public function mock_unpin_returns_success()
    {
        $result = $this->provenance->unpin('QmTest123');
        $this->assertTrue($result);
    }

    /** @test */
    public function real_api_unpin_success()
    {
        Config::set('services.pinata.mock_mode', false);
        $service = new ProvenanceService();

        Http::fake([
            'api.pinata.cloud/pinning/unpin/QmTest123' => Http::response([], 200),
        ]);

        $result = $service->unpin('QmTest123');
        $this->assertTrue($result);
    }

    /** @test */
    public function real_api_unpin_handles_error()
    {
        Config::set('services.pinata.mock_mode', false);
        $service = new ProvenanceService();

        Http::fake([
            'api.pinata.cloud/pinning/unpin/QmTest123' => Http::response([], 404),
        ]);

        $result = $service->unpin('QmTest123');
        $this->assertFalse($result);
    }

    /** @test */
    public function mock_list_pins_returns_empty_array()
    {
        $result = $this->provenance->listPins();

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['count']);
        $this->assertIsArray($result['rows']);
        $this->assertEmpty($result['rows']);
    }

    /** @test */
    public function real_api_list_pins_success()
    {
        Config::set('services.pinata.mock_mode', false);
        $service = new ProvenanceService();

        Http::fake([
            'api.pinata.cloud/data/pinList*' => Http::response([
                'count' => 2,
                'rows' => [
                    ['ipfs_pin_hash' => 'QmHash1', 'date_pinned' => '2024-01-01'],
                    ['ipfs_pin_hash' => 'QmHash2', 'date_pinned' => '2024-01-02'],
                ],
            ], 200),
        ]);

        $result = $service->listPins(10);

        $this->assertEquals(2, $result['count']);
        $this->assertCount(2, $result['rows']);
    }

    /** @test */
    public function mock_test_connection_returns_ok()
    {
        $result = $this->provenance->testConnection();

        $this->assertEquals('ok', $result['status']);
        $this->assertTrue($result['mock_mode']);
        $this->assertArrayHasKey('message', $result);
    }

    /** @test */
    public function real_api_test_connection_success()
    {
        Config::set('services.pinata.mock_mode', false);
        $service = new ProvenanceService();

        Http::fake([
            'api.pinata.cloud/data/testAuthentication' => Http::response([
                'message' => 'Congratulations! You are communicating with the Pinata API!',
            ], 200),
        ]);

        $result = $service->testConnection();

        $this->assertEquals('ok', $result['status']);
        $this->assertFalse($result['mock_mode']);
    }

    /** @test */
    public function real_api_test_connection_handles_failure()
    {
        Config::set('services.pinata.mock_mode', false);
        $service = new ProvenanceService();

        Http::fake([
            'api.pinata.cloud/data/testAuthentication' => Http::response([], 401),
        ]);

        $result = $service->testConnection();

        $this->assertEquals('error', $result['status']);
        $this->assertFalse($result['mock_mode']);
    }

    /** @test */
    public function get_gateway_url_formats_correctly()
    {
        $hash = 'QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG';
        $url = $this->provenance->getGatewayUrl($hash);

        $this->assertEquals("https://gateway.pinata.cloud/ipfs/{$hash}", $url);
    }

    /** @test */
    public function auth_headers_use_jwt_when_available()
    {
        Config::set('services.pinata.jwt', 'test_jwt_token');
        $service = new ProvenanceService();

        // Access protected method via reflection
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getAuthHeaders');
        $method->setAccessible(true);
        $headers = $method->invoke($service);

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer test_jwt_token', $headers['Authorization']);
    }

    /** @test */
    public function auth_headers_use_api_key_when_no_jwt()
    {
        Config::set('services.pinata.jwt', '');
        Config::set('services.pinata.api_key', 'test_key');
        Config::set('services.pinata.api_secret', 'test_secret');
        $service = new ProvenanceService();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getAuthHeaders');
        $method->setAccessible(true);
        $headers = $method->invoke($service);

        $this->assertArrayHasKey('pinata_api_key', $headers);
        $this->assertEquals('test_key', $headers['pinata_api_key']);
        $this->assertArrayHasKey('pinata_secret_api_key', $headers);
        $this->assertEquals('test_secret', $headers['pinata_secret_api_key']);
    }
}
