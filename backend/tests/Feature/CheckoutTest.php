<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Seller;
use App\Models\CartItem;
use App\Models\Store;
use App\Models\Inventory;
use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Feature tests for Checkout functionality.
 * 
 * Tests:
 * - Quick local checkout with inventory reservation
 * - Standard checkout with multiple sellers
 * - Group buy checkout
 * - Empty cart validation
 * - Inventory availability checks
 * - Cart clearing after successful checkout
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Seller $seller;
    protected Product $quickProduct;
    protected Product $standardProduct;
    protected Store $nearStore;
    protected Store $farStore;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create seller
        $sellerUser = User::factory()->seller()->create();
        $this->seller = Seller::factory()->verified()->create([
            'user_id' => $sellerUser->id,
        ]);

        // Create test user
        $this->user = User::factory()->create();

        // Create stores at different locations
        $this->nearStore = Store::factory()->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'is_active' => true,
        ]);

        $this->farStore = Store::factory()->create([
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'is_active' => true,
        ]);

        // Create quick commerce product with inventory
        $this->quickProduct = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'price_cents' => 999, // $9.99
            'is_quick_item' => true,
            'status' => 'ACTIVE', // ENUM values are uppercase
        ]);

        Inventory::factory()->create([
            'product_id' => $this->quickProduct->id,
            'store_id' => $this->nearStore->id,
            'quantity' => 50,
            'reserved' => 0,
        ]);

        // Create standard product
        $this->standardProduct = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'price_cents' => 2999, // $29.99
            'is_quick_item' => false,
            'status' => 'ACTIVE', // ENUM values are uppercase
        ]);
    }

    /**
     * Test successful quick local checkout.
     */
    public function test_quick_local_checkout_success(): void
    {
        // Add quick item to cart
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->quickProduct->id,
            'quantity' => 3,
            'store_id' => $this->nearStore->id,
        ]);

        // Checkout (use 'sanctum' guard for API authentication)
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'quick_local',
                'latitude' => 40.7128, // Near the nearStore
                'longitude' => -74.0060,
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'orders' => [
                    '*' => [
                        'id',
                        'total_cents',
                        'status',
                        'fulfillment_type',
                        'eta_minutes',
                        'store',
                        'items',
                    ],
                ],
                'total_cents',
                'payment',
            ])
            ->assertJson([
                'orders' => [
                    [
                        'total_cents' => 2997, // $9.99 * 3
                        'fulfillment_type' => 'quick_local',
                    ],
                ],
            ]);

        // Verify order created
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'store_id' => $this->nearStore->id,
            'total_cents' => 2997,
            'fulfillment_type' => 'quick_local',
            'status' => 'pending',
        ]);

        // Verify order items created
        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->quickProduct->id,
            'quantity' => 3,
        ]);

        // Verify inventory reserved
        $inventory = Inventory::where('product_id', $this->quickProduct->id)
            ->where('store_id', $this->nearStore->id)
            ->first();
        $this->assertEquals(3, $inventory->reserved);

        // Verify cart cleared
        $this->assertEquals(0, CartItem::where('user_id', $this->user->id)->count());
    }

    /**
     * Test quick local checkout fails when no stores have inventory.
     */
    public function test_quick_local_checkout_fails_without_inventory(): void
    {
        // Set inventory to zero
        Inventory::where('product_id', $this->quickProduct->id)->update(['quantity' => 0]);

        // Add to cart
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->quickProduct->id,
            'quantity' => 1,
            'store_id' => $this->nearStore->id,
        ]);

        // Attempt checkout
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'quick_local',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Checkout failed',
            ]);

        // Verify no order created
        $this->assertEquals(0, Order::where('user_id', $this->user->id)->count());
    }

    /**
     * Test quick local checkout fails with non-quick items.
     */
    public function test_quick_local_checkout_rejects_standard_items(): void
    {
        // Add standard item to cart
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 1,
        ]);

        // Attempt quick local checkout
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'quick_local',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(500)
            ->assertJsonFragment([
                'error' => 'Checkout failed',
            ]);
    }

    /**
     * Test standard checkout creates order successfully.
     */
    public function test_standard_checkout_success(): void
    {
        // Add standard item to cart
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 2,
        ]);

        // Checkout
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'standard',
                'shipping_address' => [
                    'line1' => '123 Main St',
                    'city' => 'New York',
                    'postal_code' => '10001',
                    'country' => 'US',
                ],
                'payment_method' => 'credit_card',
                'notes' => 'Please ring doorbell',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'orders' => [
                    [
                        'total_cents' => 5998, // $29.99 * 2
                        'fulfillment_type' => 'standard',
                    ],
                ],
            ]);

        // Verify order created
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'seller_id' => $this->seller->id,
            'total_cents' => 5998,
            'fulfillment_type' => 'standard',
        ]);

        // Verify cart cleared
        $this->assertEquals(0, CartItem::where('user_id', $this->user->id)->count());
    }

    /**
     * Test standard checkout creates separate orders for multiple sellers.
     */
    public function test_standard_checkout_splits_orders_by_seller(): void
    {
        // Create second seller
        $seller2User = User::factory()->seller()->create();
        $seller2 = Seller::factory()->verified()->create([
            'user_id' => $seller2User->id,
        ]);

        $product2 = Product::factory()->create([
            'seller_id' => $seller2->id,
            'price_cents' => 1999,
            'is_quick_item' => false,
        ]);

        // Add items from different sellers
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 1,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 1,
        ]);

        // Checkout
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'standard',
                'shipping_address' => [
                    'line1' => '123 Main St',
                    'city' => 'New York',
                    'postal_code' => '10001',
                    'country' => 'US',
                ],
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(201);

        // Verify two separate orders created
        $orders = Order::where('user_id', $this->user->id)->get();
        $this->assertEquals(2, $orders->count());

        // Verify orders are for different sellers
        $sellerIds = $orders->pluck('seller_id')->toArray();
        $this->assertContains($this->seller->id, $sellerIds);
        $this->assertContains($seller2->id, $sellerIds);
    }

    /**
     * Test group buy checkout success.
     */
    public function test_group_buy_checkout_success(): void
    {
        // Create fulfilled group buy
        $groupBuy = GroupBuy::factory()->fulfilled()->create([
            'product_id' => $this->standardProduct->id,
            'starter_user_id' => $this->user->id,
            'target_size' => 5,
            'team_price_cents' => 2499, // Discounted from $29.99
            'solo_price_cents' => 2999,
        ]);

        // Add user as member
        GroupBuyMember::create([
            'group_buy_id' => $groupBuy->id,
            'user_id' => $this->user->id,
            'joined_at' => now(),
            'paid' => false,
        ]);

        // Add product to cart
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 1,
        ]);

        // Checkout
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'group_buy',
                'group_buy_id' => $groupBuy->id,
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'orders' => [
                    [
                        'total_cents' => 2499, // Team price
                        'fulfillment_type' => 'group_buy',
                    ],
                ],
            ]);

        // Verify order created with team price
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'total_cents' => 2499,
            'fulfillment_type' => 'group_buy',
        ]);
    }

    /**
     * Test checkout fails with empty cart.
     */
    public function test_checkout_fails_with_empty_cart(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'standard',
                'shipping_address' => [
                    'line1' => '123 Main St',
                    'city' => 'New York',
                    'postal_code' => '10001',
                    'country' => 'US',
                ],
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Cart is empty',
            ]);
    }

    /**
     * Test checkout validates fulfillment type.
     */
    public function test_checkout_validates_fulfillment_type(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 1,
        ]);

        // Invalid fulfillment type
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'invalid_type',
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fulfillment_type']);
    }

    /**
     * Test quick local checkout requires coordinates.
     */
    public function test_quick_local_requires_coordinates(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->quickProduct->id,
            'quantity' => 1,
            'store_id' => $this->nearStore->id,
        ]);

        // Missing latitude and longitude
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'quick_local',
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    /**
     * Test standard checkout requires shipping address.
     */
    public function test_standard_checkout_requires_shipping_address(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 1,
        ]);

        // Missing shipping_address
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'standard',
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_address']);
    }

    /**
     * Test unauthenticated users cannot checkout.
     */
    public function test_unauthenticated_user_cannot_checkout(): void
    {
        $response = $this->postJson('/api/checkout', [
            'fulfillment_type' => 'standard',
            'payment_method' => 'credit_card',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test inventory reservation is atomic (concurrent requests).
     * This tests race condition handling.
     */
    public function test_inventory_reservation_handles_race_conditions(): void
    {
        // Set limited inventory
        Inventory::where('product_id', $this->quickProduct->id)
            ->where('store_id', $this->nearStore->id)
            ->update(['quantity' => 5, 'reserved' => 0]);

        // Create two users with items in cart
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        CartItem::create([
            'user_id' => $user1->id,
            'product_id' => $this->quickProduct->id,
            'quantity' => 3,
            'store_id' => $this->nearStore->id,
        ]);

        CartItem::create([
            'user_id' => $user2->id,
            'product_id' => $this->quickProduct->id,
            'quantity' => 3,
            'store_id' => $this->nearStore->id,
        ]);

        // First checkout should succeed
        $response1 = $this->actingAs($user1, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'quick_local',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'payment_method' => 'credit_card',
            ]);

        $response1->assertStatus(201);

        // Second checkout should fail (insufficient inventory)
        $response2 = $this->actingAs($user2, 'sanctum')
            ->postJson('/api/checkout', [
                'fulfillment_type' => 'quick_local',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'payment_method' => 'credit_card',
            ]);

        $response2->assertStatus(500);

        // Verify inventory state
        $inventory = Inventory::where('product_id', $this->quickProduct->id)
            ->where('store_id', $this->nearStore->id)
            ->first();
        
        $this->assertEquals(5, $inventory->quantity);
        $this->assertEquals(3, $inventory->reserved); // Only first order reserved
        $this->assertEquals(2, $inventory->available);
    }
}
