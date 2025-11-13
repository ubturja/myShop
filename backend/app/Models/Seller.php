<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Seller model representing marketplace vendors.
 * 
 * Relationships:
 * - user: Belongs to User
 * - products: One-to-many with Product
 * - orders: One-to-many with Order
 */
class Seller extends Model
{
    /** @use HasFactory<\Database\Factories\SellerFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_name',
        'verified',
        'rating',
        'description',
        'logo_url',
        'business_info',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'rating' => 'decimal:2',
        'business_info' => 'array',
    ];

    /**
     * Get the user that owns this seller account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all products listed by this seller.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get all orders for this seller's products.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
