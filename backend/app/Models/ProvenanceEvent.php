<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProvenanceEvent model for supply chain tracking.
 * 
 * Relationships:
 * - product: Belongs to Product
 * 
 * @property-read \App\Models\Product $product
 */
class ProvenanceEvent extends Model
{
    /** @use HasFactory<\Database\Factories\ProvenanceEventFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'event_type',
        'data',
        'ipfs_hash',
        'tx_hash',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get the product for this provenance event.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get IPFS gateway URL for metadata.
     */
    public function getIpfsUrlAttribute(): ?string
    {
        if (!$this->ipfs_hash) {
            return null;
        }

        return "https://gateway.pinata.cloud/ipfs/{$this->ipfs_hash}";
    }

    /**
     * Get Polygonscan URL for blockchain transaction.
     */
    public function getPolygonscanUrlAttribute(): ?string
    {
        if (!$this->tx_hash) {
            return null;
        }

        return "https://mumbai.polygonscan.com/tx/{$this->tx_hash}";
    }
}
