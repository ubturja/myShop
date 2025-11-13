<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Http\Requests\StoreCartItemRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * CartController manages shopping cart operations.
 * 
 * Endpoints:
 * - GET /api/cart - Get user's cart with calculated totals
 * - POST /api/cart - Add item to cart (or update quantity if exists)
 * - PUT /api/cart/{id} - Update cart item quantity
 * - DELETE /api/cart/{id} - Remove item from cart
 * - DELETE /api/cart - Clear entire cart
 * 
 * Cart items are user-scoped (each user has their own cart).
 * For quick commerce items, store_id must be specified.
 */
class CartController extends Controller
{
    /**
     * Get user's shopping cart with all items and calculated totals.
     * 
     * Returns:
     * - items: Array of cart items with product details
     * - subtotal_cents: Sum of all item prices
     * - item_count: Total number of items
     * - unique_products: Number of distinct products
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get all cart items for this user with product relationships
        $cartItems = CartItem::where('user_id', $user->id)
            ->with(['product.seller', 'store'])
            ->get();

        // Calculate cart totals
        $subtotalCents = $cartItems->sum('total_price_cents');
        $itemCount = $cartItems->sum('quantity');
        $uniqueProducts = $cartItems->count();

        // Group items by seller for potential split shipping
        $itemsBySeller = $cartItems->groupBy(function ($item) {
            return $item->product->seller_id;
        });

        return response()->json([
            'items' => $cartItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => [
                        'id' => $item->product->id,
                        'title' => $item->product->title,
                        'price_cents' => $item->product->price_cents,
                        'price' => $item->product->price,
                        'formatted_price' => $item->product->formatted_price,
                        'image_url' => $item->product->image_url,
                        'category' => $item->product->category,
                        'is_quick_item' => $item->product->is_quick_item,
                        'seller' => [
                            'id' => $item->product->seller->id,
                            'business_name' => $item->product->seller->business_name,
                        ],
                    ],
                    'quantity' => $item->quantity,
                    'store_id' => $item->store_id,
                    'store' => $item->store ? [
                        'id' => $item->store->id,
                        'name' => $item->store->name,
                        'address' => $item->store->address,
                    ] : null,
                    'subtotal_cents' => $item->total_price_cents,
                ];
            }),
            'summary' => [
                'subtotal_cents' => $subtotalCents,
                'subtotal' => $subtotalCents / 100,
                'item_count' => $itemCount,
                'unique_products' => $uniqueProducts,
                'sellers_count' => $itemsBySeller->count(),
            ],
        ]);
    }

    /**
     * Add item to cart or update quantity if already exists.
     * 
     * Business logic:
     * - If item already in cart with same store_id, increment quantity
     * - If item in cart with different store_id, create separate cart item
     * - Validate product exists and is available
     * - For quick items, require store_id
     * 
     * @param StoreCartItemRequest $request Validated request
     * @return JsonResponse
     */
    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Get product to check if it's a quick item
        $product = Product::findOrFail($validated['product_id']);

        // Check if product is active (ENUM values are uppercase: ACTIVE, DRAFT, ARCHIVED)
        if ($product->status !== 'ACTIVE') {
            return response()->json([
                'error' => 'This product is currently unavailable',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Check if item already exists in cart (same product and store)
            $existingItem = CartItem::where('user_id', $user->id)
                ->where('product_id', $validated['product_id'])
                ->where('store_id', $validated['store_id'] ?? null)
                ->first();

            if ($existingItem) {
                // Update existing cart item quantity
                $newQuantity = $existingItem->quantity + $validated['quantity'];
                $existingItem->update(['quantity' => $newQuantity]);
                
                $cartItem = $existingItem;
                $message = 'Cart item quantity updated';
            } else {
                // Create new cart item
                $cartItem = CartItem::create([
                    'user_id' => $user->id,
                    'product_id' => $validated['product_id'],
                    'quantity' => $validated['quantity'],
                    'store_id' => $validated['store_id'] ?? null,
                ]);
                
                $message = 'Item added to cart';
            }

            DB::commit();

            // Load relationships for response
            $cartItem->load(['product', 'store']);

            return response()->json([
                'message' => $message,
                'cart_item' => [
                    'id' => $cartItem->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'store_id' => $cartItem->store_id,
                    'subtotal_cents' => $cartItem->total_price_cents,
                ],
                'cart_summary' => $this->getCartSummary($user->id),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Failed to add item to cart',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update cart item quantity.
     * 
     * @param Request $request
     * @param int $id Cart item ID
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:999',
        ]);

        // Find cart item and verify ownership
        $cartItem = CartItem::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $cartItem->update([
                'quantity' => $validated['quantity'],
            ]);

            DB::commit();

            $cartItem->load(['product', 'store']);

            return response()->json([
                'message' => 'Cart item updated',
                'cart_item' => [
                    'id' => $cartItem->id,
                    'quantity' => $cartItem->quantity,
                    'subtotal_cents' => $cartItem->total_price_cents,
                ],
                'cart_summary' => $this->getCartSummary($user->id),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Failed to update cart item',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove item from cart.
     * 
     * @param Request $request
     * @param int $id Cart item ID
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Find cart item and verify ownership
        $cartItem = CartItem::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $cartItem->delete();
            DB::commit();

            return response()->json([
                'message' => 'Item removed from cart',
                'cart_summary' => $this->getCartSummary($user->id),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Failed to remove item from cart',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear entire cart (remove all items).
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();
        try {
            // Delete all cart items for this user
            CartItem::where('user_id', $user->id)->delete();
            
            DB::commit();

            return response()->json([
                'message' => 'Cart cleared successfully',
                'cart_summary' => [
                    'subtotal_cents' => 0,
                    'item_count' => 0,
                    'unique_products' => 0,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Failed to clear cart',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cart summary without full item details.
     * Helper method used in responses.
     * 
     * @param int $userId
     * @return array
     */
    private function getCartSummary(int $userId): array
    {
        $cartItems = CartItem::where('user_id', $userId)
            ->with('product')
            ->get();

        return [
            'subtotal_cents' => $cartItems->sum('total_price_cents'),
            'item_count' => $cartItems->sum('quantity'),
            'unique_products' => $cartItems->count(),
        ];
    }
}
