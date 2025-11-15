<?php

namespace App\Services;

use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * GroupBuyService - Business logic for group buying campaigns.
 * 
 * Handles:
 * - Expiration checking and processing
 * - Status transitions (ACTIVE → FULFILLED/FAILED)
 * - Member count computation
 * - Event triggering for notifications
 */
class GroupBuyService
{
    /**
     * Process expired group buys.
     * 
     * Finds all active group buys past their expiration time,
     * checks if target was reached, and updates status accordingly.
     * 
     * @return array Statistics about processed group buys
     */
    public function processExpirations(): array
    {
        $expiredGroupBuys = $this->getExpiredGroupBuys();
        
        $stats = [
            'total' => $expiredGroupBuys->count(),
            'fulfilled' => 0,
            'failed' => 0,
            'errors' => 0,
        ];

        foreach ($expiredGroupBuys as $groupBuy) {
            try {
                $result = $this->processExpiredGroupBuy($groupBuy);
                
                if ($result === 'FULFILLED') {
                    $stats['fulfilled']++;
                } elseif ($result === 'FAILED') {
                    $stats['failed']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('GroupBuyService: Failed to process expired group buy', [
                    'group_buy_id' => $groupBuy->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('GroupBuyService: Processed expirations', $stats);

        return $stats;
    }

    /**
     * Get all expired group buys that need processing.
     * 
     * @return Collection<GroupBuy>
     */
    protected function getExpiredGroupBuys(): Collection
    {
        return GroupBuy::where('status', 'ACTIVE')
            ->where('expires_at', '<=', now())
            ->with(['members', 'product', 'starter'])
            ->get();
    }

    /**
     * Process a single expired group buy.
     * 
     * @param GroupBuy $groupBuy
     * @return string New status ('FULFILLED' or 'FAILED')
     */
    public function processExpiredGroupBuy(GroupBuy $groupBuy): string
    {
        DB::beginTransaction();

        try {
            // Get current member count
            $memberCount = $this->getMemberCount($groupBuy);
            
            // Determine new status based on target reached
            $newStatus = $memberCount >= $groupBuy->target_size ? 'FULFILLED' : 'FAILED';
            
            // Update group buy status
            $groupBuy->update(['status' => $newStatus]);
            
            Log::info('GroupBuyService: Processed expired group buy', [
                'group_buy_id' => $groupBuy->id,
                'product_id' => $groupBuy->product_id,
                'member_count' => $memberCount,
                'target_size' => $groupBuy->target_size,
                'new_status' => $newStatus,
            ]);

            // Trigger appropriate event
            if ($newStatus === 'FULFILLED') {
                $this->triggerFulfilledEvent($groupBuy, $memberCount);
            } else {
                $this->triggerFailedEvent($groupBuy, $memberCount);
            }

            DB::commit();

            return $newStatus;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get accurate member count for a group buy.
     * 
     * @param GroupBuy $groupBuy
     * @return int
     */
    public function getMemberCount(GroupBuy $groupBuy): int
    {
        return GroupBuyMember::where('group_buy_id', $groupBuy->id)->count();
    }

    /**
     * Check if a group buy should be marked as fulfilled.
     * 
     * @param GroupBuy $groupBuy
     * @return bool
     */
    public function shouldBeFulfilled(GroupBuy $groupBuy): bool
    {
        return $this->getMemberCount($groupBuy) >= $groupBuy->target_size;
    }

    /**
     * Mark a group buy as fulfilled.
     * 
     * @param GroupBuy $groupBuy
     * @return bool
     */
    public function markAsFulfilled(GroupBuy $groupBuy): bool
    {
        if ($groupBuy->status === 'FULFILLED') {
            return true; // Already fulfilled
        }

        $memberCount = $this->getMemberCount($groupBuy);
        
        if ($memberCount < $groupBuy->target_size) {
            return false; // Target not reached
        }

        DB::beginTransaction();

        try {
            $groupBuy->update(['status' => 'FULFILLED']);
            $this->triggerFulfilledEvent($groupBuy, $memberCount);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GroupBuyService: Failed to mark as fulfilled', [
                'group_buy_id' => $groupBuy->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Mark a group buy as failed.
     * 
     * @param GroupBuy $groupBuy
     * @return bool
     */
    public function markAsFailed(GroupBuy $groupBuy): bool
    {
        if ($groupBuy->status === 'FAILED') {
            return true; // Already failed
        }

        DB::beginTransaction();

        try {
            $memberCount = $this->getMemberCount($groupBuy);
            $groupBuy->update(['status' => 'FAILED']);
            $this->triggerFailedEvent($groupBuy, $memberCount);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GroupBuyService: Failed to mark as failed', [
                'group_buy_id' => $groupBuy->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Trigger event when group buy is fulfilled.
     * 
     * @param GroupBuy $groupBuy
     * @param int $memberCount
     * @return void
     */
    protected function triggerFulfilledEvent(GroupBuy $groupBuy, int $memberCount): void
    {
        Log::info('GroupBuyService: Group buy fulfilled', [
            'group_buy_id' => $groupBuy->id,
            'product_id' => $groupBuy->product_id,
            'member_count' => $memberCount,
            'target_size' => $groupBuy->target_size,
            'team_price_cents' => $groupBuy->team_price_cents,
        ]);

        // TODO: Dispatch GroupBuyFulfilled event for notifications
        // event(new GroupBuyFulfilled($groupBuy, $memberCount));
    }

    /**
     * Trigger event when group buy fails.
     * 
     * @param GroupBuy $groupBuy
     * @param int $memberCount
     * @return void
     */
    protected function triggerFailedEvent(GroupBuy $groupBuy, int $memberCount): void
    {
        Log::info('GroupBuyService: Group buy failed', [
            'group_buy_id' => $groupBuy->id,
            'product_id' => $groupBuy->product_id,
            'member_count' => $memberCount,
            'target_size' => $groupBuy->target_size,
            'shortfall' => $groupBuy->target_size - $memberCount,
        ]);

        // TODO: Dispatch GroupBuyFailed event for notifications
        // event(new GroupBuyFailed($groupBuy, $memberCount));
    }

    /**
     * Get statistics for active group buys.
     * 
     * @return array
     */
    public function getActiveStats(): array
    {
        $activeGroupBuys = GroupBuy::where('status', 'ACTIVE')->get();

        return [
            'total_active' => $activeGroupBuys->count(),
            'expiring_soon' => $activeGroupBuys->filter(function ($gb) {
                return $gb->expires_at->isBefore(now()->addHours(24));
            })->count(),
            'target_reached' => $activeGroupBuys->filter(function ($gb) {
                return $this->shouldBeFulfilled($gb);
            })->count(),
        ];
    }

    /**
     * Get group buys expiring soon (within specified hours).
     * 
     * @param int $hours
     * @return Collection<GroupBuy>
     */
    public function getExpiringSoon(int $hours = 24): Collection
    {
        return GroupBuy::where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addHours($hours))
            ->with(['members', 'product', 'starter'])
            ->get();
    }

    /**
     * Auto-fulfill group buys that reached target before expiration.
     * 
     * @return int Number of group buys fulfilled
     */
    public function autoFulfillReachedTargets(): int
    {
        $groupBuys = GroupBuy::where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->with('members')
            ->get();

        $fulfilled = 0;

        foreach ($groupBuys as $groupBuy) {
            if ($this->shouldBeFulfilled($groupBuy)) {
                if ($this->markAsFulfilled($groupBuy)) {
                    $fulfilled++;
                }
            }
        }

        return $fulfilled;
    }
}
