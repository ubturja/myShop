<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * StripeWebhookController - Handles Stripe webhook events.
 * 
 * Supported Events:
 * - payment_intent.succeeded: Payment successful, update order status
 * - payment_intent.payment_failed: Payment failed, update order status
 * - charge.refunded: Refund processed, update order status
 * 
 * Security:
 * - Verifies webhook signature using StripeService
 * - Implements idempotency using payment_intent_id
 * - Logs all webhook events for audit trail
 */
class StripeWebhookController extends Controller
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Handle incoming Stripe webhook.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        // Verify webhook signature
        $result = $this->stripeService->constructWebhookEvent($payload, $signature);

        if (!$result['success']) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $result['error'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Webhook signature verification failed',
            ], 400);
        }

        $event = $result['event'];
        $eventType = $event['type'];
        $eventId = $event['id'] ?? 'unknown';

        Log::info('Stripe webhook received', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        // Handle specific event types
        try {
            switch ($eventType) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($event);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($event);
                    break;

                case 'charge.refunded':
                    $this->handleChargeRefunded($event);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event type', [
                        'event_type' => $eventType,
                        'event_id' => $eventId,
                    ]);
            }

            return response()->json(['received' => true]);

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing error', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent Stripe from retrying (log the error for manual review)
            return response()->json([
                'received' => true,
                'error' => 'Internal processing error',
            ]);
        }
    }

    /**
     * Handle payment_intent.succeeded event.
     * 
     * @param array $event
     * @return void
     */
    private function handlePaymentSucceeded(array $event): void
    {
        $paymentIntent = $event['data']['object'];
        $paymentIntentId = $paymentIntent['id'];

        // Find order(s) by payment_intent_id
        $orders = Order::where('payment_intent_id', $paymentIntentId)->get();

        if ($orders->isEmpty()) {
            Log::warning('No orders found for payment intent', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        DB::transaction(function () use ($orders, $paymentIntent) {
            foreach ($orders as $order) {
                // Idempotency check
                if ($order->payment_status === 'succeeded') {
                    Log::info('Payment already marked as succeeded (idempotent)', [
                        'order_id' => $order->id,
                        'payment_intent_id' => $order->payment_intent_id,
                    ]);
                    continue;
                }

                $order->update([
                    'payment_status' => 'succeeded',
                    'payment_method_details' => [
                        'type' => $paymentIntent['payment_method_types'][0] ?? 'unknown',
                        'succeeded_at' => now()->toIso8601String(),
                    ],
                ]);

                Log::info('Payment succeeded, order updated', [
                    'order_id' => $order->id,
                    'payment_intent_id' => $paymentIntent['id'],
                    'amount' => $paymentIntent['amount'],
                ]);
            }
        });
    }

    /**
     * Handle payment_intent.payment_failed event.
     * 
     * @param array $event
     * @return void
     */
    private function handlePaymentFailed(array $event): void
    {
        $paymentIntent = $event['data']['object'];
        $paymentIntentId = $paymentIntent['id'];
        $lastError = $paymentIntent['last_payment_error'] ?? null;

        // Find order(s) by payment_intent_id
        $orders = Order::where('payment_intent_id', $paymentIntentId)->get();

        if ($orders->isEmpty()) {
            Log::warning('No orders found for failed payment intent', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        DB::transaction(function () use ($orders, $paymentIntent, $lastError) {
            foreach ($orders as $order) {
                // Idempotency check
                if ($order->payment_status === 'failed') {
                    Log::info('Payment already marked as failed (idempotent)', [
                        'order_id' => $order->id,
                        'payment_intent_id' => $order->payment_intent_id,
                    ]);
                    continue;
                }

                $order->update([
                    'payment_status' => 'failed',
                    'payment_method_details' => [
                        'type' => $paymentIntent['payment_method_types'][0] ?? 'unknown',
                        'failed_at' => now()->toIso8601String(),
                        'error_code' => $lastError['code'] ?? 'unknown',
                        'error_message' => $lastError['message'] ?? 'Payment failed',
                    ],
                ]);

                Log::info('Payment failed, order updated', [
                    'order_id' => $order->id,
                    'payment_intent_id' => $paymentIntent['id'],
                    'error_code' => $lastError['code'] ?? 'unknown',
                ]);
            }
        });
    }

    /**
     * Handle charge.refunded event.
     * 
     * @param array $event
     * @return void
     */
    private function handleChargeRefunded(array $event): void
    {
        $charge = $event['data']['object'];
        $paymentIntentId = $charge['payment_intent'] ?? null;

        if (!$paymentIntentId) {
            Log::warning('Refunded charge has no payment_intent', [
                'charge_id' => $charge['id'],
            ]);
            return;
        }

        // Find order(s) by payment_intent_id
        $orders = Order::where('payment_intent_id', $paymentIntentId)->get();

        if ($orders->isEmpty()) {
            Log::warning('No orders found for refunded payment intent', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        DB::transaction(function () use ($orders, $charge) {
            foreach ($orders as $order) {
                // Idempotency check
                if ($order->payment_status === 'refunded') {
                    Log::info('Payment already marked as refunded (idempotent)', [
                        'order_id' => $order->id,
                        'payment_intent_id' => $order->payment_intent_id,
                    ]);
                    continue;
                }

                $order->update([
                    'payment_status' => 'refunded',
                    'status' => 'canceled', // Cancel the order
                    'payment_method_details' => array_merge($order->payment_method_details ?? [], [
                        'refunded_at' => now()->toIso8601String(),
                        'refund_amount' => $charge['amount_refunded'],
                    ]),
                ]);

                Log::info('Payment refunded, order canceled', [
                    'order_id' => $order->id,
                    'payment_intent_id' => $order->payment_intent_id,
                    'refund_amount' => $charge['amount_refunded'],
                ]);
            }
        });
    }
}
