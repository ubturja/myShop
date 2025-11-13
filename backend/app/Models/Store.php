<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Store model for quick commerce micro-fulfillment centers.
 * 
 * Relationships:
 * - inventories: One-to-many with Inventory
 * - orders: One-to-many with Order (quick commerce orders)
 */
class Store extends Model
{
    /** @use HasFactory<\Database\Factories\StoreFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'address',
        'city',
        'postal_code',
        'opening_hours',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'opening_hours' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get all inventory items at this store.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Get all orders fulfilled by this store.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Calculate distance to given coordinates using Haversine formula.
     * Returns distance in kilometers.
     */
    public function distanceTo(float $lat, float $lng): float
    {
        $earthRadius = 6371; // km

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Estimate delivery time in minutes based on distance.
     * Uses simple formula: distance_km / 25 km/h * 60 + 5 min base time.
     */
    public function estimateDeliveryTime(float $lat, float $lng): int
    {
        $distance = $this->distanceTo($lat, $lng);
        $travelTime = ($distance / 25) * 60; // Assume 25 km/h average speed
        $baseTime = 5; // 5 minutes for preparation
        
        return (int) ceil($travelTime + $baseTime);
    }
}
