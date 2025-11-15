<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Seller>
 */
class SellerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate a placeholder logo URL using picsum.photos
        $logoId = fake()->numberBetween(1, 1000);
        $logoUrl = "https://picsum.photos/seed/logo{$logoId}/200/200";
        
        return [
            'user_id' => User::factory()->seller(),
            'store_name' => fake()->company() . ' Store',
            'verified' => fake()->boolean(70), // 70% verified
            'rating' => fake()->randomFloat(2, 3.5, 5.0),
            'description' => fake()->paragraph(),
            'logo_url' => $logoUrl,
            'business_info' => [
                'legal_name' => fake()->company(),
                'tax_id' => fake()->numerify('##-#######'),
                'registration_number' => fake()->numerify('REG-######'),
                'business_address' => fake()->address(),
            ],
        ];
    }

    /**
     * Indicate that the seller is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified' => true,
        ]);
    }

    /**
     * Indicate that the seller is unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified' => false,
        ]);
    }
}
