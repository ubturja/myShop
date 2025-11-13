<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrderItem model representing line items in an order.
 * 
 * Relationships:
 * - order: Belongs to Order
 * - product: Belongs to Product
 */
class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price_cents',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_cents' => 'integer',
    ];

    /**
     * Get the order for this item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product for this item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get total price for this order item.
     */
    public function getTotalPriceCentsAttribute(): int
    {
        return $this->price_cents * $this->quantity;
    }
}
