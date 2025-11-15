<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Web3Client - Interface to web3-service for blockchain operations.
 * 
 * Provides methods to:
 * - Mint loyalty NFTs on Polygon
 * - Query NFT ownership
 * - Anchor provenance hashes to blockchain
 * 
 * Supports mock mode for development without blockchain connection.
 */
class Web3Client
{
    protected string $baseUrl;
    protected bool $mockMode;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.web3.url', 'http://web3-service:3001');
        $this->mockMode = config('services.web3.mock', true);
        $this->timeout = config('services.web3.timeout', 10);
    }

    /**
     * Check if the service is running in mock mode.
     */
    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    /**
     * Get the web3-service health status.
     * 
     * @return array Health status data
     * @throws Exception If service is unreachable
     */
    public function health(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/health");

            if (!$response->successful()) {
                throw new Exception("Web3 service returned status {$response->status()}");
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Web3Client health check failed', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl,
            ]);
            throw $e;
        }
    }

    /**
     * Mint a loyalty NFT for a user.
     * 
     * @param int $userId User ID to mint for
     * @param string $metadataUri IPFS or HTTP URL to token metadata
     * @param string|null $contractAddress Optional contract address override
     * @return array Mint response with txHash and tokenId
     * @throws Exception If minting fails
     */
    public function mintLoyaltyNFT(int $userId, string $metadataUri, ?string $contractAddress = null): array
    {
        try {
            $payload = [
                'userId' => $userId,
                'metadataUri' => $metadataUri,
            ];

            if ($contractAddress) {
                $payload['contractAddress'] = $contractAddress;
            }

            Log::info('Web3Client: Minting loyalty NFT', $payload);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/mint", $payload);

            if (!$response->successful()) {
                $errorMessage = $response->json()['error'] ?? 'Unknown error';
                throw new Exception("Mint failed: {$errorMessage}");
            }

            $data = $response->json();

            // Validate response structure
            if (!isset($data['success']) || !$data['success']) {
                throw new Exception("Mint unsuccessful: " . ($data['message'] ?? 'Unknown error'));
            }

            if (!isset($data['txHash']) || !isset($data['tokenId'])) {
                throw new Exception("Invalid mint response: missing txHash or tokenId");
            }

            Log::info('Web3Client: Mint successful', [
                'tokenId' => $data['tokenId'],
                'txHash' => $data['txHash'],
            ]);

            return $data;
        } catch (Exception $e) {
            Log::error('Web3Client: Mint failed', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'metadataUri' => $metadataUri,
            ]);
            throw $e;
        }
    }

    /**
     * Get the owner of an NFT token.
     * 
     * @param string $tokenId Token ID to query
     * @return array Owner data with tokenId and owner address
     * @throws Exception If query fails
     */
    public function getOwner(string $tokenId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/ownerOf/{$tokenId}");

            if (!$response->successful()) {
                $errorMessage = $response->json()['error'] ?? 'Unknown error';
                throw new Exception("Owner query failed: {$errorMessage}");
            }

            $data = $response->json();

            if (!isset($data['tokenId']) || !isset($data['owner'])) {
                throw new Exception("Invalid owner response: missing tokenId or owner");
            }

            return $data;
        } catch (Exception $e) {
            Log::error('Web3Client: Owner query failed', [
                'error' => $e->getMessage(),
                'tokenId' => $tokenId,
            ]);
            throw $e;
        }
    }

    /**
     * Anchor a provenance hash to the blockchain.
     * 
     * @param string $sha256 SHA-256 hash to anchor
     * @return array Anchor response with txHash
     * @throws Exception If anchoring fails
     */
    public function anchorHash(string $sha256): array
    {
        try {
            $payload = ['sha256' => $sha256];

            Log::info('Web3Client: Anchoring hash', $payload);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/anchor", $payload);

            if (!$response->successful()) {
                $errorMessage = $response->json()['error'] ?? 'Unknown error';
                throw new Exception("Anchor failed: {$errorMessage}");
            }

            $data = $response->json();

            if (!isset($data['success']) || !$data['success']) {
                throw new Exception("Anchor unsuccessful: " . ($data['message'] ?? 'Unknown error'));
            }

            if (!isset($data['txHash'])) {
                throw new Exception("Invalid anchor response: missing txHash");
            }

            Log::info('Web3Client: Anchor successful', [
                'txHash' => $data['txHash'],
                'hash' => $sha256,
            ]);

            return $data;
        } catch (Exception $e) {
            Log::error('Web3Client: Anchor failed', [
                'error' => $e->getMessage(),
                'sha256' => $sha256,
            ]);
            throw $e;
        }
    }
}
