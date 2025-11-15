<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * AuthController handles user authentication and profile management.
 * 
 * Uses Laravel Sanctum for API token authentication.
 * 
 * Endpoints:
 * - POST /api/auth/register - Register new user
 * - POST /api/auth/login - Login and get token
 * - POST /api/auth/logout - Logout and revoke token
 * - POST /api/auth/refresh - Refresh token
 * - GET /api/user/profile - Get authenticated user profile
 */
class AuthController extends Controller
{
    /**
     * Register a new user.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Add email verification flow
     * TODO: Add rate limiting to prevent abuse
     * TODO: Check for referral code and create Referral record
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|in:CUSTOMER,SELLER',
            // Seller-specific fields
            'business_name' => 'required_if:role,SELLER|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $role = $request->role ?? 'CUSTOMER';
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role,
            'profile' => [],
        ]);

        // If registering as seller, create seller profile
        if ($role === 'SELLER' && $request->business_name) {
            \App\Models\Seller::create([
                'user_id' => $user->id,
                'store_name' => $request->business_name,
                'description' => '',
                'verified' => false,
                'rating' => 0.0,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->fresh(['seller']),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Login user and create token.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Add failed login attempt tracking
     * TODO: Add device fingerprinting for security
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'sometimes|in:ADMIN,CUSTOMER,SELLER',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Role enforcement: if role specified, must match user's actual role
        if ($request->has('role') && $user->role !== $request->role) {
            return response()->json([
                'error' => 'Role mismatch',
                'message' => 'Your account does not have access to this login portal. Please use the correct login page for your account type.',
            ], 403);
        }

        // Revoke old tokens (optional, can keep multiple device sessions)
        // $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        // Load seller relationship if applicable
        $user->load('seller');

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Logout user and revoke token.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Refresh authentication token.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Implement token rotation with refresh tokens
     * For now, just returns current user info
     */
    public function refresh(Request $request)
    {
        // Sanctum doesn't have built-in refresh tokens
        // For production, consider implementing custom refresh token logic
        // or using Laravel Passport instead
        
        $user = $request->user();
        
        return response()->json([
            'user' => $user,
            'message' => 'Token is still valid',
        ]);
    }

    /**
     * Get authenticated user profile.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Include related data (orders, cart, loyalty points, etc.)
     * TODO: Implement profile update endpoint
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        // Load relationships if needed
        $user->load(['seller', 'nftTokens', 'orders' => function ($query) {
            $query->latest()->limit(5);
        }]);

        return response()->json([
            'user' => $user,
        ]);
    }
}
