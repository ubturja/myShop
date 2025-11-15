<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Seller;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'seller_id' => Seller::factory(),
            'store_id' => null,
            'total_cents' => fake()->numberBetween(1000, 50000),
            'currency' => 'USD',
            'status' => fake()->randomElement(['PENDING', 'CONFIRMED', 'SHIPPED', 'DELIVERED', 'CANCELLED']),
            'fulfillment_type' => fake()->randomElement(['STANDARD', 'QUICK_LOCAL', 'GROUP_BUY']),
            'eta' => fake()->numberBetween(30, 120),
            'tx_hash' => null,
            'payment_intent_id' => null,
            'payment_status' => 'pending',
            'payment_method_details' => null,
            'notes' => null,
        ];
    }

    /**
     * Order with quick_local fulfillment and store.
     */
    public function quickLocal(): static
    {
        return $this->state(fn (array $attributes) => [
            'fulfillment_type' => 'quick_local',
            'store_id' => Store::factory(),
            'eta' => fake()->numberBetween(15, 60),
        ]);
    }

    /**
     * Order with group_buy fulfillment.
     */
    public function groupBuy(): static
    {
        return $this->state(fn (array $attributes) => [
            'fulfillment_type' => 'group_buy',
        ]);
    }

    /**
     * Order with standard fulfillment.
     */
    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'fulfillment_type' => 'standard',
            'eta' => null,
        ]);
    }

    /**
     * Order with specific payment intent.
     */
    public function withPaymentIntent(string $paymentIntentId, string $status = 'pending'): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_intent_id' => $paymentIntentId,
            'payment_status' => $status,
        ]);
    }
}
