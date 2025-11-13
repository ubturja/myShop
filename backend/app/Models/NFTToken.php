<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\User $user
 */
class NFTToken extends Model
{
    /** @use HasFactory<\Database\Factories\NFTTokenFactory> */
    use HasFactory;

    protected $table = 'nft_tokens';

    protected $fillable = [
        'user_id',
        'token_id',
        'contract_address',
        'metadata_uri',
        'tx_hash',
        'minted_at',
    ];

    protected $casts = [
        'minted_at' => 'datetime',
    ];

    /**
     * Get the user who owns this NFT.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get Polygonscan URL for this token's mint transaction.
     */
    public function getPolygonscanUrlAttribute(): ?string
    {
        if (!$this->tx_hash) {
            return null;
        }

        // Mumbai testnet
        return "https://mumbai.polygonscan.com/tx/{$this->tx_hash}";
    }
}
