<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * OrderController handles order retrieval and management.
 */
class OrderController extends Controller
{
    /**
     * Get all orders for authenticated user.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $orders = Order::where('user_id', $user->id)
            ->with(['orderItems.product', 'seller'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return response()->json($orders);
    }
    
    /**
     * Get a single order by ID.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $order = Order::where('user_id', $user->id)
            ->where('id', $id)
            ->with(['orderItems.product', 'seller'])
            ->firstOrFail();
            
        return response()->json($order);
    }
}
