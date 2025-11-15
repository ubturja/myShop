<?php

namespace Database\Factories;

use App\Models\CartItem;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'store_id' => null,
        ];
    }

    /**
     * Indicate that the cart item is for a quick item with a store.
     */
    public function withStore(): static
    {
        return $this->state(fn (array $attributes) => [
            'store_id' => \App\Models\Store::factory(),
        ]);
    }
}
