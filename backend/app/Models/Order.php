<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Seller $seller
 * @property-read \App\Models\Store|null $store
 */
class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'seller_id',
        'store_id',
        'total_cents',
        'currency',
        'status',
        'fulfillment_type',
        'eta',
        'tx_hash',
        'payment_intent_id',
        'payment_status',
        'payment_method_details',
        'notes',
    ];

    protected $casts = [
        'total_cents' => 'integer',
        'eta' => 'integer',
        'payment_method_details' => 'array',
    ];

    /**
     * Get the customer who placed this order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the seller fulfilling this order.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Get the store fulfilling this order (for quick_local).
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the line items in this order.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }


    /**
     * Get total price in main currency unit (e.g., dollars).
     */
    public function getTotalAttribute(): float
    {
        return $this->total_cents / 100;
    }

    /**
     * Get formatted total with currency symbol.
     */
    public function getFormattedTotalAttribute(): string
    {
        $symbol = $this->currency === 'USD' ? '$' : $this->currency . ' ';
        return $symbol . number_format($this->total, 2);
    }
}
