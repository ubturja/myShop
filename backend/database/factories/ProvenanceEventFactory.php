<?php

namespace Database\Factories;

use App\Models\ProvenanceEvent;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProvenanceEvent>
 */
class ProvenanceEventFactory extends Factory
{
    protected $model = ProvenanceEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = [
            'MANUFACTURED',
            'QUALITY_CHECK',
            'PACKAGED',
            'SHIPPED',
            'IN_TRANSIT',
            'CUSTOMS',
            'OUT_FOR_DELIVERY',
            'DELIVERED',
        ];

        return [
            'product_id' => Product::factory(),
            'event_type' => fake()->randomElement($eventTypes),
            'data' => [
                'location' => fake()->city() . ', ' . fake()->country(),
                'timestamp' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d\TH:i:s\Z'),
                'notes' => fake()->sentence(),
            ],
            'ipfs_hash' => 'Qm' . fake()->regexify('[A-Za-z0-9]{44}'),
            'tx_hash' => fake()->optional(0.3)->regexify('0x[a-f0-9]{64}'),
        ];
    }

    /**
     * Indicate that the event has not been anchored to blockchain.
     */
    public function notAnchored(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_hash' => null,
        ]);
    }

    /**
     * Indicate that the event has been anchored to blockchain.
     */
    public function anchored(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_hash' => '0x' . fake()->regexify('[a-f0-9]{64}'),
        ]);
    }

    /**
     * Indicate that the event is for a specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }

    /**
     * Set a specific event type.
     */
    public function eventType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => $type,
        ]);
    }
}
