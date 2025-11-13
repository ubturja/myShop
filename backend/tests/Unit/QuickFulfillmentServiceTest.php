<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Store;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\CartItem;
use App\Models\User;
use App\Services\QuickFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class QuickFulfillmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected QuickFulfillmentService $service;
    protected Store $store1;
    protected Store $store2;
    protected Product $product;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuickFulfillmentService();

        // Create test stores at different distances from user (40.7128, -74.0060 - NYC)
        $this->store1 = Store::factory()->create([
            'name' => 'Downtown Store',
            'latitude' => 40.7128, // ~0 km from user
            'longitude' => -74.0060,
            'is_active' => true,
        ]);

        $this->store2 = Store::factory()->create([
            'name' => 'Uptown Store',
            'latitude' => 40.7489, // ~4 km from user
            'longitude' => -73.9680,
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'title' => 'Test Product',
            'price_cents' => 1999,
        ]);

        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_finds_nearest_store_for_single_product()
    {
        // Add inventory to both stores
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store2->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $result = $this->service->findNearestStoreWithInventoryForProduct(
            40.7128,
            -74.0060,
            $this->product->id,
            2
        );

        $this->assertNotNull($result['store']);
        $this->assertEquals($this->store1->id, $result['store']->id);
        $this->assertNotNull($result['eta']);
        $this->assertGreaterThan(0, $result['eta']);
        $this->assertIsFloat($result['distance_km']);
        $this->assertContains($result['method'], ['mapbox', 'haversine']);
    }

    /** @test */
    public function it_returns_null_when_no_store_has_inventory()
    {
        // No inventory records created

        $result = $this->service->findNearestStoreWithInventoryForProduct(
            40.7128,
            -74.0060,
            $this->product->id,
            1
        );

        $this->assertNull($result['store']);
        $this->assertNull($result['eta']);
        $this->assertNull($result['distance_km']);
        $this->assertEquals('none', $result['method']);
    }

    /** @test */
    public function it_returns_null_when_insufficient_quantity()
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 5,
            'reserved' => 3, // Only 2 available
        ]);

        $result = $this->service->findNearestStoreWithInventoryForProduct(
            40.7128,
            -74.0060,
            $this->product->id,
            3 // Need 3, but only 2 available
        );

        $this->assertNull($result['store']);
    }

    /** @test */
    public function it_ignores_stores_beyond_max_radius()
    {
        // Create a far store (more than 25 km away)
        $farStore = Store::factory()->create([
            'latitude' => 40.9000, // ~20+ km away
            'longitude' => -73.7000,
            'is_active' => true,
        ]);

        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $farStore->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $result = $this->service->findNearestStoreWithInventoryForProduct(
            40.7128,
            -74.0060,
            $this->product->id,
            1
        );

        $this->assertNull($result['store']);
    }

    /** @test */
    public function it_ignores_inactive_stores()
    {
        $inactiveStore = Store::factory()->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'is_active' => false,
        ]);

        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $inactiveStore->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $result = $this->service->findNearestStoreWithInventoryForProduct(
            40.7128,
            -74.0060,
            $this->product->id,
            1
        );

        $this->assertNull($result['store']);
    }

    /** @test */
    public function it_reserves_inventory_within_transaction()
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $cartItem = CartItem::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);

        DB::beginTransaction();

        $result = $this->service->reserveInventory(
            $this->store1,
            collect([$cartItem])
        );

        $this->assertTrue($result);

        // Check that inventory was reserved
        $inventory = Inventory::where('product_id', $this->product->id)
            ->where('store_id', $this->store1->id)
            ->first();

        $this->assertEquals(3, $inventory->reserved);

        DB::rollBack();
    }

    /** @test */
    public function it_fails_reservation_when_insufficient_stock()
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 10,
            'reserved' => 8, // Only 2 available
        ]);

        $cartItem = CartItem::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 5, // Requesting more than available
        ]);

        DB::beginTransaction();

        $result = $this->service->reserveInventory(
            $this->store1,
            collect([$cartItem])
        );

        $this->assertFalse($result);

        DB::rollBack();
    }

    /** @test */
    public function it_throws_exception_when_reserve_called_outside_transaction()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be called within a database transaction');

        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $cartItem = CartItem::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        // Ensure we're NOT in a transaction by rolling back any existing ones
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        // This should throw exception
        $this->service->reserveInventory($this->store1, collect([$cartItem]));
    }

    /** @test */
    public function it_calculates_eta_using_haversine_fallback()
    {
        $result = $this->service->calculateETA(
            40.7128, -74.0060, // From: NYC
            40.7489, -73.9680  // To: ~4 km away
        );

        $this->assertIsInt($result['eta']);
        $this->assertGreaterThan(0, $result['eta']);
        $this->assertEquals('haversine', $result['method']);
        $this->assertIsFloat($result['distance_km']);
        $this->assertGreaterThan(0, $result['distance_km']);
    }

    /** @test */
    public function it_uses_mapbox_when_available()
    {
        // Mock Mapbox API response
        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'routes' => [
                    [
                        'duration' => 600, // 10 minutes
                        'distance' => 5000, // 5 km
                    ],
                ],
            ], 200),
        ]);

        // Temporarily enable Mapbox
        config(['services.mapbox.mock_mode' => false]);
        config(['services.mapbox.access_token' => 'test-token']);

        $service = new QuickFulfillmentService();

        $result = $service->calculateETA(
            40.7128, -74.0060,
            40.7489, -73.9680
        );

        $this->assertEquals('mapbox', $result['method']);
        $this->assertEquals(15, $result['eta']); // 10 min travel + 5 min prep
        $this->assertEquals(5.0, $result['distance_km']);
    }

    /** @test */
    public function it_falls_back_to_haversine_when_mapbox_fails()
    {
        // Mock failed Mapbox API response
        Http::fake([
            'api.mapbox.com/*' => Http::response([], 500),
        ]);

        config(['services.mapbox.mock_mode' => false]);
        config(['services.mapbox.access_token' => 'test-token']);

        $service = new QuickFulfillmentService();

        $result = $service->calculateETA(
            40.7128, -74.0060,
            40.7489, -73.9680
        );

        $this->assertEquals('haversine', $result['method']);
        $this->assertGreaterThan(0, $result['eta']);
    }

    /** @test */
    public function it_checks_if_mapbox_is_available()
    {
        // Default mock mode
        $this->assertFalse($this->service->isMapboxAvailable());

        // Enable Mapbox
        config(['services.mapbox.mock_mode' => false]);
        config(['services.mapbox.access_token' => 'test-token']);

        $service = new QuickFulfillmentService();
        $this->assertTrue($service->isMapboxAvailable());
    }

    /** @test */
    public function it_finds_nearest_store_for_cart_items()
    {
        $product2 = Product::factory()->create();

        // Store1 has both products
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        Inventory::factory()->create([
            'product_id' => $product2->id,
            'store_id' => $this->store1->id,
            'quantity' => 5,
            'reserved' => 0,
        ]);

        // Store2 only has product1
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store2->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $cartItems = collect([
            CartItem::factory()->make(['product_id' => $this->product->id, 'quantity' => 2]),
            CartItem::factory()->make(['product_id' => $product2->id, 'quantity' => 1]),
        ]);

        $result = $this->service->findNearestStoreWithInventory(
            $cartItems,
            40.7128,
            -74.0060
        );

        $this->assertNotNull($result['store']);
        $this->assertEquals($this->store1->id, $result['store']->id);
        $this->assertTrue($result['products_available']);
    }

    /** @test */
    public function it_returns_null_when_no_store_has_all_products()
    {
        $product2 = Product::factory()->create();

        // Store1 has product1
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        // Store2 has product2
        Inventory::factory()->create([
            'product_id' => $product2->id,
            'store_id' => $this->store2->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $cartItems = collect([
            CartItem::factory()->make(['product_id' => $this->product->id, 'quantity' => 2]),
            CartItem::factory()->make(['product_id' => $product2->id, 'quantity' => 1]),
        ]);

        $result = $this->service->findNearestStoreWithInventory(
            $cartItems,
            40.7128,
            -74.0060
        );

        $this->assertNull($result['store']);
        $this->assertFalse($result['products_available']);
    }

    /** @test */
    public function it_checks_if_store_can_fulfill_order()
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $cartItem = CartItem::factory()->make([
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $result = $this->service->canFulfill($this->store1, collect([$cartItem]));

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_when_store_cannot_fulfill()
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 3,
            'reserved' => 0,
        ]);

        $cartItem = CartItem::factory()->make([
            'product_id' => $this->product->id,
            'quantity' => 5, // More than available
        ]);

        $result = $this->service->canFulfill($this->store1, collect([$cartItem]));

        $this->assertFalse($result);
    }

    /** @test */
    public function it_gets_nearby_stores()
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $stores = $this->service->getNearbyStores(40.7128, -74.0060);

        $this->assertGreaterThan(0, $stores->count());
        $this->assertEquals($this->store1->id, $stores->first()['store']->id);
        $this->assertArrayHasKey('distance_km', $stores->first());
        $this->assertArrayHasKey('eta_minutes', $stores->first());
    }

    /** @test */
    public function it_reserves_multiple_products_atomically()
    {
        $product2 = Product::factory()->create();

        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        Inventory::factory()->create([
            'product_id' => $product2->id,
            'store_id' => $this->store1->id,
            'quantity' => 5,
            'reserved' => 0,
        ]);

        $cartItems = collect([
            CartItem::factory()->create([
                'user_id' => $this->user->id,
                'product_id' => $this->product->id,
                'quantity' => 3,
            ]),
            CartItem::factory()->create([
                'user_id' => $this->user->id,
                'product_id' => $product2->id,
                'quantity' => 2,
            ]),
        ]);

        DB::beginTransaction();

        $result = $this->service->reserveInventory($this->store1, $cartItems);

        $this->assertTrue($result);

        // Verify both products were reserved
        $inventory1 = Inventory::where('product_id', $this->product->id)
            ->where('store_id', $this->store1->id)
            ->first();
        $this->assertEquals(3, $inventory1->reserved);

        $inventory2 = Inventory::where('product_id', $product2->id)
            ->where('store_id', $this->store1->id)
            ->first();
        $this->assertEquals(2, $inventory2->reserved);

        DB::rollBack();
    }

    /** @test */
    public function it_calculates_eta_with_zero_distance()
    {
        $result = $this->service->calculateETA(
            40.7128, -74.0060,
            40.7128, -74.0060 // Same location
        );

        $this->assertEquals('haversine', $result['method']);
        $this->assertEquals(5, $result['eta']); // Just preparation time
        $this->assertEquals(0.0, $result['distance_km']);
    }

    /** @test */
    public function it_handles_reserved_inventory_correctly()
    {
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store1->id,
            'quantity' => 10,
            'reserved' => 7, // Only 3 available
        ]);

        $result = $this->service->findNearestStoreWithInventoryForProduct(
            40.7128,
            -74.0060,
            $this->product->id,
            3 // Exactly the available amount
        );

        $this->assertNotNull($result['store']);
        $this->assertEquals($this->store1->id, $result['store']->id);
    }
}
