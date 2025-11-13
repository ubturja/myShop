<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CartItem model for user shopping carts.
 * 
 * Relationships:
 * - user: Belongs to User
 * - product: Belongs to Product
 * - store: Belongs to Store (optional, for quick commerce items)
 */
class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'store_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the user who owns this cart item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product in this cart item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the store for quick commerce items.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get total price for this cart item.
     */
    public function getTotalPriceCentsAttribute(): int
    {
        return $this->product->price_cents * $this->quantity;
    }
}
