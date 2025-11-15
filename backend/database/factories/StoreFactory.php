<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate realistic coordinates for major US cities
        $cities = [
            ['name' => 'New York', 'lat' => 40.7128, 'lng' => -74.0060],
            ['name' => 'Los Angeles', 'lat' => 34.0522, 'lng' => -118.2437],
            ['name' => 'Chicago', 'lat' => 41.8781, 'lng' => -87.6298],
            ['name' => 'Houston', 'lat' => 29.7604, 'lng' => -95.3698],
            ['name' => 'Phoenix', 'lat' => 33.4484, 'lng' => -112.0740],
            ['name' => 'Philadelphia', 'lat' => 39.9526, 'lng' => -75.1652],
            ['name' => 'San Antonio', 'lat' => 29.4241, 'lng' => -98.4936],
            ['name' => 'San Diego', 'lat' => 32.7157, 'lng' => -117.1611],
            ['name' => 'Dallas', 'lat' => 32.7767, 'lng' => -96.7970],
            ['name' => 'San Jose', 'lat' => 37.3382, 'lng' => -121.8863],
        ];

        $city = fake()->randomElement($cities);
        
        // Add small random offset to coordinates (within ~5km radius)
        $latOffset = fake()->randomFloat(4, -0.045, 0.045);
        $lngOffset = fake()->randomFloat(4, -0.045, 0.045);

        return [
            'name' => fake()->company() . ' ' . fake()->randomElement(['Express', 'Quick', 'Mart', 'Store', 'Shop', 'Market']),
            'latitude' => $city['lat'] + $latOffset,
            'longitude' => $city['lng'] + $lngOffset,
            'address' => fake()->streetAddress(),
            'city' => $city['name'],
            'postal_code' => fake()->postcode(),
            'opening_hours' => [
                'monday' => '08:00-22:00',
                'tuesday' => '08:00-22:00',
                'wednesday' => '08:00-22:00',
                'thursday' => '08:00-22:00',
                'friday' => '08:00-23:00',
                'saturday' => '09:00-23:00',
                'sunday' => '10:00-20:00',
            ],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the store is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
