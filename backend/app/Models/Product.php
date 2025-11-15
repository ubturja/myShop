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
     * Attributes to append to JSON serialization.
     */
    protected $appends = [
        'price',
        'formatted_price',
        // 'average_rating', // Disabled: reviews table not implemented yet
    ];

    /**
     * Ensure tags are always returned as an array in JSON responses.
     * This prevents double-encoding issues.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Override toArray to ensure tags are properly formatted.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // Ensure tags is always an array
        if (isset($array['tags'])) {
            if (is_string($array['tags'])) {
                $array['tags'] = json_decode($array['tags'], true) ?? [];
            } elseif (!is_array($array['tags'])) {
                $array['tags'] = [];
            }
        } else {
            $array['tags'] = [];
        }
        
        return $array;
    }

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
     * Get all reviews for this product.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get all wishlist entries for this product.
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
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
     * Get image URL with fallback to placeholder.
     * Ensures all products have a valid image URL.
     */
    public function getImageUrlAttribute($value): string
    {
        // If no image URL is set, return placeholder
        if (!$value || empty(trim($value))) {
            return url('/images/product-placeholder.png');
        }
        
        // If URL is already absolute, return as-is
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        
        // Otherwise, make it absolute
        return url($value);
    }

    /**
     * Get average rating from reviews.
     */
    public function getAverageRatingAttribute(): ?float
    {
        return $this->reviews()->where('is_approved', true)->avg('rating');
    }

    /**
     * Get total number of reviews.
     */
    public function getReviewCountAttribute(): int
    {
        return $this->reviews()->where('is_approved', true)->count();
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
