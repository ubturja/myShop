<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product model representing marketplace items.
 * 
 * Relationships:
 * - seller: Belongs to Seller
 * - inventories: One-to-many with Inventory (for quick commerce)
 * - cartItems: One-to-many with CartItem
 * - orderItems: One-to-many with OrderItem
 * - groupBuys: One-to-many with GroupBuy
 * - provenanceEvents: One-to-many with ProvenanceEvent
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'title',
        'description',
        'price_cents',
        'currency',
        'sku',
        'image_url',
        'category',
        'tags',
        'is_quick_item',
        'status',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'tags' => 'array',
        'is_quick_item' => 'boolean',
    ];

    /**
     * Get the seller who owns this product.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Get all inventory records for this product (across stores).
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Get all cart items containing this product.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get all order items containing this product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all group buys for this product.
     */
    public function groupBuys(): HasMany
    {
        return $this->hasMany(GroupBuy::class);
    }

    /**
     * Get all provenance events for this product.
     */
    public function provenanceEvents(): HasMany
    {
        return $this->hasMany(ProvenanceEvent::class);
    }

    /**
     * Get price in dollars (or main currency unit).
     */
    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    /**
     * Get formatted price with currency symbol.
     */
    public function getFormattedPriceAttribute(): string
    {
        $symbol = $this->currency === 'USD' ? '$' : $this->currency . ' ';
        return $symbol . number_format($this->price, 2);
    }

    /**
     * Generate embedding text for AI vector search.
     * Format: "{title} | {category} | {description} | {tags comma-separated}"
     */
    public function getEmbeddingTextAttribute(): string
    {
        $tagsString = is_array($this->tags) ? implode(', ', $this->tags) : '';
        return implode(' | ', [
            $this->title,
            $this->category,
            $this->description,
            $tagsString,
        ]);
    }
}
