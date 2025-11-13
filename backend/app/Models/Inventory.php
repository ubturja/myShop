<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'store_id',
        'quantity',
        'reserved',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved' => 'integer',
    ];

    /**
     * Get the product this inventory tracks.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the store where this inventory is located.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the available (unreserved) quantity.
     */
    public function getAvailableAttribute(): int
    {
        return max(0, $this->quantity - $this->reserved);
    }

    /**
     * Check if there's sufficient available stock.
     */
    public function hasStock(int $requestedQuantity): bool
    {
        return $this->available >= $requestedQuantity;
    }

    /**
     * Alias for hasStock() - check if requested quantity is available.
     */
    public function hasAvailable(int $requestedQuantity): bool
    {
        return $this->hasStock($requestedQuantity);
    }

    /**
     * Reserve inventory (increment reserved count).
     * Returns number of rows affected (should be 1 on success).
     */
    public function reserve(int $quantity): int
    {
        return $this->increment('reserved', $quantity);
    }

    /**
     * Release reserved inventory (decrement reserved count).
     * Returns number of rows affected (should be 1 on success).
     */
    public function release(int $quantity): int
    {
        return $this->decrement('reserved', min($quantity, $this->reserved));
    }
}