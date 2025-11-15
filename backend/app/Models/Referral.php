<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Referral model for tracking user referrals.
 * 
 * Relationships:
 * - referrer: Belongs to User (who sent the referral)
 * - referredUser: Belongs to User (who accepted the referral)
 */
class Referral extends Model
{
    /** @use HasFactory<\Database\Factories\ReferralFactory> */
    use HasFactory;

    protected $fillable = [
        'referrer_user_id',
        'referred_email',
        'accepted',
        'referred_user_id',
    ];

    protected $casts = [
        'accepted' => 'boolean',
    ];

    /**
     * Get the user who sent this referral.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    /**
     * Get the user who accepted this referral.
     */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /**
     * Mark referral as accepted.
     */
    public function markAsAccepted(int $userId): bool
    {
        $this->accepted = true;
        $this->referred_user_id = $userId;
        return $this->save();
    }
}
