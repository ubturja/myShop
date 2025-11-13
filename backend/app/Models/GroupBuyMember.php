<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * GroupBuyMember model (pivot table with additional data).
 * 
 * Relationships:
 * - groupBuy: Belongs to GroupBuy
 * - user: Belongs to User
 */
class GroupBuyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_buy_id',
        'user_id',
        'joined_at',
        'paid',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'paid' => 'boolean',
    ];

    /**
     * Get the group buy for this membership.
     */
    public function groupBuy()
    {
        return $this->belongsTo(GroupBuy::class);
    }

    /**
     * Get the user for this membership.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
