<?php

namespace App\Http\Controllers;

use App\Models\GamificationEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    /**
     * Prize pool configuration with probabilities.
     * Probabilities should sum to 100.
     */
    private const PRIZE_POOL = [
        [
            'type' => 'discount',
            'label' => '5% Off',
            'value' => 500, // cents or 5%
            'code_prefix' => 'SPIN5',
            'probability' => 30,
            'color' => '#10b981', // green
        ],
        [
            'type' => 'discount',
            'label' => '10% Off',
            'value' => 1000,
            'code_prefix' => 'SPIN10',
            'probability' => 25,
            'color' => '#3b82f6', // blue
        ],
        [
            'type' => 'discount',
            'label' => '15% Off',
            'value' => 1500,
            'code_prefix' => 'SPIN15',
            'probability' => 15,
            'color' => '#8b5cf6', // purple
        ],
        [
            'type' => 'discount',
            'label' => '20% Off',
            'value' => 2000,
            'code_prefix' => 'SPIN20',
            'probability' => 10,
            'color' => '#f59e0b', // amber
        ],
        [
            'type' => 'points',
            'label' => '50 Points',
            'value' => 50,
            'probability' => 15,
            'color' => '#06b6d4', // cyan
        ],
        [
            'type' => 'free_shipping',
            'label' => 'Free Shipping',
            'value' => 1,
            'code_prefix' => 'SHIPFREE',
            'probability' => 4,
            'color' => '#ec4899', // pink
        ],
        [
            'type' => 'no_prize',
            'label' => 'Try Again!',
            'value' => 0,
            'probability' => 1,
            'color' => '#6b7280', // gray
        ],
    ];

    /**
     * Spin the wheel for a reward.
     * Constraint: One free spin per day per user.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function spin(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has already spun today (start of day to now)
        $todayStart = Carbon::today()->startOfDay();
        $tomorrowStart = Carbon::tomorrow()->startOfDay();

        $spinToday = GamificationEvent::where('user_id', $user->id)
            ->where('type', 'SPIN')
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $tomorrowStart)
            ->first();

        if ($spinToday) {
            return response()->json([
                'message' => 'You have already spun today. Come back tomorrow!',
                'next_spin_at' => $tomorrowStart->toIso8601String(),
            ], 429); // Too Many Requests
        }

        // Select a prize based on probability
        $prize = $this->selectPrize();

        // Generate unique code for discount/shipping rewards
        $rewardData = [
            'type' => $prize['type'],
            'label' => $prize['label'],
            'value' => $prize['value'],
            'color' => $prize['color'],
        ];

        if (in_array($prize['type'], ['discount', 'free_shipping'])) {
            $rewardData['code'] = $this->generateRewardCode($prize['code_prefix']);
            $rewardData['expires_at'] = Carbon::now()->addDays(30)->toIso8601String();
        }

        // Persist gamification event
        $event = GamificationEvent::create([
            'user_id' => $user->id,
            'type' => 'SPIN',
            'reward' => $rewardData,
            'used' => $prize['type'] === 'no_prize', // Automatically mark "no prize" as used
            'used_at' => $prize['type'] === 'no_prize' ? now() : null,
        ]);

        return response()->json([
            'message' => 'Spin successful!',
            'prize' => $rewardData,
            'event_id' => $event->id,
            'next_spin_at' => $tomorrowStart->toIso8601String(),
        ], 200);
    }

    /**
     * Get user's rewards/gamification history.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function rewards(Request $request): JsonResponse
    {
        $user = $request->user();

        $rewards = GamificationEvent::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Check if user can spin today
        $todayStart = Carbon::today();
        $todayEnd = Carbon::tomorrow();

        $spinToday = GamificationEvent::where('user_id', $user->id)
            ->where('type', 'SPIN')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->exists();

        return response()->json([
            'rewards' => $rewards,
            'can_spin_today' => !$spinToday,
            'next_spin_at' => $spinToday ? $todayEnd->toIso8601String() : null,
        ], 200);
    }

    /**
     * Get available prizes for displaying the wheel.
     * 
     * @return JsonResponse
     */
    public function prizes(): JsonResponse
    {
        $prizes = collect(self::PRIZE_POOL)->map(function ($prize) {
            return [
                'label' => $prize['label'],
                'color' => $prize['color'],
                'probability' => $prize['probability'],
            ];
        });

        return response()->json([
            'prizes' => $prizes,
        ], 200);
    }

    /**
     * Select a prize based on probability weights.
     * 
     * @return array
     */
    private function selectPrize(): array
    {
        $rand = rand(1, 100);
        $cumulative = 0;

        foreach (self::PRIZE_POOL as $prize) {
            $cumulative += $prize['probability'];
            if ($rand <= $cumulative) {
                return $prize;
            }
        }

        // Fallback (should never reach here if probabilities sum to 100)
        return self::PRIZE_POOL[0];
    }

    /**
     * Generate a unique reward code.
     * 
     * @param string $prefix
     * @return string
     */
    private function generateRewardCode(string $prefix): string
    {
        return strtoupper($prefix . '_' . substr(md5(uniqid()), 0, 8));
    }
}
