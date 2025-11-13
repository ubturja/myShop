<?php

namespace Database\Factories;

use App\Models\GamificationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GamificationEvent>
 */
class GamificationEventFactory extends Factory
{
    protected $model = GamificationEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['SPIN', 'QUIZ', 'REFERRAL_BONUS', 'DAILY_CHECK_IN'];
        $type = fake()->randomElement($types);

        $rewardTypes = ['discount', 'points', 'free_shipping'];
        $rewardType = fake()->randomElement($rewardTypes);

        $reward = [
            'type' => $rewardType,
            'label' => $this->getLabelForType($rewardType),
            'value' => $this->getValueForType($rewardType),
            'color' => fake()->hexColor(),
        ];

        if (in_array($rewardType, ['discount', 'free_shipping'])) {
            $reward['code'] = strtoupper(fake()->lexify('????') . fake()->numerify('####'));
            $reward['expires_at'] = now()->addDays(30)->toIso8601String();
        }

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'reward' => $reward,
            'used' => fake()->boolean(30), // 30% chance of being used
            'used_at' => fake()->boolean(30) ? fake()->dateTimeBetween('-30 days', 'now') : null,
        ];
    }

    /**
     * Indicate that the event is a spin.
     */
    public function spin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'SPIN',
        ]);
    }

    /**
     * Indicate that the event is unused.
     */
    public function unused(): static
    {
        return $this->state(fn (array $attributes) => [
            'used' => false,
            'used_at' => null,
        ]);
    }

    /**
     * Indicate that the event is used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'used' => true,
            'used_at' => now(),
        ]);
    }

    /**
     * Get label for reward type.
     */
    private function getLabelForType(string $type): string
    {
        return match ($type) {
            'discount' => fake()->randomElement(['5% Off', '10% Off', '15% Off', '20% Off']),
            'points' => fake()->randomElement(['50 Points', '100 Points', '200 Points']),
            'free_shipping' => 'Free Shipping',
            default => 'Reward',
        };
    }

    /**
     * Get value for reward type.
     */
    private function getValueForType(string $type): int
    {
        return match ($type) {
            'discount' => fake()->randomElement([500, 1000, 1500, 2000]),
            'points' => fake()->randomElement([50, 100, 200]),
            'free_shipping' => 1,
            default => 0,
        };
    }
}
