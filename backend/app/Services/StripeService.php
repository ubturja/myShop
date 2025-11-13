<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * StripeService - Handles Stripe payment processing with mock mode support.
 * 
 * Mock Mode:
 * - Set STRIPE_MOCK=true to bypass real API calls for testing
 * - Returns deterministic responses based on input patterns
 * - Useful for local development and automated tests
 * 
 * Real Mode:
 * - Requires STRIPE_SECRET_KEY and STRIPE_WEBHOOK_SECRET
 * - Makes actual API calls to Stripe's REST API
 * - Handles payment intents, confirmations, refunds, and webhooks
 */
class StripeService
{
    private string $secretKey;
    private string $webhookSecret;
    private bool $mockMode;
    private string $apiVersion = '2023-10-16';
    private string $baseUrl = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret_key') ?? '';
        $this->webhookSecret = config('services.stripe.webhook_secret') ?? '';
        $this->mockMode = config('services.stripe.mock') ?? false;
    }

    /**
     * Create a payment intent for checkout.
     * 
     * @param int $amountCents Total amount in cents
     * @param string $currency Three-letter ISO currency code (USD, EUR, etc.)
     * @param array $metadata Optional metadata (order_id, user_id, etc.)
     * @return array{success: bool, payment_intent_id: string|null, client_secret: string|null, status: string|null, error: string|null}
     */
    public function createPaymentIntent(int $amountCents, string $currency = 'USD', array $metadata = []): array
    {
        if ($this->mockMode) {
            return $this->mockCreatePaymentIntent($amountCents, $currency, $metadata);
        }

        if (empty($this->secretKey)) {
            return [
                'success' => false,
                'payment_intent_id' => null,
                'client_secret' => null,
                'status' => null,
                'error' => 'Stripe secret key not configured',
            ];
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->asForm()
                ->timeout(30)
                ->post("{$this->baseUrl}/payment_intents", [
                    'amount' => $amountCents,
                    'currency' => strtolower($currency),
                    'metadata' => $metadata,
                    'automatic_payment_methods' => ['enabled' => true],
                ]);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';
                Log::error('Stripe payment intent creation failed', [
                    'status' => $response->status(),
                    'error' => $error,
                    'amount' => $amountCents,
                ]);

                return [
                    'success' => false,
                    'payment_intent_id' => null,
                    'client_secret' => null,
                    'status' => null,
                    'error' => $error,
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'payment_intent_id' => $data['id'],
                'client_secret' => $data['client_secret'],
                'status' => $data['status'],
                'error' => null,
            ];

        } catch (Exception $e) {
            Log::error('Stripe payment intent exception', [
                'message' => $e->getMessage(),
                'amount' => $amountCents,
            ]);

            return [
                'success' => false,
                'payment_intent_id' => null,
                'client_secret' => null,
                'status' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Confirm a payment intent (client-side usually handles this).
     * 
     * @param string $paymentIntentId Payment intent ID to confirm
     * @param string|null $paymentMethodId Payment method ID (if not already attached)
     * @return array{success: bool, status: string|null, error: string|null}
     */
    public function confirmPayment(string $paymentIntentId, ?string $paymentMethodId = null): array
    {
        if ($this->mockMode) {
            return $this->mockConfirmPayment($paymentIntentId, $paymentMethodId);
        }

        if (empty($this->secretKey)) {
            return [
                'success' => false,
                'status' => null,
                'error' => 'Stripe secret key not configured',
            ];
        }

        try {
            $params = [];
            if ($paymentMethodId) {
                $params['payment_method'] = $paymentMethodId;
            }

            $response = Http::withBasicAuth($this->secretKey, '')
                ->asForm()
                ->timeout(30)
                ->post("{$this->baseUrl}/payment_intents/{$paymentIntentId}/confirm", $params);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';
                Log::error('Stripe payment confirmation failed', [
                    'status' => $response->status(),
                    'error' => $error,
                    'payment_intent_id' => $paymentIntentId,
                ]);

                return [
                    'success' => false,
                    'status' => null,
                    'error' => $error,
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'status' => $data['status'],
                'error' => null,
            ];

        } catch (Exception $e) {
            Log::error('Stripe payment confirmation exception', [
                'message' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);

            return [
                'success' => false,
                'status' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refund a payment.
     * 
     * @param string $paymentIntentId Payment intent to refund
     * @param int|null $amountCents Amount to refund (null = full refund)
     * @param string|null $reason Reason for refund (duplicate, fraudulent, requested_by_customer)
     * @return array{success: bool, refund_id: string|null, status: string|null, error: string|null}
     */
    public function refundPayment(string $paymentIntentId, ?int $amountCents = null, ?string $reason = null): array
    {
        if ($this->mockMode) {
            return $this->mockRefundPayment($paymentIntentId, $amountCents, $reason);
        }

        if (empty($this->secretKey)) {
            return [
                'success' => false,
                'refund_id' => null,
                'status' => null,
                'error' => 'Stripe secret key not configured',
            ];
        }

        try {
            $params = ['payment_intent' => $paymentIntentId];
            if ($amountCents !== null) {
                $params['amount'] = $amountCents;
            }
            if ($reason !== null) {
                $params['reason'] = $reason;
            }

            $response = Http::withBasicAuth($this->secretKey, '')
                ->asForm()
                ->timeout(30)
                ->post("{$this->baseUrl}/refunds", $params);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';
                Log::error('Stripe refund failed', [
                    'status' => $response->status(),
                    'error' => $error,
                    'payment_intent_id' => $paymentIntentId,
                ]);

                return [
                    'success' => false,
                    'refund_id' => null,
                    'status' => null,
                    'error' => $error,
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'refund_id' => $data['id'],
                'status' => $data['status'],
                'error' => null,
            ];

        } catch (Exception $e) {
            Log::error('Stripe refund exception', [
                'message' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);

            return [
                'success' => false,
                'refund_id' => null,
                'status' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Construct and verify webhook event from request.
     * 
     * @param string $payload Raw request body
     * @param string $signature Stripe-Signature header value
     * @return array{success: bool, event: array|null, error: string|null}
     */
    public function constructWebhookEvent(string $payload, string $signature): array
    {
        if ($this->mockMode) {
            return $this->mockConstructWebhookEvent($payload, $signature);
        }

        if (empty($this->webhookSecret)) {
            return [
                'success' => false,
                'event' => null,
                'error' => 'Stripe webhook secret not configured',
            ];
        }

        try {
            // Parse signature header
            $elements = explode(',', $signature);
            $signatureData = [];
            foreach ($elements as $element) {
                $parts = explode('=', $element, 2);
                if (count($parts) === 2) {
                    $signatureData[$parts[0]] = $parts[1];
                }
            }

            if (!isset($signatureData['t']) || !isset($signatureData['v1'])) {
                return [
                    'success' => false,
                    'event' => null,
                    'error' => 'Invalid signature format',
                ];
            }

            $timestamp = $signatureData['t'];
            $expectedSignature = $signatureData['v1'];

            // Check timestamp tolerance (5 minutes)
            $tolerance = 300;
            if (abs(time() - (int)$timestamp) > $tolerance) {
                return [
                    'success' => false,
                    'event' => null,
                    'error' => 'Timestamp outside tolerance',
                ];
            }

            // Compute expected signature
            $signedPayload = "{$timestamp}.{$payload}";
            $computedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

            // Compare signatures (constant-time comparison)
            if (!hash_equals($computedSignature, $expectedSignature)) {
                return [
                    'success' => false,
                    'event' => null,
                    'error' => 'Invalid signature',
                ];
            }

            // Parse event
            $event = json_decode($payload, true);
            if (!$event || !isset($event['type'])) {
                return [
                    'success' => false,
                    'event' => null,
                    'error' => 'Invalid event payload',
                ];
            }

            return [
                'success' => true,
                'event' => $event,
                'error' => null,
            ];

        } catch (Exception $e) {
            Log::error('Stripe webhook event construction exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'event' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if service is in mock mode.
     */
    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    /**
     * Set mock mode (useful for testing).
     */
    public function setMockMode(bool $mockMode): void
    {
        $this->mockMode = $mockMode;
    }

    // ==================== Mock Mode Methods ====================

    /**
     * Mock payment intent creation.
     */
    private function mockCreatePaymentIntent(int $amountCents, string $currency, array $metadata): array
    {
        // Deterministic ID based on amount and metadata
        $idSeed = $amountCents . json_encode($metadata);
        $paymentIntentId = 'pi_mock_' . substr(md5($idSeed), 0, 24);
        $clientSecret = $paymentIntentId . '_secret_' . substr(md5($idSeed . 'secret'), 0, 16);

        return [
            'success' => true,
            'payment_intent_id' => $paymentIntentId,
            'client_secret' => $clientSecret,
            'status' => 'requires_payment_method',
            'error' => null,
            'mock' => true,
        ];
    }

    /**
     * Mock payment confirmation.
     */
    private function mockConfirmPayment(string $paymentIntentId, ?string $paymentMethodId): array
    {
        // Simulate failure for specific test patterns
        if (str_contains($paymentIntentId, 'fail')) {
            return [
                'success' => false,
                'status' => 'requires_payment_method',
                'error' => 'Your card was declined',
                'mock' => true,
            ];
        }

        return [
            'success' => true,
            'status' => 'succeeded',
            'error' => null,
            'mock' => true,
        ];
    }

    /**
     * Mock refund.
     */
    private function mockRefundPayment(string $paymentIntentId, ?int $amountCents, ?string $reason): array
    {
        $refundId = 're_mock_' . substr(md5($paymentIntentId . ($amountCents ?? '')), 0, 24);

        return [
            'success' => true,
            'refund_id' => $refundId,
            'status' => 'succeeded',
            'error' => null,
            'mock' => true,
        ];
    }

    /**
     * Mock webhook event construction.
     */
    private function mockConstructWebhookEvent(string $payload, string $signature): array
    {
        // In mock mode, accept any signature and parse payload
        try {
            $event = json_decode($payload, true);
            if (!$event || !isset($event['type'])) {
                return [
                    'success' => false,
                    'event' => null,
                    'error' => 'Invalid event payload',
                    'mock' => true,
                ];
            }

            return [
                'success' => true,
                'event' => $event,
                'error' => null,
                'mock' => true,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'event' => null,
                'error' => $e->getMessage(),
                'mock' => true,
            ];
        }
    }
}
