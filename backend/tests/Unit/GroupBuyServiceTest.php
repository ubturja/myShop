<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use App\Services\GroupBuyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GroupBuyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GroupBuyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupBuyService();
    }

    /** @test */
    public function it_gets_member_count_for_group_buy()
    {
        $groupBuy = GroupBuy::factory()->create(['target_size' => 5]);
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $count = $this->service->getMemberCount($groupBuy);

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_determines_if_group_buy_should_be_fulfilled()
    {
        $groupBuy = GroupBuy::factory()->create(['target_size' => 3]);
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $this->assertTrue($this->service->shouldBeFulfilled($groupBuy));
    }

    /** @test */
    public function it_determines_if_group_buy_should_not_be_fulfilled()
    {
        $groupBuy = GroupBuy::factory()->create(['target_size' => 5]);
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $this->assertFalse($this->service->shouldBeFulfilled($groupBuy));
    }

    /** @test */
    public function it_processes_expired_group_buy_that_reached_target()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 3,
            'status' => 'ACTIVE',
            'expires_at' => now()->subMinute(),
        ]);

        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now()->subHour(),
                'paid' => false,
            ]);
        }

        $result = $this->service->processExpiredGroupBuy($groupBuy);

        $this->assertEquals('FULFILLED', $result);
        $this->assertEquals('FULFILLED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_processes_expired_group_buy_that_failed_to_reach_target()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 5,
            'status' => 'ACTIVE',
            'expires_at' => now()->subMinute(),
        ]);

        $users = User::factory()->count(2)->create();
        foreach ($users as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now()->subHour(),
                'paid' => false,
            ]);
        }

        $result = $this->service->processExpiredGroupBuy($groupBuy);

        $this->assertEquals('FAILED', $result);
        $this->assertEquals('FAILED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_processes_multiple_expirations()
    {
        // Create expired group buy that reached target
        $fulfilled = GroupBuy::factory()->create([
            'target_size' => 2,
            'status' => 'ACTIVE',
            'expires_at' => now()->subMinute(),
        ]);
        $users1 = User::factory()->count(2)->create();
        foreach ($users1 as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $fulfilled->id,
                'user_id' => $user->id,
                'joined_at' => now()->subHour(),
                'paid' => false,
            ]);
        }

        // Create expired group buy that failed
        $failed = GroupBuy::factory()->create([
            'target_size' => 5,
            'status' => 'ACTIVE',
            'expires_at' => now()->subMinute(),
        ]);
        $users2 = User::factory()->count(2)->create();
        foreach ($users2 as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $failed->id,
                'user_id' => $user->id,
                'joined_at' => now()->subHour(),
                'paid' => false,
            ]);
        }

        // Create active group buy not yet expired (should be ignored)
        GroupBuy::factory()->create([
            'target_size' => 3,
            'status' => 'ACTIVE',
            'expires_at' => now()->addHour(),
        ]);

        $stats = $this->service->processExpirations();

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['fulfilled']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(0, $stats['errors']);

        $this->assertEquals('FULFILLED', $fulfilled->fresh()->status);
        $this->assertEquals('FAILED', $failed->fresh()->status);
    }

    /** @test */
    public function it_marks_group_buy_as_fulfilled()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 3,
            'status' => 'ACTIVE',
        ]);

        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $result = $this->service->markAsFulfilled($groupBuy);

        $this->assertTrue($result);
        $this->assertEquals('FULFILLED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_does_not_mark_as_fulfilled_if_target_not_reached()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 5,
            'status' => 'ACTIVE',
        ]);

        $users = User::factory()->count(2)->create();
        foreach ($users as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $result = $this->service->markAsFulfilled($groupBuy);

        $this->assertFalse($result);
        $this->assertEquals('ACTIVE', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_returns_true_if_already_fulfilled()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 3,
            'status' => 'FULFILLED',
        ]);

        $result = $this->service->markAsFulfilled($groupBuy);

        $this->assertTrue($result);
        $this->assertEquals('FULFILLED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_marks_group_buy_as_failed()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 5,
            'status' => 'ACTIVE',
        ]);

        $users = User::factory()->count(2)->create();
        foreach ($users as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $result = $this->service->markAsFailed($groupBuy);

        $this->assertTrue($result);
        $this->assertEquals('FAILED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_returns_true_if_already_failed()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 5,
            'status' => 'FAILED',
        ]);

        $result = $this->service->markAsFailed($groupBuy);

        $this->assertTrue($result);
        $this->assertEquals('FAILED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_gets_active_stats()
    {
        // Create active group buys
        $active1 = GroupBuy::factory()->create([
            'target_size' => 3,
            'status' => 'ACTIVE',
            'expires_at' => now()->addHours(12), // Expiring soon
        ]);
        $users1 = User::factory()->count(3)->create();
        foreach ($users1 as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $active1->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $active2 = GroupBuy::factory()->create([
            'target_size' => 5,
            'status' => 'ACTIVE',
            'expires_at' => now()->addDays(2),
        ]);
        $users2 = User::factory()->count(2)->create();
        foreach ($users2 as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $active2->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        // Create fulfilled (should be ignored)
        GroupBuy::factory()->create(['status' => 'FULFILLED']);

        $stats = $this->service->getActiveStats();

        $this->assertEquals(2, $stats['total_active']);
        $this->assertEquals(1, $stats['expiring_soon']);
        $this->assertEquals(1, $stats['target_reached']);
    }

    /** @test */
    public function it_gets_group_buys_expiring_soon()
    {
        // Expiring in 12 hours
        $expiringSoon = GroupBuy::factory()->create([
            'status' => 'ACTIVE',
            'expires_at' => now()->addHours(12),
        ]);

        // Expiring in 2 days
        GroupBuy::factory()->create([
            'status' => 'ACTIVE',
            'expires_at' => now()->addDays(2),
        ]);

        // Already expired
        GroupBuy::factory()->create([
            'status' => 'ACTIVE',
            'expires_at' => now()->subHour(),
        ]);

        $groupBuys = $this->service->getExpiringSoon(24);

        $this->assertCount(1, $groupBuys);
        $this->assertEquals($expiringSoon->id, $groupBuys->first()->id);
    }

    /** @test */
    public function it_auto_fulfills_group_buys_that_reached_target()
    {
        // Active group buy that reached target
        $toFulfill = GroupBuy::factory()->create([
            'target_size' => 3,
            'status' => 'ACTIVE',
            'expires_at' => now()->addHours(24),
        ]);
        $users1 = User::factory()->count(3)->create();
        foreach ($users1 as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $toFulfill->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        // Active group buy that hasn't reached target
        $notReady = GroupBuy::factory()->create([
            'target_size' => 5,
            'status' => 'ACTIVE',
            'expires_at' => now()->addHours(24),
        ]);
        $users2 = User::factory()->count(2)->create();
        foreach ($users2 as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $notReady->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $count = $this->service->autoFulfillReachedTargets();

        $this->assertEquals(1, $count);
        $this->assertEquals('FULFILLED', $toFulfill->fresh()->status);
        $this->assertEquals('ACTIVE', $notReady->fresh()->status);
    }

    /** @test */
    public function it_handles_group_buy_with_no_members()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 5,
            'status' => 'ACTIVE',
            'expires_at' => now()->subMinute(),
        ]);

        $result = $this->service->processExpiredGroupBuy($groupBuy);

        $this->assertEquals('FAILED', $result);
        $this->assertEquals('FAILED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_handles_group_buy_with_exact_target_count()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 3,
            'status' => 'ACTIVE',
            'expires_at' => now()->subMinute(),
        ]);

        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $result = $this->service->processExpiredGroupBuy($groupBuy);

        $this->assertEquals('FULFILLED', $result);
        $this->assertEquals('FULFILLED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_handles_group_buy_with_more_than_target_members()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 3,
            'status' => 'ACTIVE',
            'expires_at' => now()->subMinute(),
        ]);

        $users = User::factory()->count(5)->create();
        foreach ($users as $user) {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $result = $this->service->processExpiredGroupBuy($groupBuy);

        $this->assertEquals('FULFILLED', $result);
        $this->assertEquals('FULFILLED', $groupBuy->fresh()->status);
    }
}
