<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\GroupBuy;
use App\Http\Requests\CheckoutRequest;
use App\Services\QuickFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * CheckoutController handles order creation and payment processing.
 * 
 * Endpoints:
 * - POST /api/checkout - Process checkout from cart
 * 
 * Supports three fulfillment types:
 * 1. quick_local: Rapid delivery from nearest store (uses QuickFulfillmentService)
 * 2. standard: Traditional shipping from seller
 * 3. group_buy: Pinduoduo-style group purchase
 * 
 * TODO: Integrate real payment processor (Stripe, crypto wallet)
 * TODO: Add webhook handlers for payment confirmations
 * TODO: Implement order status tracking and notifications
 */
class CheckoutController extends Controller
{
    /**
     * QuickFulfillmentService instance for finding nearest stores.
     */
    protected QuickFulfillmentService $quickFulfillment;

    /**
     * Inject QuickFulfillmentService dependency.
     */
    public function __construct(QuickFulfillmentService $quickFulfillment)
    {
        $this->quickFulfillment = $quickFulfillment;
    }

    /**
     * Process checkout and create order(s).
     * 
     * Flow:
     * 1. Validate request and cart
     * 2. Calculate totals
     * 3. For quick_local: Find nearest store and reserve inventory
     * 4. Create order(s) - may split by seller for standard fulfillment
     * 5. Process payment (currently mocked)
     * 6. Clear cart on success
     * 7. Return order details
     * 
     * @param CheckoutRequest $request Validated checkout request
     * @return JsonResponse
     */
    public function process(CheckoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $fulfillmentType = $validated['fulfillment_type'];

        // Get user's cart items
        $cartItems = CartItem::where('user_id', $user->id)
            ->with(['product.seller', 'store'])
            ->get();

        // Validate cart is not empty
        if ($cartItems->isEmpty()) {
            return response()->json([
                'error' => 'Cart is empty',
            ], 422);
        }

        // Route to appropriate fulfillment handler
        try {
            DB::beginTransaction();

            $result = match ($fulfillmentType) {
                'quick_local' => $this->processQuickLocalCheckout($user, $cartItems, $validated),
                'standard' => $this->processStandardCheckout($user, $cartItems, $validated),
                'group_buy' => $this->processGroupBuyCheckout($user, $cartItems, $validated),
                default => throw new \InvalidArgumentException('Invalid fulfillment type'),
            };

            // Payment processing would happen here
            // For now, we mock successful payment
            $paymentResult = $this->mockPaymentProcessing($validated['payment_method'], $result['total_cents']);

            if (!$paymentResult['success']) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Payment failed',
                    'message' => $paymentResult['error'],
                ], 402);
            }

            // Clear cart on successful checkout
            CartItem::where('user_id', $user->id)->delete();

            DB::commit();

            return response()->json([
                'message' => 'Order placed successfully',
                'orders' => $result['orders'],
                'total_cents' => $result['total_cents'],
                'payment' => $paymentResult,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Checkout failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process quick local delivery checkout.
     * 
     * Steps:
     * 1. Validate all items are quick_items
     * 2. Find nearest store with complete inventory
     * 3. Reserve inventory at that store (with pessimistic locking)
     * 4. Create single order with ETA
     * 
     * @param \App\Models\User $user
     * @param \Illuminate\Support\Collection $cartItems
     * @param array $validated
     * @return array{orders: array, total_cents: int}
     * @throws \Exception
     */
    protected function processQuickLocalCheckout($user, $cartItems, array $validated): array
    {
        $latitude = $validated['latitude'];
        $longitude = $validated['longitude'];

        // Validate all items are quick commerce items
        $nonQuickItems = $cartItems->filter(fn($item) => !$item->product->is_quick_item);
        
        if ($nonQuickItems->isNotEmpty()) {
            throw new \Exception(
                'Quick local delivery only available for quick commerce items. ' .
                'Some items in your cart are not eligible.'
            );
        }

        // Find nearest store with full inventory availability
        $storeResult = $this->quickFulfillment->findNearestStoreWithInventory(
            $cartItems,
            $latitude,
            $longitude
        );

        if (!$storeResult['products_available'] || !$storeResult['store']) {
            throw new \Exception(
                'No nearby stores have all requested items in stock. ' .
                'Please try standard delivery or adjust your cart.'
            );
        }

        $store = $storeResult['store'];
        $eta = $storeResult['eta'];

        // Reserve inventory at the selected store (pessimistic locking)
        $reservationSuccess = $this->quickFulfillment->reserveInventory($store, $cartItems);

        if (!$reservationSuccess) {
            throw new \Exception(
                'Unable to reserve inventory. Items may have sold out. Please try again.'
            );
        }

        // Calculate total
        $totalCents = $cartItems->sum('total_price_cents');

        // Get primary seller (could be multiple sellers, but order goes to first one)
        $primarySeller = $cartItems->first()->product->seller;

        // Create order
        $order = Order::create([
            'user_id' => $user->id,
            'seller_id' => $primarySeller->id,
            'store_id' => $store->id,
            'total_cents' => $totalCents,
            'currency' => 'USD',
            'status' => 'pending',
            'fulfillment_type' => 'quick_local',
            'eta' => $eta,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Create order items
        foreach ($cartItems as $cartItem) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $cartItem->product_id,
                'quantity' => $cartItem->quantity,
                'price_cents' => $cartItem->product->price_cents,
            ]);
        }

        // Load relationships for response
        $order->load(['orderItems.product', 'store', 'seller']);

        return [
            'orders' => [
                [
                    'id' => $order->id,
                    'total_cents' => $order->total_cents,
                    'status' => $order->status,
                    'fulfillment_type' => $order->fulfillment_type,
                    'eta_minutes' => $order->eta,
                    'store' => [
                        'id' => $store->id,
                        'name' => $store->name,
                        'address' => $store->address,
                    ],
                    'items' => $order->orderItems->map(fn($item) => [
                        'product_id' => $item->product_id,
                        'title' => $item->product->title,
                        'quantity' => $item->quantity,
                        'price_cents' => $item->price_cents,
                    ]),
                ],
            ],
            'total_cents' => $totalCents,
        ];
    }

    /**
     * Process standard delivery checkout.
     * 
     * May create multiple orders if cart contains items from different sellers.
     * Each seller gets their own order for separate fulfillment.
     * 
     * @param \App\Models\User $user
     * @param \Illuminate\Support\Collection $cartItems
     * @param array $validated
     * @return array{orders: array, total_cents: int}
     */
    protected function processStandardCheckout($user, $cartItems, array $validated): array
    {
        $shippingAddress = $validated['shipping_address'];
        $orders = [];
        $totalCents = 0;

        // Group cart items by seller
        $itemsBySeller = $cartItems->groupBy(fn($item) => $item->product->seller_id);

        // Create separate order for each seller
        foreach ($itemsBySeller as $sellerId => $sellerItems) {
            $seller = $sellerItems->first()->product->seller;
            $orderTotal = $sellerItems->sum('total_price_cents');
            $totalCents += $orderTotal;

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'seller_id' => $seller->id,
                'store_id' => null, // Standard delivery doesn't use stores
                'total_cents' => $orderTotal,
                'currency' => 'USD',
                'status' => 'pending',
                'fulfillment_type' => 'standard',
                'eta' => rand(2880, 10080), // 2-7 days in minutes
                'notes' => json_encode([
                    'shipping_address' => $shippingAddress,
                    'customer_notes' => $validated['notes'] ?? null,
                ]),
            ]);

            // Create order items
            foreach ($sellerItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price_cents' => $cartItem->product->price_cents,
                ]);
            }

            $order->load(['orderItems.product', 'seller']);

            $orders[] = [
                'id' => $order->id,
                'total_cents' => $order->total_cents,
                'status' => $order->status,
                'fulfillment_type' => $order->fulfillment_type,
                'eta_minutes' => $order->eta,
                'seller' => [
                    'id' => $seller->id,
                    'business_name' => $seller->business_name,
                ],
                'items' => $order->orderItems->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'title' => $item->product->title,
                    'quantity' => $item->quantity,
                    'price_cents' => $item->price_cents,
                ]),
            ];
        }

        return [
            'orders' => $orders,
            'total_cents' => $totalCents,
        ];
    }

    /**
     * Process group buy checkout.
     * 
     * User must be a member of the specified group buy.
     * Group buy must have reached target size.
     * 
     * @param \App\Models\User $user
     * @param \Illuminate\Support\Collection $cartItems
     * @param array $validated
     * @return array{orders: array, total_cents: int}
     * @throws \Exception
     */
    protected function processGroupBuyCheckout($user, $cartItems, array $validated): array
    {
        $groupBuyId = $validated['group_buy_id'];
        
        // Get group buy
        $groupBuy = GroupBuy::with(['product.seller', 'members'])->findOrFail($groupBuyId);

        // Verify user is a member (members() is belongsToMany, so check by id not user_id)
        $isMember = $groupBuy->members->contains('id', $user->id);
        
        if (!$isMember) {
            throw new \Exception('You must join the group buy before checking out');
        }

        // Verify group buy is fulfilled (target reached)
        if ($groupBuy->status !== 'FULFILLED') {
            throw new \Exception('This group buy has not reached its target yet');
        }

        // Verify cart contains the group buy product
        $hasProduct = $cartItems->contains('product_id', $groupBuy->product_id);
        
        if (!$hasProduct) {
            throw new \Exception('Group buy product not in cart');
        }

        // Calculate total using team price
        $quantity = $cartItems->firstWhere('product_id', $groupBuy->product_id)->quantity;
        $totalCents = $groupBuy->team_price_cents * $quantity;

        // Create order
        $order = Order::create([
            'user_id' => $user->id,
            'seller_id' => $groupBuy->product->seller_id,
            'store_id' => null,
            'total_cents' => $totalCents,
            'currency' => 'USD',
            'status' => 'pending',
            'fulfillment_type' => 'group_buy',
            'eta' => rand(2880, 7200), // 2-5 days
            'notes' => json_encode([
                'group_buy_id' => $groupBuy->id,
                'team_price_cents' => $groupBuy->team_price_cents,
            ]),
        ]);

        // Create order item
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $groupBuy->product_id,
            'quantity' => $quantity,
            'price_cents' => $groupBuy->team_price_cents,
        ]);

        $order->load(['orderItems.product', 'seller']);

        return [
            'orders' => [
                [
                    'id' => $order->id,
                    'total_cents' => $order->total_cents,
                    'status' => $order->status,
                    'fulfillment_type' => $order->fulfillment_type,
                    'eta_minutes' => $order->eta,
                    'group_buy' => [
                        'id' => $groupBuy->id,
                        'team_price_cents' => $groupBuy->team_price_cents,
                        'savings' => $groupBuy->savings,
                    ],
                    'items' => $order->orderItems->map(fn($item) => [
                        'product_id' => $item->product_id,
                        'title' => $item->product->title,
                        'quantity' => $item->quantity,
                        'price_cents' => $item->price_cents,
                    ]),
                ],
            ],
            'total_cents' => $totalCents,
        ];
    }

    /**
     * Mock payment processing for development.
     * 
     * TODO: Replace with real payment processor integration:
     * - Stripe for credit cards
     * - Crypto wallet integration (MetaMask, WalletConnect)
     * - Store payment confirmation details
     * 
     * @param string $paymentMethod
     * @param int $amountCents
     * @return array{success: bool, transaction_id: string|null, error: string|null}
     */
    protected function mockPaymentProcessing(string $paymentMethod, int $amountCents): array
    {
        // Simulate payment processing delay
        // usleep(500000); // 0.5 seconds

        // Mock success for amounts under $10,000
        if ($amountCents > 1000000) {
            return [
                'success' => false,
                'transaction_id' => null,
                'error' => 'Amount exceeds limit',
            ];
        }

        return [
            'success' => true,
            'transaction_id' => 'mock_tx_' . uniqid(),
            'method' => $paymentMethod,
            'amount_cents' => $amountCents,
            'error' => null,
        ];
    }
}
