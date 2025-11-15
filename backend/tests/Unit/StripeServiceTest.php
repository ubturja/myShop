<?php

namespace Tests\Unit;

use App\Services\StripeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @test
 */
class StripeServiceTest extends TestCase
{
    private StripeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StripeService();
    }

    /**
     * @test
     */
    public function is_mock_mode_returns_correct_value(): void
    {
        $this->assertTrue($this->service->isMockMode());
    }

    /**
     * @test
     */
    public function mock_create_payment_intent_returns_valid_response(): void
    {
        $this->service->setMockMode(true);

        $result = $this->service->createPaymentIntent(2999, 'USD', ['order_id' => '123']);

        $this->assertTrue($result['success']);
        $this->assertStringStartsWith('pi_mock_', $result['payment_intent_id']);
        $this->assertStringContainsString('secret', $result['client_secret']);
        $this->assertEquals('requires_payment_method', $result['status']);
        $this->assertNull($result['error']);
    }

    /**
     * @test
     */
    public function mock_create_payment_intent_is_deterministic(): void
    {
        $this->service->setMockMode(true);

        $result1 = $this->service->createPaymentIntent(2999, 'USD', ['order_id' => '123']);
        $result2 = $this->service->createPaymentIntent(2999, 'USD', ['order_id' => '123']);

        $this->assertEquals($result1['payment_intent_id'], $result2['payment_intent_id']);
        $this->assertEquals($result1['client_secret'], $result2['client_secret']);
    }

    /**
     * @test
     */
    public function mock_confirm_payment_succeeds(): void
    {
        $this->service->setMockMode(true);

        $result = $this->service->confirmPayment('pi_mock_12345', 'pm_card_visa');

        $this->assertTrue($result['success']);
        $this->assertEquals('succeeded', $result['status']);
        $this->assertNull($result['error']);
    }

    /**
     * @test
     */
    public function mock_confirm_payment_fails_for_fail_pattern(): void
    {
        $this->service->setMockMode(true);

        $result = $this->service->confirmPayment('pi_mock_fail_12345', 'pm_card_visa');

        $this->assertFalse($result['success']);
        $this->assertEquals('requires_payment_method', $result['status']);
        $this->assertStringContainsString('declined', $result['error']);
    }

    /**
     * @test
     */
    public function mock_refund_payment_returns_valid_response(): void
    {
        $this->service->setMockMode(true);

        $result = $this->service->refundPayment('pi_mock_12345', 2999, 'requested_by_customer');

        $this->assertTrue($result['success']);
        $this->assertStringStartsWith('re_mock_', $result['refund_id']);
        $this->assertEquals('succeeded', $result['status']);
        $this->assertNull($result['error']);
    }

    /**
     * @test
     */
    public function mock_construct_webhook_event_parses_valid_payload(): void
    {
        $this->service->setMockMode(true);

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'amount' => 2999,
                ],
            ],
        ]);

        $result = $this->service->constructWebhookEvent($payload, 'any_signature');

        $this->assertTrue($result['success']);
        $this->assertEquals('evt_test_123', $result['event']['id']);
        $this->assertEquals('payment_intent.succeeded', $result['event']['type']);
        $this->assertNull($result['error']);
    }

    /**
     * @test
     */
    public function mock_construct_webhook_event_rejects_invalid_payload(): void
    {
        $this->service->setMockMode(true);

        $result = $this->service->constructWebhookEvent('invalid json', 'any_signature');

        $this->assertFalse($result['success']);
        $this->assertNull($result['event']);
        $this->assertNotNull($result['error']);
    }

    /**
     * @test
     */
    public function real_api_create_payment_intent_success(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_fake']);
        config(['services.stripe.mock' => false]);
        $this->service = new StripeService();

        Http::fake([
            '*/payment_intents' => Http::response([
                'id' => 'pi_test_123456',
                'client_secret' => 'pi_test_123456_secret_abc',
                'status' => 'requires_payment_method',
                'amount' => 2999,
            ], 200),
        ]);

        $result = $this->service->createPaymentIntent(2999, 'USD', ['order_id' => '123']);

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test_123456', $result['payment_intent_id']);
        $this->assertEquals('pi_test_123456_secret_abc', $result['client_secret']);
        $this->assertEquals('requires_payment_method', $result['status']);
    }

    /**
     * @test
     */
    public function real_api_create_payment_intent_handles_error(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_fake']);
        config(['services.stripe.mock' => false]);
        $this->service = new StripeService();

        Http::fake([
            '*/payment_intents' => Http::response([
                'error' => [
                    'message' => 'Invalid amount',
                ],
            ], 400),
        ]);

        $result = $this->service->createPaymentIntent(-100, 'USD');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid amount', $result['error']);
    }

    /**
     * @test
     */
    public function real_api_confirm_payment_success(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_fake']);
        config(['services.stripe.mock' => false]);
        $this->service = new StripeService();

        Http::fake([
            '*/payment_intents/*/confirm' => Http::response([
                'id' => 'pi_test_123456',
                'status' => 'succeeded',
            ], 200),
        ]);

        $result = $this->service->confirmPayment('pi_test_123456', 'pm_card_visa');

        $this->assertTrue($result['success']);
        $this->assertEquals('succeeded', $result['status']);
    }

    /**
     * @test
     */
    public function real_api_refund_payment_success(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_fake']);
        config(['services.stripe.mock' => false]);
        $this->service = new StripeService();

        Http::fake([
            '*/refunds' => Http::response([
                'id' => 're_test_123456',
                'status' => 'succeeded',
                'amount' => 2999,
            ], 200),
        ]);

        $result = $this->service->refundPayment('pi_test_123456', 2999);

        $this->assertTrue($result['success']);
        $this->assertEquals('re_test_123456', $result['refund_id']);
        $this->assertEquals('succeeded', $result['status']);
    }

    /**
     * @test
     */
    public function construct_webhook_event_validates_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        config(['services.stripe.mock' => false]);
        $this->service = new StripeService();

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_123']],
        ]);

        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, 'whsec_test_secret');
        $signatureHeader = "t={$timestamp},v1={$signature}";

        $result = $this->service->constructWebhookEvent($payload, $signatureHeader);

        $this->assertTrue($result['success']);
        $this->assertEquals('evt_test_123', $result['event']['id']);
    }

    /**
     * @test
     */
    public function construct_webhook_event_rejects_invalid_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        config(['services.stripe.mock' => false]);
        $this->service = new StripeService();

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
        ]);

        $timestamp = time();
        $signatureHeader = "t={$timestamp},v1=invalid_signature";

        $result = $this->service->constructWebhookEvent($payload, $signatureHeader);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid signature', $result['error']);
    }

    /**
     * @test
     */
    public function construct_webhook_event_rejects_old_timestamp(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        config(['services.stripe.mock' => false]);
        $this->service = new StripeService();

        $payload = json_encode(['id' => 'evt_test_123', 'type' => 'test']);
        $timestamp = time() - 400; // 6+ minutes ago
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, 'whsec_test_secret');
        $signatureHeader = "t={$timestamp},v1={$signature}";

        $result = $this->service->constructWebhookEvent($payload, $signatureHeader);

        $this->assertFalse($result['success']);
        $this->assertEquals('Timestamp outside tolerance', $result['error']);
    }

    /**
     * @test
     */
    public function create_payment_intent_requires_secret_key(): void
    {
        config(['services.stripe.secret_key' => '']);
        config(['services.stripe.mock' => false]);
        $this->service = new StripeService();

        $result = $this->service->createPaymentIntent(2999, 'USD');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not configured', $result['error']);
    }
}
