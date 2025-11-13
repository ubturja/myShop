<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\User $starter
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupBuyMember> $groupBuyMembers
 * @property int $member_count
 * @property int $savings
 * @property int $progress_percentage
 */
class GroupBuy extends Model
{
    /** @use HasFactory<\Database\Factories\GroupBuyFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'starter_user_id',
        'target_size',
        'team_price_cents',
        'solo_price_cents',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'target_size' => 'integer',
        'team_price_cents' => 'integer',
        'solo_price_cents' => 'integer',
    ];

    /**
     * Get the product being sold in this group buy.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who started this group buy.
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'starter_user_id');
    }

    /**
     * Get the users who joined this group buy (many-to-many).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_buy_members')
            ->withTimestamps()
            ->withPivot('paid');
    }

    /**
     * Get the group buy member pivot records.
     */
    public function groupBuyMembers(): HasMany
    {
        return $this->hasMany(GroupBuyMember::class);
    }

    /**
     * Get current member count.
     */
    public function getMemberCountAttribute(): int
    {
        return $this->members()->count();
    }

    /**
     * Check if target size has been reached.
     */
    public function isTargetReached(): bool
    {
        return $this->member_count >= $this->target_size;
    }

    /**
     * Check if group buy has expired.
     */
    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    /**
     * Check if group buy is still active.
     */
    public function isActive(): bool
    {
        return $this->status === 'ACTIVE' && !$this->isExpired();
    }

    /**
     * Get current price (team or solo based on target reached).
     */
    public function getCurrentPriceCents(): int
    {
        return $this->isTargetReached() ? $this->team_price_cents : $this->solo_price_cents;
    }

    /**
     * Get savings amount if target is reached.
     */
    public function getSavingsAttribute(): int
    {
        return $this->solo_price_cents - $this->team_price_cents;
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentageAttribute(): int
    {
        return min(100, (int) (($this->member_count / $this->target_size) * 100));
    }
}
