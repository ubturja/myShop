<?php

namespace Database\Factories;

use App\Models\NFTToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NFTToken>
 */
class NFTTokenFactory extends Factory
{
    protected $model = NFTToken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_id' => fake()->unique()->numerify('#####'),
            'contract_address' => '0x' . fake()->regexify('[0-9a-f]{40}'),
            'metadata_uri' => 'ipfs://Qm' . fake()->regexify('[A-Za-z0-9]{44}'),
            'tx_hash' => '0x' . fake()->regexify('[0-9a-f]{64}'),
            'minted_at' => now(),
        ];
    }

    /**
     * Indicate that the NFT has no transaction hash (pending mint).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_hash' => null,
        ]);
    }

    /**
     * Indicate that the NFT was minted for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
