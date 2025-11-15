<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ProvenanceService - Manages IPFS uploads via Pinata API.
 * 
 * Provides methods to:
 * - Upload JSON metadata to IPFS via Pinata
 * - Pin content to ensure persistence
 * - Compute SHA-256 hashes for blockchain anchoring
 * 
 * Supports mock mode for development without Pinata API keys.
 */
class ProvenanceService
{
    protected string $apiKey;
    protected string $apiSecret;
    protected string $jwt;
    protected bool $mockMode;
    protected string $gateway;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.pinata.api_key', '');
        $this->apiSecret = config('services.pinata.api_secret', '');
        $this->jwt = config('services.pinata.jwt', '');
        $this->mockMode = config('services.pinata.mock_mode', true);
        $this->gateway = config('services.pinata.gateway', 'gateway.pinata.cloud');
        $this->timeout = config('services.pinata.timeout', 30);
    }

    /**
     * Check if service is running in mock mode.
     */
    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    /**
     * Upload JSON data to IPFS via Pinata.
     * 
     * @param array $data JSON data to upload
     * @param string|null $name Optional pin name for Pinata dashboard
     * @return array Response with IPFS hash and metadata
     * @throws Exception If upload fails
     */
    public function uploadJson(array $data, ?string $name = null): array
    {
        if ($this->mockMode) {
            return $this->mockUploadJson($data, $name);
        }

        try {
            $payload = [
                'pinataContent' => $data,
                'pinataMetadata' => [
                    'name' => $name ?? 'provenance-event-' . time(),
                ],
            ];

            Log::info('ProvenanceService: Uploading to IPFS', [
                'name' => $payload['pinataMetadata']['name'],
                'data_size' => strlen(json_encode($data)),
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAuthHeaders())
                ->post('https://api.pinata.cloud/pinning/pinJSONToIPFS', $payload);

            if (!$response->successful()) {
                $error = $response->json()['error'] ?? 'Unknown error';
                throw new Exception("Pinata upload failed: {$error}");
            }

            $result = $response->json();

            if (!isset($result['IpfsHash'])) {
                throw new Exception('Invalid Pinata response: missing IpfsHash');
            }

            $ipfsHash = $result['IpfsHash'];
            $sha256 = $this->computeHash($data);

            Log::info('ProvenanceService: Upload successful', [
                'ipfs_hash' => $ipfsHash,
                'sha256' => $sha256,
            ]);

            return [
                'ipfs_hash' => $ipfsHash,
                'ipfs_url' => "https://{$this->gateway}/ipfs/{$ipfsHash}",
                'sha256' => $sha256,
                'size' => $result['PinSize'] ?? null,
                'timestamp' => $result['Timestamp'] ?? now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('ProvenanceService: Upload failed', [
                'error' => $e->getMessage(),
                'name' => $name,
            ]);
            throw $e;
        }
    }

    /**
     * Mock upload for development/testing.
     * 
     * @param array $data JSON data to upload
     * @param string|null $name Optional pin name
     * @return array Mock response with deterministic hash
     */
    protected function mockUploadJson(array $data, ?string $name = null): array
    {
        $sha256 = $this->computeHash($data);
        
        // Generate deterministic mock IPFS hash based on SHA-256
        // Real IPFS CIDs start with 'Qm' and are 46 chars
        $mockHash = 'Qm' . substr(base64_encode(hex2bin($sha256)), 0, 44);
        
        Log::info('ProvenanceService: Mock upload', [
            'mock_ipfs_hash' => $mockHash,
            'sha256' => $sha256,
            'name' => $name ?? 'provenance-event-' . time(),
        ]);

        return [
            'ipfs_hash' => $mockHash,
            'ipfs_url' => "https://{$this->gateway}/ipfs/{$mockHash}",
            'sha256' => $sha256,
            'size' => strlen(json_encode($data)),
            'timestamp' => now()->toIso8601String(),
            'mock' => true,
        ];
    }

    /**
     * Compute SHA-256 hash of JSON data.
     * 
     * @param array $data Data to hash
     * @return string Hex-encoded SHA-256 hash
     */
    public function computeHash(array $data): string
    {
        // Ensure consistent JSON encoding for deterministic hashing
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $json);
    }

    /**
     * Unpin content from Pinata (remove from permanent storage).
     * 
     * @param string $ipfsHash IPFS CID to unpin
     * @return bool Success status
     */
    public function unpin(string $ipfsHash): bool
    {
        if ($this->mockMode) {
            Log::info('ProvenanceService: Mock unpin', ['ipfs_hash' => $ipfsHash]);
            return true;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAuthHeaders())
                ->delete("https://api.pinata.cloud/pinning/unpin/{$ipfsHash}");

            if (!$response->successful()) {
                Log::warning('ProvenanceService: Unpin failed', [
                    'ipfs_hash' => $ipfsHash,
                    'status' => $response->status(),
                ]);
                return false;
            }

            Log::info('ProvenanceService: Unpin successful', ['ipfs_hash' => $ipfsHash]);
            return true;
        } catch (Exception $e) {
            Log::error('ProvenanceService: Unpin error', [
                'ipfs_hash' => $ipfsHash,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get list of pinned files from Pinata.
     * 
     * @param int $limit Maximum number of results
     * @return array List of pinned files
     */
    public function listPins(int $limit = 10): array
    {
        if ($this->mockMode) {
            return [
                'count' => 0,
                'rows' => [],
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAuthHeaders())
                ->get('https://api.pinata.cloud/data/pinList', [
                    'pageLimit' => $limit,
                    'status' => 'pinned',
                ]);

            if (!$response->successful()) {
                throw new Exception('Failed to retrieve pin list');
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('ProvenanceService: List pins failed', [
                'error' => $e->getMessage(),
            ]);
            return ['count' => 0, 'rows' => []];
        }
    }

    /**
     * Test Pinata API connection.
     * 
     * @return array Status information
     */
    public function testConnection(): array
    {
        if ($this->mockMode) {
            return [
                'status' => 'ok',
                'mock_mode' => true,
                'message' => 'Mock mode enabled',
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAuthHeaders())
                ->get('https://api.pinata.cloud/data/testAuthentication');

            if ($response->successful()) {
                return [
                    'status' => 'ok',
                    'mock_mode' => false,
                    'message' => $response->json()['message'] ?? 'Connected to Pinata',
                ];
            }

            return [
                'status' => 'error',
                'mock_mode' => false,
                'message' => 'Authentication failed',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'mock_mode' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get authentication headers for Pinata API.
     * 
     * @return array HTTP headers
     */
    protected function getAuthHeaders(): array
    {
        // Prefer JWT if available, otherwise use API key/secret
        if (!empty($this->jwt)) {
            return [
                'Authorization' => "Bearer {$this->jwt}",
                'Content-Type' => 'application/json',
            ];
        }

        return [
            'pinata_api_key' => $this->apiKey,
            'pinata_secret_api_key' => $this->apiSecret,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get IPFS gateway URL for a hash.
     * 
     * @param string $ipfsHash IPFS CID
     * @return string Gateway URL
     */
    public function getGatewayUrl(string $ipfsHash): string
    {
        return "https://{$this->gateway}/ipfs/{$ipfsHash}";
    }
}
