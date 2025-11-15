<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * QuickFulfillmentService handles rapid delivery logistics.
 * 
 * Responsible for:
 * - Finding nearest stores with available inventory
 * - Reserving inventory atomically
 * - Calculating estimated delivery times (Mapbox or Haversine)
 * - Managing multi-store fulfillment optimization
 */
class QuickFulfillmentService
{
    /**
     * Maximum delivery radius in kilometers.
     * Stores beyond this distance won't be considered.
     */
    const MAX_DELIVERY_RADIUS_KM = 25;

    /**
     * Mapbox API configuration.
     */
    protected bool $mapboxMockMode;
    protected ?string $mapboxAccessToken;
    protected int $mapboxTimeout;
    protected string $mapboxProfile;

    public function __construct()
    {
        $this->mapboxMockMode = config('services.mapbox.mock_mode', true);
        $this->mapboxAccessToken = config('services.mapbox.access_token');
        $this->mapboxTimeout = config('services.mapbox.timeout', 10);
        $this->mapboxProfile = config('services.mapbox.profile', 'driving');
    }

    /**
     * Find the nearest store that has all requested products in stock.
     * 
     * Algorithm:
     * 1. Get all active stores within delivery radius
     * 2. For each store, check if it has sufficient inventory for ALL products
     * 3. Calculate distance to each qualifying store
     * 4. Return nearest store with full inventory availability
     * 
     * @param Collection $cartItems Collection of CartItem models
     * @param float $userLat User's latitude coordinate
     * @param float $userLng User's longitude coordinate
     * @return array{store: Store|null, eta: int|null, products_available: bool}
     */
    public function findNearestStoreWithInventory(
        Collection $cartItems,
        float $userLat,
        float $userLng
    ): array {
        // Get all active stores
        $stores = Store::where('is_active', true)->get();
        
        if ($stores->isEmpty()) {
            return [
                'store' => null,
                'eta' => null,
                'products_available' => false,
            ];
        }

        // Extract unique product IDs and their required quantities
        $requiredProducts = $cartItems->mapWithKeys(function ($item) {
            return [$item->product_id => $item->quantity];
        });

        $eligibleStores = collect();

        // Check each store for complete inventory availability
        foreach ($stores as $store) {
            // Calculate distance first to filter out too-far stores
            $distance = $store->distanceTo($userLat, $userLng);
            
            if ($distance > self::MAX_DELIVERY_RADIUS_KM) {
                continue; // Store too far, skip
            }

            // Check if this store has ALL required products in sufficient quantity
            $hasAllProducts = true;
            
            foreach ($requiredProducts as $productId => $requiredQty) {
                // Get inventory record for this product at this store
                $inventory = Inventory::where('product_id', $productId)
                    ->where('store_id', $store->id)
                    ->first();

                // If no inventory record or insufficient available quantity, skip this store
                if (!$inventory || !$inventory->hasAvailable($requiredQty)) {
                    $hasAllProducts = false;
                    break;
                }
            }

            // If store has all products, add to eligible list with distance
            if ($hasAllProducts) {
                $eligibleStores->push([
                    'store' => $store,
                    'distance' => $distance,
                    'eta' => $store->estimateDeliveryTime($userLat, $userLng),
                ]);
            }
        }

        // If no stores have complete inventory, return null
        if ($eligibleStores->isEmpty()) {
            return [
                'store' => null,
                'eta' => null,
                'products_available' => false,
            ];
        }

        // Sort by distance (nearest first)
        $eligibleStores = $eligibleStores->sortBy('distance');

        // Return the nearest store
        $nearest = $eligibleStores->first();
        
        return [
            'store' => $nearest['store'],
            'eta' => $nearest['eta'],
            'products_available' => true,
        ];
    }

    /**
     * Reserve inventory at a specific store for given cart items.
     * Must be called within a database transaction.
     * 
     * @param Store $store The store to reserve inventory from
     * @param Collection $cartItems Collection of CartItem models
     * @return bool True if all reservations succeeded, false otherwise
     * @throws \RuntimeException If called outside a transaction
     */
    public function reserveInventory(Store $store, Collection $cartItems): bool
    {
        // Verify we're in a transaction (safety check)
        if (!\DB::transactionLevel()) {
            throw new \RuntimeException(
                'reserveInventory must be called within a database transaction'
            );
        }

        foreach ($cartItems as $cartItem) {
            // Get inventory record with row-level locking to prevent race conditions
            $inventory = Inventory::where('product_id', $cartItem->product_id)
                ->where('store_id', $store->id)
                ->lockForUpdate() // Pessimistic locking
                ->first();

            if (!$inventory) {
                // Inventory record doesn't exist
                return false;
            }

            // Check if sufficient stock is available before reserving
            if (!$inventory->hasAvailable($cartItem->quantity)) {
                // Insufficient stock available
                return false;
            }

            // Reserve the required quantity
            $inventory->reserve($cartItem->quantity);
        }

        return true;
    }

    /**
     * Release reserved inventory (e.g., when order is cancelled).
     * Must be called within a database transaction.
     * 
     * @param Store $store The store to release inventory to
     * @param Collection $orderItems Collection of OrderItem models
     * @return bool True if all releases succeeded
     */
    public function releaseInventory(Store $store, Collection $orderItems): bool
    {
        if (!\DB::transactionLevel()) {
            throw new \RuntimeException(
                'releaseInventory must be called within a database transaction'
            );
        }

        foreach ($orderItems as $orderItem) {
            $inventory = Inventory::where('product_id', $orderItem->product_id)
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->first();

            if ($inventory) {
                $inventory->release($orderItem->quantity);
            }
        }

        return true;
    }

    /**
     * Check if a store can fulfill an entire order.
     * Non-locking check, suitable for UI/API responses.
     * 
     * @param Store $store
     * @param Collection $cartItems
     * @return bool
     */
    public function canFulfill(Store $store, Collection $cartItems): bool
    {
        foreach ($cartItems as $cartItem) {
            $inventory = Inventory::where('product_id', $cartItem->product_id)
                ->where('store_id', $store->id)
                ->first();

            if (!$inventory || !$inventory->hasAvailable($cartItem->quantity)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all stores within delivery radius sorted by distance.
     * Useful for "show all nearby stores" feature.
     * 
     * @param float $userLat
     * @param float $userLng
     * @return Collection Collection of stores with distance and ETA
     */
    public function getNearbyStores(float $userLat, float $userLng): Collection
    {
        $stores = Store::where('is_active', true)->get();

        return $stores->map(function ($store) use ($userLat, $userLng) {
            $distance = $store->distanceTo($userLat, $userLng);
            
            if ($distance > self::MAX_DELIVERY_RADIUS_KM) {
                return null;
            }

            return [
                'store' => $store,
                'distance_km' => round($distance, 2),
                'eta_minutes' => $store->estimateDeliveryTime($userLat, $userLng),
            ];
        })
        ->filter()
        ->sortBy('distance_km')
        ->values();
    }

    /**
     * Find nearest store with inventory for a single product.
     * Useful for quick "buy now" flows.
     * 
     * @param float $userLat User's latitude
     * @param float $userLng User's longitude
     * @param int $productId Product ID to search for
     * @param int $quantity Required quantity
     * @return array{store: Store|null, eta: int|null, distance_km: float|null, method: string}
     */
    public function findNearestStoreWithInventoryForProduct(
        float $userLat,
        float $userLng,
        int $productId,
        int $quantity = 1
    ): array {
        // Get all active stores
        $stores = Store::where('is_active', true)->get();
        
        if ($stores->isEmpty()) {
            return [
                'store' => null,
                'eta' => null,
                'distance_km' => null,
                'method' => 'none',
            ];
        }

        $eligibleStores = collect();

        // Check each store for inventory availability
        foreach ($stores as $store) {
            // Calculate distance first to filter out too-far stores
            $distance = $store->distanceTo($userLat, $userLng);
            
            if ($distance > self::MAX_DELIVERY_RADIUS_KM) {
                continue; // Store too far, skip
            }

            // Check if this store has the product in sufficient quantity
            $inventory = Inventory::where('product_id', $productId)
                ->where('store_id', $store->id)
                ->first();

            // If inventory exists and has sufficient quantity, add to eligible list
            if ($inventory && $inventory->hasAvailable($quantity)) {
                $eligibleStores->push([
                    'store' => $store,
                    'distance' => $distance,
                ]);
            }
        }

        // If no stores have inventory, return null
        if ($eligibleStores->isEmpty()) {
            return [
                'store' => null,
                'eta' => null,
                'distance_km' => null,
                'method' => 'none',
            ];
        }

        // Sort by distance (nearest first)
        $eligibleStores = $eligibleStores->sortBy('distance');

        // Get the nearest store
        $nearest = $eligibleStores->first();
        $store = $nearest['store'];
        $distance = $nearest['distance'];

        // Calculate ETA using Mapbox or Haversine fallback
        $etaResult = $this->calculateETA($userLat, $userLng, $store->latitude, $store->longitude);
        
        return [
            'store' => $store,
            'eta' => $etaResult['eta'],
            'distance_km' => round($distance, 2),
            'method' => $etaResult['method'],
        ];
    }

    /**
     * Calculate ETA using Mapbox API with Haversine fallback.
     * 
     * @param float $fromLat Origin latitude
     * @param float $fromLng Origin longitude
     * @param float $toLat Destination latitude
     * @param float $toLng Destination longitude
     * @return array{eta: int, method: string, distance_km: float|null}
     */
    public function calculateETA(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng
    ): array {
        // If mock mode or no API key, use Haversine fallback
        if ($this->mapboxMockMode || empty($this->mapboxAccessToken)) {
            return $this->calculateETAHaversine($fromLat, $fromLng, $toLat, $toLng);
        }

        try {
            // Try Mapbox Directions API
            $result = $this->calculateETAMapbox($fromLat, $fromLng, $toLat, $toLng);
            
            if ($result['success']) {
                return [
                    'eta' => $result['eta'],
                    'method' => 'mapbox',
                    'distance_km' => $result['distance_km'],
                ];
            }
        } catch (\Exception $e) {
            Log::warning('QuickFulfillmentService: Mapbox API failed, using Haversine fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback to Haversine
        return $this->calculateETAHaversine($fromLat, $fromLng, $toLat, $toLng);
    }

    /**
     * Calculate ETA using Mapbox Directions API.
     * Returns driving time + 5 minutes preparation time.
     * 
     * @param float $fromLat
     * @param float $fromLng
     * @param float $toLat
     * @param float $toLng
     * @return array{success: bool, eta: int|null, distance_km: float|null}
     */
    protected function calculateETAMapbox(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng
    ): array {
        $coordinates = "{$fromLng},{$fromLat};{$toLng},{$toLat}";
        $url = "https://api.mapbox.com/directions/v5/mapbox/{$this->mapboxProfile}/{$coordinates}";

        $response = Http::timeout($this->mapboxTimeout)
            ->get($url, [
                'access_token' => $this->mapboxAccessToken,
                'geometries' => 'geojson',
                'overview' => 'simplified',
            ]);

        if (!$response->successful()) {
            return ['success' => false, 'eta' => null, 'distance_km' => null];
        }

        $data = $response->json();

        if (empty($data['routes'])) {
            return ['success' => false, 'eta' => null, 'distance_km' => null];
        }

        $route = $data['routes'][0];
        $durationSeconds = $route['duration'] ?? 0;
        $distanceMeters = $route['distance'] ?? 0;

        // Convert to minutes and add 5 minutes preparation time
        $travelMinutes = ceil($durationSeconds / 60);
        $preparationMinutes = 5;
        $totalEta = $travelMinutes + $preparationMinutes;

        return [
            'success' => true,
            'eta' => (int) $totalEta,
            'distance_km' => round($distanceMeters / 1000, 2),
        ];
    }

    /**
     * Calculate ETA using Haversine formula (fallback).
     * Assumes 25 km/h average speed + 5 minutes preparation.
     * 
     * @param float $fromLat
     * @param float $fromLng
     * @param float $toLat
     * @param float $toLng
     * @return array{eta: int, method: string, distance_km: float}
     */
    protected function calculateETAHaversine(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng
    ): array {
        $earthRadius = 6371; // km

        $latFrom = deg2rad($fromLat);
        $lonFrom = deg2rad($fromLng);
        $latTo = deg2rad($toLat);
        $lonTo = deg2rad($toLng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        // Estimate travel time: assume 25 km/h average speed
        $travelTime = ($distance / 25) * 60; // minutes
        $preparationTime = 5; // minutes
        $totalEta = (int) ceil($travelTime + $preparationTime);

        return [
            'eta' => $totalEta,
            'method' => 'haversine',
            'distance_km' => round($distance, 2),
        ];
    }

    /**
     * Check if Mapbox integration is available.
     * 
     * @return bool
     */
    public function isMapboxAvailable(): bool
    {
        return !$this->mapboxMockMode && !empty($this->mapboxAccessToken);
    }
}
