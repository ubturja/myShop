<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\GamificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class GamificationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can spin the wheel successfully.
     */
    public function test_user_can_spin_wheel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/gamification/spin');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'prize' => [
                    'type',
                    'label',
                    'value',
                    'color',
                ],
                'event_id',
                'next_spin_at',
            ]);

        // Verify event was persisted
        $this->assertDatabaseHas('gamification_events', [
            'user_id' => $user->id,
            'type' => 'SPIN',
        ]);
    }

    /**
     * Test user cannot spin twice in one day.
     */
    public function test_user_cannot_spin_twice_per_day(): void
    {
        $user = User::factory()->create();

        // First spin - should succeed
        $response1 = $this->actingAs($user)
            ->postJson('/api/gamification/spin');
        $response1->assertStatus(200);

        // Second spin - should fail
        $response2 = $this->actingAs($user)
            ->postJson('/api/gamification/spin');
        $response2->assertStatus(429)
            ->assertJson([
                'message' => 'You have already spun today. Come back tomorrow!',
            ])
            ->assertJsonStructure(['next_spin_at']);
    }

    /**
     * Test user can spin again the next day.
     */
    public function test_user_can_spin_again_next_day(): void
    {
        $user = User::factory()->create();

        // Create a spin from yesterday by manually setting created_at
        $event = GamificationEvent::create([
            'user_id' => $user->id,
            'type' => 'SPIN',
            'reward' => ['type' => 'discount', 'value' => 500],
        ]);
        
        // Force the created_at timestamp to yesterday
        $event->created_at = Carbon::yesterday();
        $event->save();

        // Spin today - should succeed because yesterday's spin was in a different day
        $response = $this->actingAs($user)
            ->postJson('/api/gamification/spin');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'prize' => ['type', 'label', 'value', 'color'],
                'event_id',
                'next_spin_at',
            ]);
    }

    /**
     * Test prize includes discount code when applicable.
     */
    public function test_discount_prize_includes_code(): void
    {
        $user = User::factory()->create();

        // Run multiple times to increase chance of getting discount
        $gotDiscount = false;
        for ($i = 0; $i < 10; $i++) {
            // Reset events for this test
            GamificationEvent::where('user_id', $user->id)->delete();

            $response = $this->actingAs($user)
                ->postJson('/api/gamification/spin');

            if ($response->json('prize.type') === 'discount') {
                $gotDiscount = true;
                $response->assertJsonStructure([
                    'prize' => [
                        'code',
                        'expires_at',
                    ],
                ]);
                break;
            }
        }

        $this->assertTrue($gotDiscount, 'Should eventually get a discount prize');
    }

    /**
     * Test all prizes result in successful database persist.
     */
    public function test_all_prizes_persist_correctly(): void
    {
        $user = User::factory()->create();

        // Spin once and verify event is persisted
        $response = $this->actingAs($user)
            ->postJson('/api/gamification/spin');

        $response->assertStatus(200);
        $eventId = $response->json('event_id');
        $prizeType = $response->json('prize.type');

        // Verify event was persisted
        $event = GamificationEvent::find($eventId);
        $this->assertNotNull($event);
        $this->assertEquals('SPIN', $event->type);
        $this->assertEquals($user->id, $event->user_id);
        
        // If it's "no_prize", it should be marked as used immediately
        if ($prizeType === 'no_prize') {
            $this->assertTrue($event->used);
            $this->assertNotNull($event->used_at);
        }
    }

    /**
     * Test rewards endpoint returns user's gamification history.
     */
    public function test_rewards_endpoint_returns_history(): void
    {
        $user = User::factory()->create();

        // Create some events
        GamificationEvent::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/gamification/rewards');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'rewards',
                'can_spin_today',
                'next_spin_at',
            ])
            ->assertJsonCount(3, 'rewards');
    }

    /**
     * Test rewards endpoint indicates if user can spin today.
     */
    public function test_rewards_endpoint_shows_spin_availability(): void
    {
        $user = User::factory()->create();

        // No spins yet - should be able to spin
        $response1 = $this->actingAs($user)
            ->getJson('/api/gamification/rewards');
        $response1->assertJson(['can_spin_today' => true]);

        // Create spin today
        GamificationEvent::create([
            'user_id' => $user->id,
            'type' => 'SPIN',
            'reward' => ['type' => 'discount', 'value' => 500],
        ]);

        // Should not be able to spin
        $response2 = $this->actingAs($user)
            ->getJson('/api/gamification/rewards');
        $response2->assertJson(['can_spin_today' => false])
            ->assertJsonStructure(['next_spin_at']);
    }

    /**
     * Test prizes endpoint returns available prizes.
     */
    public function test_prizes_endpoint_returns_prize_list(): void
    {
        $response = $this->getJson('/api/gamification/prizes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'prizes' => [
                    '*' => [
                        'label',
                        'color',
                        'probability',
                    ],
                ],
            ]);

        // Verify probabilities sum to 100
        $prizes = $response->json('prizes');
        $totalProbability = array_sum(array_column($prizes, 'probability'));
        $this->assertEquals(100, $totalProbability, 'Probabilities should sum to 100');
    }

    /**
     * Test spin requires authentication.
     */
    public function test_spin_requires_authentication(): void
    {
        $response = $this->postJson('/api/gamification/spin');
        $response->assertStatus(401);
    }

    /**
     * Test rewards requires authentication.
     */
    public function test_rewards_requires_authentication(): void
    {
        $response = $this->getJson('/api/gamification/rewards');
        $response->assertStatus(401);
    }

    /**
     * Test prize selection follows probability distribution (statistical test).
     */
    public function test_prize_distribution_follows_probabilities(): void
    {
        $user = User::factory()->create();
        $results = [];

        // Spin many times to check distribution
        for ($i = 0; $i < 100; $i++) {
            GamificationEvent::where('user_id', $user->id)->delete();

            $response = $this->actingAs($user)
                ->postJson('/api/gamification/spin');

            $prizeLabel = $response->json('prize.label');
            $results[$prizeLabel] = ($results[$prizeLabel] ?? 0) + 1;
        }

        // Most common prizes should be 5% and 10% off (highest probabilities)
        arsort($results);
        $topPrizes = array_slice(array_keys($results), 0, 2, true);

        // At least one of the top prizes should be 5% or 10% off
        $hasExpectedTopPrize = in_array('5% Off', $topPrizes) || in_array('10% Off', $topPrizes);
        $this->assertTrue($hasExpectedTopPrize, 'Prize distribution should favor higher probability prizes');
    }
}
