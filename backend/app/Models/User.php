<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User model with multi-role support (ADMIN, CUSTOMER, SELLER).
 * 
 * Relationships:
 * - seller: One-to-one with Seller (if role is SELLER)
 * - cartItems: One-to-many with CartItem
 * - orders: One-to-many with Order
 * - nftTokens: One-to-many with NFTToken
 * - gamificationEvents: One-to-many with GamificationEvent
 * - referrals: One-to-many with Referral (as referrer)
 * 
 * @property-read \App\Models\Seller|null $seller
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'profile',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profile' => 'array', // Cast JSON to array
        ];
    }

    /**
     * Get the seller profile if user is a seller.
     */
    public function seller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    /**
     * Get all cart items for this user.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get all orders placed by this user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get all NFT tokens owned by this user.
     */
    public function nftTokens(): HasMany
    {
        return $this->hasMany(NFTToken::class);
    }

    /**
     * Get all gamification events for this user.
     */
    public function gamificationEvents(): HasMany
    {
        return $this->hasMany(GamificationEvent::class);
    }

    /**
     * Get all referrals made by this user.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    /**
     * Get all group buys started by this user.
     */
    public function startedGroupBuys(): HasMany
    {
        return $this->hasMany(GroupBuy::class, 'starter_user_id');
    }

    /**
     * Get all group buys this user has joined.
     */
    public function joinedGroupBuys(): BelongsToMany
    {
        return $this->belongsToMany(GroupBuy::class, 'group_buy_members')
            ->withPivot('joined_at', 'paid')
            ->withTimestamps();
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('ADMIN');
    }

    /**
     * Check if user is a seller.
     */
    public function isSeller(): bool
    {
        return $this->hasRole('SELLER');
    }

    /**
     * Check if user is a customer.
     */
    public function isCustomer(): bool
    {
        return $this->hasRole('CUSTOMER');
    }
}
