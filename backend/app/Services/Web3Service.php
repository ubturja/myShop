<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Web3\Web3;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Contract;
use Web3\Utils;
use kornrunner\Keccak;
use Elliptic\EC;
use Illuminate\Support\Str;

class Web3Service
{
    protected $web3;
    protected $provider;
    protected $network;
    
    public function __construct($network = null)
    {
        $this->network = $network ?? config('web3.default_network');
        $networkConfig = config("web3.networks.{$this->network}");
        
        if (!$networkConfig) {
            throw new \RuntimeException("Unsupported network: {$this->network}");
        }
        
        $this->provider = new HttpProvider(
            new HttpRequestManager($networkConfig['rpc_url'], 10)
        );
        
        $this->web3 = new Web3($this->provider);
    }
    
    /**
     * Verify a signed message
     * 
     * @param string $message
     * @param string $signature
     * @param string $address
     * @return bool
     */
    public function verifySignature(string $message, string $signature, string $address): bool
    {
        try {
            // Hash the message with Ethereum prefix
            $messageHash = $this->hashMessage($message);
            
            // Recover the public key
            $recoveredAddress = $this->recoverAddress($messageHash, $signature);
            
            // Normalize addresses for comparison
            return $this->isAddressEqual($recoveredAddress, $address);
        } catch (\Exception $e) {
            Log::error('Web3 signature verification failed', [
                'error' => $e->getMessage(),
                'address' => $address,
                'network' => $this->network
            ]);
            return false;
        }
    }
    
    /**
     * Hash a message with Ethereum prefix
     */
    protected function hashMessage(string $message): string
    {
        $prefix = config('web3.message_prefix');
        $message = $prefix . strlen($message) . $message;
        return '0x' . Keccak::hash($message, 256);
    }
    
    /**
     * Recover the signer's address from a signature
     */
    protected function recoverAddress(string $messageHash, string $signature): string
    {
        // Remove '0x' from the signature if present
        $signature = str_replace('0x', '', $signature);
        
        // Split the signature into r, s, and v
        $r = '0x' . substr($signature, 0, 64);
        $s = '0x' . substr($signature, 64, 64);
        $v = hexdec(substr($signature, 128, 2));
        
        // Adjust v value for Ethereum signatures (27/28 -> 0/1)
        if ($v >= 27 && $v <= 28) {
            $v -= 27;
        }
        
        // Recover the public key
        $ec = new EC('secp256k1');
        $signature = [
            'r' => $r,
            's' => $s,
        ];
        
        $publicKey = $ec->recoverPubKey(
            $messageHash,
            $signature,
            $v,
            'hex'
        );
        
        // Derive the Ethereum address from the public key
        $publicKey = $publicKey->encode('hex');
        $publicKey = substr($publicKey, 2); // Remove '04' prefix
        
        $hash = Keccak::hash(hex2bin($publicKey), 256);
        $address = '0x' . substr($hash, -40);
        
        return $this->toChecksumAddress($address);
    }
    
    /**
     * Convert an address to checksum format (EIP-55)
     */
    public function toChecksumAddress(string $address): string
    {
        $address = strtolower($address);
        $address = str_replace('0x', '', $address);
        
        $hash = Keccak::hash($address);
        $result = '0x';
        
        for ($i = 0; $i < 40; $i++) {
            if (intval($hash[$i], 16) >= 8) {
                $result .= strtoupper($address[$i]);
            } else {
                $result .= $address[$i];
            }
        }
        
        return $result;
    }
    
    /**
     * Check if two addresses are equal (case-insensitive)
     */
    public function isAddressEqual(string $address1, string $address2): bool
    {
        return strtolower($address1) === strtolower($address2);
    }
    
    /**
     * Validate an Ethereum address
     */
    public function isAddressValid(string $address): bool
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            return false;
        }
        
        // Check if it's a checksum address
        if ($address === strtolower($address) || $address === strtoupper($address)) {
            return true;
        }
        
        // Verify checksum
        return $this->toChecksumAddress($address) === $address;
    }
    
    /**
     * Get the current gas price from the network
     */
    public function getGasPrice(): string
    {
        $gasPrice = '0';
        
        $this->web3->eth->gasPrice(function ($err, $result) use (&$gasPrice) {
            if ($err !== null) {
                throw new \RuntimeException("Failed to get gas price: " . $err->getMessage());
            }
            $gasPrice = $result->toString();
        });
        
        return $gasPrice;
    }
    
    /**
     * Get the current block number
     */
    public function getBlockNumber(): int
    {
        $blockNumber = 0;
        
        $this->web3->eth->blockNumber(function ($err, $result) use (&$blockNumber) {
            if ($err !== null) {
                throw new \RuntimeException("Failed to get block number: " . $err->getMessage());
            }
            $blockNumber = $result->toNumber();
        });
        
        return $blockNumber;
    }
    
    /**
     * Get the balance of an address in wei
     */
    public function getBalance(string $address): string
    {
        $balance = '0';
        
        $this->web3->eth->getBalance($address, function ($err, $result) use (&$balance) {
            if ($err !== null) {
                throw new \RuntimeException("Failed to get balance: " . $err->getMessage());
            }
            $balance = $result->toString();
        });
        
        return $balance;
    }
    
    /**
     * Convert wei to ether
     */
    public function fromWei(string $wei, int $decimals = 18): string
    {
        return bcdiv($wei, bcpow('10', $decimals), $decimals);
    }
    
    /**
     * Convert ether to wei
     */
    public function toWei(string $ether, int $decimals = 18): string
    {
        return bcmul($ether, bcpow('10', $decimals), 0);
    }
}
