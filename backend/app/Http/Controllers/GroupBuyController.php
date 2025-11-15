<?php

namespace App\Http\Controllers;

use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * GroupBuyController handles Pinduoduo-style group buying campaigns.
 * 
 * Endpoints:
 * - POST /api/group-buys - Create new group buy
 * - GET /api/group-buys/{id} - Get group buy details
 * - POST /api/group-buys/{id}/join - Join group buy
 * - GET /api/group-buys/product/{productId} - Get active group buys for product
 */
class GroupBuyController extends Controller
{
    /**
     * List all active group buys.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = GroupBuy::with(['product', 'starter', 'members']);
        
        // Filter by status if provided
        if ($request->has('status')) {
            $status = strtoupper($request->input('status'));
            $query->where('status', $status);
        } else {
            // Default to active group buys only
            $query->where('status', 'ACTIVE')
                  ->where('expires_at', '>', now());
        }
        
        $groupBuys = $query->orderBy('created_at', 'desc')
                           ->paginate(20);

        return response()->json($groupBuys);
    }

    /**
     * Create new group buy campaign.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Validate product exists and is active
     * TODO: Check user hasn't already started active group buy for same product
     * TODO: Validate pricing (team_price < solo_price)
     * TODO: Auto-join creator to the group
     * TODO: Send notification to user's followers
     * TODO: Queue job to check expiration
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'target_size' => 'required|integer|min:2|max:100',
            'team_price_cents' => 'required|integer|min:0',
            'expires_in_hours' => 'required|integer|min:1|max:168', // Max 7 days
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $product = Product::findOrFail($request->product_id);
        
        // Use product's current price as solo_price
        $soloPriceCents = $product->price_cents;
        $teamPriceCents = $request->team_price_cents;

        // Validate team price is lower than solo price
        if ($teamPriceCents >= $soloPriceCents) {
            return response()->json([
                'error' => 'Team price must be lower than solo price',
            ], 422);
        }

        $user = $request->user();
        $expiresAt = now()->addHours($request->expires_in_hours);

        DB::beginTransaction();
        try {
            $groupBuy = GroupBuy::create([
                'product_id' => $product->id,
                'starter_user_id' => $user->id,
                'target_size' => $request->target_size,
                'team_price_cents' => $teamPriceCents,
                'solo_price_cents' => $soloPriceCents,
                'status' => 'ACTIVE',
                'expires_at' => $expiresAt,
            ]);

            // Auto-join the creator
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);

            DB::commit();

            // TODO: Queue job to check expiration at expires_at time
            // ProcessGroupBuyExpiration::dispatch($groupBuy)->delay($expiresAt);

            $groupBuy->load(['product', 'starter', 'members']);

            return response()->json([
                'group_buy' => $groupBuy,
                'message' => 'Group buy created successfully',
                'share_url' => url("/group-buys/{$groupBuy->id}"),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create group buy',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get group buy details with members.
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Add privacy controls (hide member details?)
     * TODO: Calculate time remaining
     * TODO: Show if current user has joined
     */
    public function show(int $id)
    {
        $groupBuy = GroupBuy::with([
            'product.seller',
            'starter',
            'members' => function ($query) {
                $query->latest('joined_at');
            },
        ])->findOrFail($id);

        $timeRemaining = $groupBuy->expires_at->diffInSeconds(now(), false);
        $isExpired = $timeRemaining < 0;

        return response()->json([
            'group_buy' => $groupBuy,
            'member_count' => $groupBuy->member_count,
            'progress_percentage' => $groupBuy->progress_percentage,
            'is_target_reached' => $groupBuy->isTargetReached(),
            'is_expired' => $isExpired,
            'time_remaining_seconds' => max(0, -$timeRemaining),
            'current_price_cents' => $groupBuy->getCurrentPriceCents(),
            'savings' => $groupBuy->savings,
        ]);
    }

    /**
     * Join an active group buy.
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Check if user already joined
     * TODO: Check if group buy is still active
     * TODO: Check if target size already reached
     * TODO: Send notification to group members
     * TODO: If target reached, trigger fulfillment process
     */
    public function join(Request $request, int $id)
    {
        $groupBuy = GroupBuy::findOrFail($id);
        $user = $request->user();

        // Check if group buy is active
        if (!$groupBuy->isActive()) {
            return response()->json([
                'error' => 'This group buy is no longer active',
            ], 400);
        }

        // Check if user already joined
        $alreadyJoined = GroupBuyMember::where('group_buy_id', $groupBuy->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyJoined) {
            return response()->json([
                'error' => 'You have already joined this group buy',
            ], 400);
        }

        DB::beginTransaction();
        try {
            GroupBuyMember::create([
                'group_buy_id' => $groupBuy->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'paid' => false,
            ]);

            // Reload to get updated member count
            $groupBuy->load('members');

            // Check if target reached
            $targetReached = $groupBuy->isTargetReached();
            
            if ($targetReached && $groupBuy->status === 'ACTIVE') {
                // TODO: Trigger fulfillment process
                // For now, just mark as fulfilled
                $groupBuy->update(['status' => 'FULFILLED']);
                
                // TODO: Queue payment processing job
                // ProcessGroupBuyPayment::dispatch($groupBuy);
                
                // TODO: Send success notifications to all members
            }

            DB::commit();

            return response()->json([
                'message' => 'Successfully joined group buy',
                'group_buy' => $groupBuy,
                'target_reached' => $targetReached,
                'member_count' => $groupBuy->member_count,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to join group buy',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active group buys for a specific product.
     * 
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Add pagination
     * TODO: Sort by closest to target or soonest expiration
     */
    public function getByProduct(int $productId)
    {
        $product = Product::findOrFail($productId);

        $groupBuys = GroupBuy::where('product_id', $productId)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->with(['starter', 'members'])
            ->orderBy('expires_at', 'asc')
            ->get()
            ->map(function ($groupBuy) {
                return [
                    'id' => $groupBuy->id,
                    'starter' => $groupBuy->starter,
                    'target_size' => $groupBuy->target_size,
                    'member_count' => $groupBuy->member_count,
                    'progress_percentage' => $groupBuy->progress_percentage,
                    'team_price_cents' => $groupBuy->team_price_cents,
                    'solo_price_cents' => $groupBuy->solo_price_cents,
                    'savings' => $groupBuy->savings,
                    'expires_at' => $groupBuy->expires_at,
                    'time_remaining_seconds' => max(0, $groupBuy->expires_at->diffInSeconds(now())),
                ];
            });

        return response()->json([
            'product' => $product,
            'group_buys' => $groupBuys,
            'count' => $groupBuys->count(),
        ]);
    }
}
