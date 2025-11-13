<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_handles_payment_succeeded(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'payment_intent_id' => 'pi_test_123',
            'payment_status' => 'pending',
        ]);

        $response = $this->withHeaders(['Stripe-Signature' => 'mock'])
            ->postJson('/api/webhooks/stripe', [
                'id' => 'evt_test_123',
                'type' => 'payment_intent.succeeded',
                'data' => [
                    'object' => [
                        'id' => 'pi_test_123',
                        'amount' => 2999,
                        'payment_method_types' => ['card'],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('succeeded', $order->payment_status);
    }
}
