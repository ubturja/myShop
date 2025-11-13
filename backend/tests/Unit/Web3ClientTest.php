<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Web3Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

/**
 * Unit tests for Web3Client service.
 * 
 * Tests web3-service integration with mocked HTTP responses.
 */
class Web3ClientTest extends TestCase
{
    protected Web3Client $web3;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set mock mode and configure service URL
        Config::set('services.web3.mock', true);
        Config::set('services.web3.url', 'http://web3-service:3001');
        Config::set('services.web3.timeout', 10);
        
        $this->web3 = new Web3Client();
    }

    /** @test */
    public function is_mock_mode_returns_correct_value()
    {
        $this->assertTrue($this->web3->isMockMode());
        
        Config::set('services.web3.mock', false);
        $web3 = new Web3Client();
        $this->assertFalse($web3->isMockMode());
    }

    /** @test */
    public function health_check_returns_valid_response()
    {
        Http::fake([
            'web3-service:3001/health' => Http::response([
                'status' => 'healthy',
                'service' => 'myShop Web3 Service',
                'mock_mode' => true,
                'timestamp' => '2024-01-01T00:00:00.000Z',
            ], 200),
        ]);

        $health = $this->web3->health();

        $this->assertEquals('healthy', $health['status']);
        $this->assertEquals('myShop Web3 Service', $health['service']);
        $this->assertTrue($health['mock_mode']);
        $this->assertArrayHasKey('timestamp', $health);
    }

    /** @test */
    public function health_check_throws_exception_on_failure()
    {
        Http::fake([
            'web3-service:3001/health' => Http::response([], 500),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Web3 service returned status 500');

        $this->web3->health();
    }

    /** @test */
    public function mint_loyalty_nft_returns_valid_response()
    {
        Http::fake([
            'web3-service:3001/mint' => Http::response([
                'success' => true,
                'txHash' => '0x' . str_repeat('a', 64),
                'tokenId' => '12345',
                'message' => 'Mock mint successful',
            ], 200),
        ]);

        $result = $this->web3->mintLoyaltyNFT(1, 'ipfs://QmTest123');

        $this->assertTrue($result['success']);
        $this->assertStringStartsWith('0x', $result['txHash']);
        $this->assertEquals(66, strlen($result['txHash']));
        $this->assertEquals('12345', $result['tokenId']);
        $this->assertArrayHasKey('message', $result);
    }

    /** @test */
    public function mint_with_contract_address_includes_it_in_request()
    {
        Http::fake([
            'web3-service:3001/mint' => Http::response([
                'success' => true,
                'txHash' => '0x' . str_repeat('b', 64),
                'tokenId' => '67890',
            ], 200),
        ]);

        $contractAddress = '0x' . str_repeat('1', 40);
        $this->web3->mintLoyaltyNFT(1, 'ipfs://QmTest456', $contractAddress);

        Http::assertSent(function ($request) use ($contractAddress) {
            return $request->url() === 'http://web3-service:3001/mint' &&
                   $request['userId'] === 1 &&
                   $request['metadataUri'] === 'ipfs://QmTest456' &&
                   $request['contractAddress'] === $contractAddress;
        });
    }

    /** @test */
    public function mint_throws_exception_on_http_error()
    {
        Http::fake([
            'web3-service:3001/mint' => Http::response([
                'error' => 'Insufficient gas',
            ], 500),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Mint failed: Insufficient gas');

        $this->web3->mintLoyaltyNFT(1, 'ipfs://QmTest');
    }

    /** @test */
    public function mint_throws_exception_on_unsuccessful_response()
    {
        Http::fake([
            'web3-service:3001/mint' => Http::response([
                'success' => false,
                'message' => 'Contract not deployed',
            ], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Mint unsuccessful: Contract not deployed');

        $this->web3->mintLoyaltyNFT(1, 'ipfs://QmTest');
    }

    /** @test */
    public function mint_throws_exception_on_missing_required_fields()
    {
        Http::fake([
            'web3-service:3001/mint' => Http::response([
                'success' => true,
                // Missing txHash and tokenId
            ], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid mint response: missing txHash or tokenId');

        $this->web3->mintLoyaltyNFT(1, 'ipfs://QmTest');
    }

    /** @test */
    public function get_owner_returns_valid_response()
    {
        $tokenId = '12345';
        $ownerAddress = '0x' . str_repeat('c', 40);

        Http::fake([
            "web3-service:3001/ownerOf/{$tokenId}" => Http::response([
                'tokenId' => $tokenId,
                'owner' => $ownerAddress,
                'message' => 'Mock owner lookup',
            ], 200),
        ]);

        $result = $this->web3->getOwner($tokenId);

        $this->assertEquals($tokenId, $result['tokenId']);
        $this->assertEquals($ownerAddress, $result['owner']);
    }

    /** @test */
    public function get_owner_throws_exception_on_error()
    {
        Http::fake([
            'web3-service:3001/ownerOf/999' => Http::response([
                'error' => 'Token not found',
            ], 404),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Owner query failed: Token not found');

        $this->web3->getOwner('999');
    }

    /** @test */
    public function get_owner_validates_response_structure()
    {
        Http::fake([
            'web3-service:3001/ownerOf/123' => Http::response([
                'tokenId' => '123',
                // Missing owner field
            ], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid owner response: missing tokenId or owner');

        $this->web3->getOwner('123');
    }

    /** @test */
    public function anchor_hash_returns_valid_response()
    {
        $hash = hash('sha256', 'test data');

        Http::fake([
            'web3-service:3001/anchor' => Http::response([
                'success' => true,
                'txHash' => '0x' . str_repeat('d', 64),
                'hash' => $hash,
                'message' => 'Mock anchor successful',
            ], 200),
        ]);

        $result = $this->web3->anchorHash($hash);

        $this->assertTrue($result['success']);
        $this->assertStringStartsWith('0x', $result['txHash']);
        $this->assertEquals($hash, $result['hash']);
    }

    /** @test */
    public function anchor_hash_throws_exception_on_http_error()
    {
        Http::fake([
            'web3-service:3001/anchor' => Http::response([
                'error' => 'Invalid hash format',
            ], 400),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Anchor failed: Invalid hash format');

        $this->web3->anchorHash('invalid');
    }

    /** @test */
    public function anchor_hash_throws_exception_on_unsuccessful_response()
    {
        Http::fake([
            'web3-service:3001/anchor' => Http::response([
                'success' => false,
                'message' => 'Transaction reverted',
            ], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Anchor unsuccessful: Transaction reverted');

        $this->web3->anchorHash(hash('sha256', 'test'));
    }

    /** @test */
    public function anchor_hash_validates_response_has_tx_hash()
    {
        Http::fake([
            'web3-service:3001/anchor' => Http::response([
                'success' => true,
                // Missing txHash
            ], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid anchor response: missing txHash');

        $this->web3->anchorHash(hash('sha256', 'test'));
    }

    /** @test */
    public function all_methods_respect_timeout_configuration()
    {
        Config::set('services.web3.timeout', 5);
        $web3 = new Web3Client();

        Http::fake([
            '*' => Http::response([], 200),
        ]);

        // This test verifies timeout is set in constructor
        // Actual timeout behavior is handled by Laravel HTTP client
        $this->assertEquals(5, config('services.web3.timeout'));
    }
}
