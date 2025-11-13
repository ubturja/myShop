<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationEvent extends Model
{
    /** @use HasFactory<\Database\Factories\GamificationEventFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'reward',
        'used',
    ];

    protected $casts = [
        'reward' => 'array',
        'used' => 'boolean',
    ];

    /**
     * Get the user who earned this reward.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark reward as used.
     */
    public function markAsUsed(): bool
    {
        $this->used = true;
        $this->used_at = now();
        return $this->save();
    }
}
