# QuickFulfillmentService Implementation Summary

## Overview
Enhanced QuickFulfillmentService with location-based inventory search, atomic reservation system with database transactions, and ETA calculation using Mapbox API with Haversine formula fallback.

## Files Created/Modified

### 1. Enhanced QuickFulfillmentService (`app/Services/QuickFulfillmentService.php`)
**New Methods Added**:

#### `findNearestStoreWithInventoryForProduct()`
- **Purpose**: Find nearest store with inventory for a single product
- **Parameters**:
  - `float $userLat`: User's latitude
  - `float $userLng`: User's longitude  
  - `int $productId`: Product ID to search for
  - `int $quantity`: Required quantity (default: 1)
- **Returns**: Array with store, ETA, distance, and method
- **Features**:
  - Filters stores within MAX_DELIVERY_RADIUS_KM (25 km)
  - Checks inventory availability at each store
  - Returns nearest store with sufficient stock
  - Calculates ETA using Mapbox or Haversine

#### `calculateETA()`
- **Purpose**: Calculate delivery ETA using Mapbox API with Haversine fallback
- **Parameters**:
  - `float $fromLat, $fromLng`: Origin coordinates
  - `float $toLat, $toLng`: Destination coordinates
- **Returns**: Array with ETA (minutes), method (mapbox/haversine), distance
- **Features**:
  - Tries Mapbox Directions API first (if available)
  - Falls back to Haversine formula if Mapbox fails
  - Adds 5 minutes preparation time to travel time
  - Handles API failures gracefully

#### `calculateETAMapbox()` (protected)
- **Purpose**: Calculate ETA using Mapbox Directions API
- **API Used**: `https://api.mapbox.com/directions/v5/mapbox/{profile}/{coordinates}`
- **Profile**: Configurable (driving, cycling, walking)
- **Returns**: Success status, ETA in minutes, distance in km
- **Features**:
  - Uses configured access token
  - Respects timeout setting
  - Returns route duration and distance
  - Error handling for API failures

#### `calculateETAHaversine()` (protected)
- **Purpose**: Calculate ETA using Haversine formula (fallback)
- **Formula**: Great-circle distance between two points on a sphere
- **Assumptions**:
  - Earth radius: 6,371 km
  - Average speed: 25 km/h
  - Preparation time: 5 minutes
- **Returns**: ETA in minutes, distance in km, method flag

#### `isMapboxAvailable()`
- **Purpose**: Check if Mapbox integration is available
- **Returns**: Boolean (true if not in mock mode and has access token)

**Existing Methods Enhanced**:
- All methods now use improved ETA calculation
- Consistent error handling and logging
- Transaction safety checks for reservations

### 2. Mapbox Configuration (`config/services.php`)
**New Configuration Section**:
```php
'mapbox' => [
    'access_token' => env('MAPBOX_ACCESS_TOKEN'),
    'mock_mode' => env('MAPBOX_MOCK_MODE', true),
    'timeout' => env('MAPBOX_TIMEOUT', 10),
    'profile' => env('MAPBOX_PROFILE', 'driving'), // driving, cycling, walking
],
```

### 3. CartItemFactory (`database/factories/CartItemFactory.php`)
**Purpose**: Factory for generating test cart items
**Features**:
- Default state with user, product, quantity
- `withStore()` modifier for quick items
- Used in all QuickFulfillmentService tests

### 4. Comprehensive Tests (`tests/Unit/QuickFulfillmentServiceTest.php`)
**Test Coverage** (20 tests, 49 assertions):

#### Store Finding Tests
- ✅ Finds nearest store for single product
- ✅ Returns null when no store has inventory
- ✅ Returns null when insufficient quantity
- ✅ Ignores stores beyond max radius (25 km)
- ✅ Ignores inactive stores
- ✅ Finds nearest store for cart items (multiple products)
- ✅ Returns null when no store has all products

#### Inventory Reservation Tests
- ✅ Reserves inventory within transaction
- ✅ Fails reservation when insufficient stock
- ✅ Throws exception when called outside transaction
- ✅ Reserves multiple products atomically
- ✅ Handles reserved inventory correctly

#### ETA Calculation Tests
- ✅ Calculates ETA using Haversine fallback
- ✅ Uses Mapbox when available
- ✅ Falls back to Haversine when Mapbox fails
- ✅ Checks if Mapbox is available
- ✅ Calculates ETA with zero distance

#### Fulfillment Checks
- ✅ Checks if store can fulfill order
- ✅ Returns false when store cannot fulfill
- ✅ Gets nearby stores sorted by distance

## Test Results

### New Tests
```
QuickFulfillmentServiceTest: 20 tests, 49 assertions ✅
```

### Full Test Suite
```
Total Tests:     215 tests
Passing:         214 tests (including all 20 new tests)
Failing:         1 test (pre-existing failure in CartTest)
Total Assertions: 594 assertions
Duration:        24.49s
```

## Technical Implementation

### 1. Distance Calculation

**Haversine Formula**:
```php
$earthRadius = 6371; // km
$a = sin($latDelta / 2)^2 + cos($latFrom) * cos($latTo) * sin($lonDelta / 2)^2
$c = 2 * atan2(sqrt($a), sqrt(1 - $a))
$distance = $earthRadius * $c
```

**ETA Calculation**:
```php
$travelTime = ($distance / 25) * 60; // 25 km/h average speed
$preparationTime = 5; // minutes
$totalEta = ceil($travelTime + $preparationTime);
```

### 2. Mapbox Integration

**API Endpoint**:
```
GET https://api.mapbox.com/directions/v5/mapbox/{profile}/{coordinates}
```

**Request Example**:
```
GET /directions/v5/mapbox/driving/-74.0060,40.7128;-73.9680,40.7489
?access_token=<token>
&geometries=geojson
&overview=simplified
```

**Response Structure**:
```json
{
  "routes": [{
    "duration": 600,    // seconds
    "distance": 5000    // meters
  }]
}
```

### 3. Database Transaction Safety

**Reservation Logic**:
```php
DB::beginTransaction();
try {
    // Get inventory with row-level locking
    $inventory = Inventory::where('product_id', $productId)
        ->where('store_id', $storeId)
        ->lockForUpdate()
        ->first();
    
    // Attempt reservation
    if ($inventory->reserve($quantity)) {
        DB::commit();
        return true;
    }
    
    DB::rollBack();
    return false;
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

**Safety Check**:
```php
if (!\DB::transactionLevel()) {
    throw new \RuntimeException(
        'reserveInventory must be called within a database transaction'
    );
}
```

## API Usage

### 1. Find Nearest Store for Product

```php
use App\Services\QuickFulfillmentService;

$service = new QuickFulfillmentService();

// Find store for single product
$result = $service->findNearestStoreWithInventoryForProduct(
    userLat: 40.7128,
    userLng: -74.0060,
    productId: 123,
    quantity: 2
);

if ($result['store']) {
    echo "Store: {$result['store']->name}";
    echo "ETA: {$result['eta']} minutes";
    echo "Distance: {$result['distance_km']} km";
    echo "Method: {$result['method']}"; // 'mapbox' or 'haversine'
}
```

### 2. Reserve Inventory (with Transaction)

```php
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    $cartItems = CartItem::where('user_id', $userId)->get();
    
    $success = $service->reserveInventory($store, $cartItems);
    
    if ($success) {
        // Create order
        $order = Order::create([...]);
        
        DB::commit();
    } else {
        DB::rollBack();
        // Handle insufficient inventory
    }
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

### 3. Calculate ETA

```php
// Get ETA between two points
$result = $service->calculateETA(
    fromLat: 40.7128,
    fromLng: -74.0060,
    toLat: 40.7489,
    toLng: -73.9680
);

echo "ETA: {$result['eta']} minutes";
echo "Distance: {$result['distance_km']} km";
echo "Method: {$result['method']}"; // 'mapbox' or 'haversine'
```

### 4. Check Store Fulfillment

```php
$cartItems = CartItem::where('user_id', $userId)->get();

if ($service->canFulfill($store, $cartItems)) {
    echo "Store can fulfill all items";
} else {
    echo "Store cannot fulfill order";
}
```

### 5. Get Nearby Stores

```php
$stores = $service->getNearbyStores(
    userLat: 40.7128,
    userLng: -74.0060
);

foreach ($stores as $storeData) {
    echo "{$storeData['store']->name}: ";
    echo "{$storeData['distance_km']} km, ";
    echo "ETA: {$storeData['eta_minutes']} min\n";
}
```

## Environment Configuration

Add to `.env`:
```bash
# Mapbox Configuration
MAPBOX_ACCESS_TOKEN=pk.eyJ1...  # Your Mapbox access token
MAPBOX_MOCK_MODE=false          # Set to false to use real API
MAPBOX_TIMEOUT=10               # API timeout in seconds
MAPBOX_PROFILE=driving          # driving, cycling, or walking
```

**Mock Mode** (Default):
- No API calls made
- Uses Haversine formula for all calculations
- Perfect for development and testing

**Production Mode**:
1. Get Mapbox access token from https://mapbox.com
2. Set `MAPBOX_ACCESS_TOKEN` in `.env`
3. Set `MAPBOX_MOCK_MODE=false`
4. Service automatically uses Mapbox API with Haversine fallback

## Performance Considerations

### Query Optimization
```php
// Uses indexes on store status and location
Store::where('is_active', true)->get();

// Eager loads relationships
$stores = Store::with(['inventories'])->get();

// Uses pessimistic locking for reservations
Inventory::lockForUpdate()->first();
```

### Distance Filtering
- Filters stores by distance before checking inventory
- MAX_DELIVERY_RADIUS_KM = 25 km
- Reduces unnecessary inventory checks

### Caching Opportunities (Future)
```php
// Cache active stores (5 minutes)
Cache::remember('active_stores', 300, function () {
    return Store::where('is_active', true)->get();
});

// Cache inventory availability (1 minute)
Cache::remember("inventory_{$productId}_{$storeId}", 60, function () {
    return Inventory::where('product_id', $productId)
        ->where('store_id', $storeId)
        ->first();
});
```

## Error Handling

### Mapbox API Failure
```php
try {
    $result = $this->calculateETAMapbox(...);
    if ($result['success']) {
        return ['eta' => $result['eta'], 'method' => 'mapbox'];
    }
} catch (\Exception $e) {
    Log::warning('Mapbox API failed, using Haversine fallback', [
        'error' => $e->getMessage(),
    ]);
}

// Automatic fallback to Haversine
return $this->calculateETAHaversine(...);
```

### Transaction Safety
```php
if (!\DB::transactionLevel()) {
    throw new \RuntimeException(
        'reserveInventory must be called within a database transaction'
    );
}
```

### Inventory Reservation Failure
```php
if (!$inventory->hasAvailable($quantity)) {
    return false; // Insufficient stock
}

if (!$inventory->reserve($quantity)) {
    return false; // Reservation failed
}
```

## Algorithm Details

### Store Selection Algorithm

1. **Get Active Stores**:
   ```sql
   SELECT * FROM stores WHERE is_active = true
   ```

2. **Filter by Distance**:
   ```php
   foreach ($stores as $store) {
       $distance = $store->distanceTo($userLat, $userLng);
       if ($distance > MAX_DELIVERY_RADIUS_KM) continue;
       // ...
   }
   ```

3. **Check Inventory**:
   ```php
   $inventory = Inventory::where('product_id', $productId)
       ->where('store_id', $store->id)
       ->first();
   
   if ($inventory && $inventory->hasAvailable($quantity)) {
       $eligibleStores->push(['store' => $store, 'distance' => $distance]);
   }
   ```

4. **Sort by Distance**:
   ```php
   $eligibleStores = $eligibleStores->sortBy('distance');
   ```

5. **Calculate ETA for Nearest**:
   ```php
   $nearest = $eligibleStores->first();
   $etaResult = $this->calculateETA(...);
   ```

### Inventory Reservation Algorithm

1. **Validate Transaction**:
   ```php
   if (!\DB::transactionLevel()) {
       throw new \RuntimeException('Must be in transaction');
   }
   ```

2. **Lock Row**:
   ```php
   $inventory = Inventory::lockForUpdate()->first();
   ```

3. **Check Availability**:
   ```php
   if (!$inventory->hasAvailable($quantity)) {
       return false;
   }
   ```

4. **Atomic Increment**:
   ```php
   return $inventory->increment('reserved', $quantity);
   ```

## Future Enhancements

### 1. Multi-Store Fulfillment
```php
public function findOptimalStoreDistribution(
    Collection $cartItems,
    float $userLat,
    float $userLng
): array {
    // Split cart across multiple stores to minimize ETA
    // Return: ['stores' => [...], 'total_eta' => 45]
}
```

### 2. Real-Time Inventory Updates
```php
// Use Redis for real-time inventory tracking
Cache::remember("inventory_available_{$storeId}_{$productId}", 5, function () {
    return Inventory::where(...)->first()->available;
});
```

### 3. Traffic-Aware ETA
```php
// Use Mapbox Traffic API
public function calculateETAWithTraffic(
    float $fromLat,
    float $fromLng,
    float $toLat,
    float $toLng,
    ?\DateTime $departureTime = null
): array {
    // Use 'traffic' profile with departure time
    // Returns ETA adjusted for traffic conditions
}
```

### 4. Store Capacity Management
```php
public function getStoreAvailabilityWindow(
    Store $store,
    \DateTime $requestedTime
): array {
    // Check if store can accept new orders at requested time
    // Returns: ['available' => true, 'next_slot' => '14:30']
}
```

### 5. Delivery Zone Management
```php
public function isWithinDeliveryZone(
    Store $store,
    float $lat,
    float $lng
): bool {
    // Use geofencing to check if location is in delivery zone
    // Support polygon delivery zones
}
```

## Summary

### Implemented Features
✅ Enhanced QuickFulfillmentService with single product search  
✅ Mapbox API integration with automatic fallback  
✅ Haversine formula for distance/ETA calculation  
✅ Database transaction safety for reservations  
✅ Pessimistic locking for inventory  
✅ Comprehensive error handling  
✅ 20 unit tests with 49 assertions (all passing)  
✅ CartItemFactory for test support  

### Key Metrics
- **Distance Calculation**: Haversine formula (Earth radius: 6,371 km)
- **ETA Estimation**: Distance / 25 km/h + 5 min preparation
- **Max Delivery Radius**: 25 km
- **Mapbox Timeout**: 10 seconds (configurable)
- **Test Coverage**: 20 tests, 49 assertions, 100% passing

### Integration Ready
✅ Service can be used directly in controllers  
✅ Mapbox integration toggleable via config  
✅ Transaction-safe inventory reservation  
✅ Graceful fallback for API failures  
✅ Comprehensive logging for debugging  

### Next Steps (Optional)
1. Add Mapbox access token for production
2. Implement traffic-aware ETA calculations
3. Add caching layer for store/inventory queries
4. Create delivery zone management
5. Build multi-store fulfillment optimizer
