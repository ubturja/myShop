<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Seller;
use App\Models\CartItem;
use App\Models\Store;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Feature tests for Cart functionality.
 * 
 * Tests:
 * - Adding items to cart
 * - Updating cart item quantities
 * - Removing items from cart
 * - Clearing entire cart
 * - Quick commerce items requiring store selection
 * - Cart item consolidation (same product + store)
 */
class CartTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authenticated user for tests.
     */
    protected User $user;

    /**
     * Regular product for testing.
     */
    protected Product $product;

    /**
     * Quick commerce product for testing.
     */
    protected Product $quickProduct;

    /**
     * Store for quick commerce testing.
     */
    protected Store $store;

    /**
     * Set up test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create seller
        $sellerUser = User::factory()->seller()->create();
        $seller = Seller::factory()->verified()->create([
            'user_id' => $sellerUser->id,
        ]);

        // Create test user
        $this->user = User::factory()->create();

        // Create regular product
        $this->product = Product::factory()->create([
            'seller_id' => $seller->id,
            'price_cents' => 1999, // $19.99
            'is_quick_item' => false,
            'status' => 'active',
        ]);

        // Create quick commerce product
        $this->quickProduct = Product::factory()->create([
            'seller_id' => $seller->id,
            'price_cents' => 599, // $5.99
            'is_quick_item' => true,
            'status' => 'active',
        ]);

        // Create store with inventory
        $this->store = Store::factory()->create();
        Inventory::factory()->inStock()->create([
            'product_id' => $this->quickProduct->id,
            'store_id' => $this->store->id,
            'quantity' => 100,
        ]);
    }

    /**
     * Test user can view their empty cart.
     */
    public function test_can_view_empty_cart(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/cart');

        $response->assertStatus(200)
            ->assertJson([
                'items' => [],
                'summary' => [
                    'subtotal_cents' => 0,
                    'item_count' => 0,
                    'unique_products' => 0,
                ],
            ]);
    }

    /**
     * Test user can add regular product to cart.
     */
    public function test_can_add_product_to_cart(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Item added to cart',
                'cart_item' => [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'subtotal_cents' => 3998, // $19.99 * 2
                ],
            ]);

        // Verify database
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
    }

    /**
     * Test user can add quick commerce product with store_id.
     */
    public function test_can_add_quick_item_with_store(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/cart', [
                'product_id' => $this->quickProduct->id,
                'quantity' => 3,
                'store_id' => $this->store->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Item added to cart',
                'cart_item' => [
                    'product_id' => $this->quickProduct->id,
                    'quantity' => 3,
                    'store_id' => $this->store->id,
                    'subtotal_cents' => 1797, // $5.99 * 3
                ],
            ]);
    }

    /**
     * Test adding quick item without store_id fails validation.
     */
    public function test_quick_item_requires_store_id(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/cart', [
                'product_id' => $this->quickProduct->id,
                'quantity' => 1,
                // Missing store_id
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);
    }

    /**
     * Test adding same product twice increments quantity.
     */
    public function test_adding_duplicate_product_increments_quantity(): void
    {
        // First add
        $this->actingAs($this->user)
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        // Second add - should increment quantity
        $response = $this->actingAs($this->user)
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 3,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Cart item quantity updated',
                'cart_item' => [
                    'quantity' => 5, // 2 + 3
                ],
            ]);

        // Verify only one cart item exists
        $this->assertEquals(1, CartItem::where('user_id', $this->user->id)->count());
    }

    /**
     * Test user can update cart item quantity.
     */
    public function test_can_update_cart_item_quantity(): void
    {
        // Create cart item
        $cartItem = CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        // Update quantity
        $response = $this->actingAs($this->user)
            ->putJson("/api/cart/{$cartItem->id}", [
                'quantity' => 5,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cart item updated',
                'cart_item' => [
                    'quantity' => 5,
                ],
            ]);

        // Verify database
        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 5,
        ]);
    }

    /**
     * Test user can remove item from cart.
     */
    public function test_can_remove_item_from_cart(): void
    {
        // Create cart item
        $cartItem = CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        // Remove item
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/cart/{$cartItem->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Item removed from cart',
            ]);

        // Verify deleted from database
        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    /**
     * Test user can clear entire cart.
     */
    public function test_can_clear_cart(): void
    {
        // Add multiple items
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->quickProduct->id,
            'quantity' => 1,
            'store_id' => $this->store->id,
        ]);

        // Clear cart
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/cart');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cart cleared successfully',
                'cart_summary' => [
                    'subtotal_cents' => 0,
                    'item_count' => 0,
                ],
            ]);

        // Verify all items deleted
        $this->assertEquals(0, CartItem::where('user_id', $this->user->id)->count());
    }

    /**
     * Test user cannot modify another user's cart items.
     */
    public function test_cannot_modify_other_user_cart(): void
    {
        $otherUser = User::factory()->create();
        
        // Create cart item for other user
        $cartItem = CartItem::create([
            'user_id' => $otherUser->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        // Try to update as current user
        $response = $this->actingAs($this->user)
            ->putJson("/api/cart/{$cartItem->id}", [
                'quantity' => 10,
            ]);

        $response->assertStatus(404); // Not found (filtered by user_id)
    }

    /**
     * Test cart validation rejects invalid data.
     */
    public function test_cart_validation_rejects_invalid_data(): void
    {
        // Invalid product ID
        $response = $this->actingAs($this->user)
            ->postJson('/api/cart', [
                'product_id' => 99999,
                'quantity' => 1,
            ]);
        $response->assertStatus(422);

        // Invalid quantity (zero)
        $response = $this->actingAs($this->user)
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 0,
            ]);
        $response->assertStatus(422);

        // Invalid quantity (too high)
        $response = $this->actingAs($this->user)
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 1000,
            ]);
        $response->assertStatus(422);
    }

    /**
     * Test unauthenticated users cannot access cart.
     */
    public function test_unauthenticated_user_cannot_access_cart(): void
    {
        $response = $this->getJson('/api/cart');
        $response->assertStatus(401);

        $response = $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);
        $response->assertStatus(401);
    }
}
