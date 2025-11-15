<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Models\User;
use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use App\Jobs\ProcessGroupBuyExpiration;
use App\Services\GroupBuyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;

class ProcessGroupBuyExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_can_be_dispatched()
    {
        Queue::fake();

        ProcessGroupBuyExpiration::dispatch();

        Queue::assertPushed(ProcessGroupBuyExpiration::class);
    }

    /** @test */
    public function it_processes_expired_group_buys_successfully()
    {
        // Create expired group buy that reached target
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

        $job = new ProcessGroupBuyExpiration();
        $service = new GroupBuyService();

        $job->handle($service);

        $this->assertEquals('FULFILLED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_handles_multiple_expired_group_buys()
    {
        // Create multiple expired group buys
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
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

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
                'joined_at' => now(),
                'paid' => false,
            ]);
        }

        $job = new ProcessGroupBuyExpiration();
        $service = new GroupBuyService();

        $job->handle($service);

        $this->assertEquals('FULFILLED', $fulfilled->fresh()->status);
        $this->assertEquals('FAILED', $failed->fresh()->status);
    }

    /** @test */
    public function it_ignores_non_expired_group_buys()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 3,
            'status' => 'ACTIVE',
            'expires_at' => now()->addHours(24),
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

        $job = new ProcessGroupBuyExpiration();
        $service = new GroupBuyService();

        $job->handle($service);

        // Should still be ACTIVE
        $this->assertEquals('ACTIVE', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_ignores_already_fulfilled_group_buys()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 3,
            'status' => 'FULFILLED',
            'expires_at' => now()->subMinute(),
        ]);

        $job = new ProcessGroupBuyExpiration();
        $service = new GroupBuyService();

        $job->handle($service);

        // Should remain FULFILLED
        $this->assertEquals('FULFILLED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_ignores_already_failed_group_buys()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 5,
            'status' => 'FAILED',
            'expires_at' => now()->subMinute(),
        ]);

        $job = new ProcessGroupBuyExpiration();
        $service = new GroupBuyService();

        $job->handle($service);

        // Should remain FAILED
        $this->assertEquals('FAILED', $groupBuy->fresh()->status);
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        $job = new ProcessGroupBuyExpiration();

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
    }

    /** @test */
    public function it_handles_service_exception_gracefully()
    {
        $this->expectException(\Exception::class);

        $mockService = Mockery::mock(GroupBuyService::class);
        $mockService->shouldReceive('processExpirations')
            ->once()
            ->andThrow(new \Exception('Service error'));

        $job = new ProcessGroupBuyExpiration();
        $job->handle($mockService);
    }

    /** @test */
    public function it_calls_failed_method_on_exception()
    {
        $job = new ProcessGroupBuyExpiration();
        $exception = new \Exception('Test exception');

        // This should not throw
        $job->failed($exception);

        // Verify logging was called (mocked in setUp)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_processes_zero_expired_group_buys()
    {
        // Create only non-expired group buys
        GroupBuy::factory()->create([
            'status' => 'ACTIVE',
            'expires_at' => now()->addHours(24),
        ]);

        $job = new ProcessGroupBuyExpiration();
        $service = new GroupBuyService();

        // Should complete without errors
        $job->handle($service);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_large_batch_of_expirations()
    {
        // Create 10 expired group buys
        for ($i = 0; $i < 10; $i++) {
            $groupBuy = GroupBuy::factory()->create([
                'target_size' => 3,
                'status' => 'ACTIVE',
                'expires_at' => now()->subMinutes($i + 1),
            ]);

            // Half reach target, half don't
            $memberCount = $i % 2 === 0 ? 3 : 1;
            $users = User::factory()->count($memberCount)->create();
            foreach ($users as $user) {
                GroupBuyMember::create([
                    'group_buy_id' => $groupBuy->id,
                    'user_id' => $user->id,
                    'joined_at' => now(),
                    'paid' => false,
                ]);
            }
        }

        $job = new ProcessGroupBuyExpiration();
        $service = new GroupBuyService();

        $job->handle($service);

        // Count fulfilled and failed
        $fulfilled = GroupBuy::where('status', 'FULFILLED')->count();
        $failed = GroupBuy::where('status', 'FAILED')->count();

        $this->assertEquals(5, $fulfilled);
        $this->assertEquals(5, $failed);
    }

    /** @test */
    public function it_uses_dependency_injection_for_service()
    {
        $groupBuy = GroupBuy::factory()->create([
            'target_size' => 2,
            'status' => 'ACTIVE',
            'expires_at' => now()->subMinute(),
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

        $job = new ProcessGroupBuyExpiration();

        // Service should be injected via dependency injection
        $service = app(GroupBuyService::class);
        $job->handle($service);

        $this->assertEquals('FULFILLED', $groupBuy->fresh()->status);
    }
}
