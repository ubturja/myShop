<?php

namespace Database\Factories;

use App\Models\GroupBuy;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupBuy>
 */
class GroupBuyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $soloPriceCents = fake()->numberBetween(5000, 50000); // $50 to $500
        $teamPriceCents = (int) ($soloPriceCents * fake()->randomFloat(2, 0.6, 0.85)); // 15-40% discount

        return [
            'product_id' => Product::factory(),
            'starter_user_id' => User::factory(),
            'target_size' => fake()->randomElement([3, 5, 10, 15, 20]),
            'team_price_cents' => $teamPriceCents,
            'solo_price_cents' => $soloPriceCents,
            'status' => 'ACTIVE',
            'expires_at' => now()->addHours(fake()->numberBetween(6, 72)), // 6 to 72 hours
        ];
    }

    /**
     * Indicate that the group buy is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ACTIVE',
            'expires_at' => now()->addHours(fake()->numberBetween(12, 48)),
        ]);
    }

    /**
     * Indicate that the group buy is fulfilled.
     */
    public function fulfilled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'FULFILLED',
            'expires_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }

    /**
     * Indicate that the group buy has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'FAILED',
            'expires_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }

    /**
     * Indicate that the group buy has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'EXPIRED',
            'expires_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }
}
