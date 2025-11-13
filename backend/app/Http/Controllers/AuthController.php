<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OtpVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Mail\OtpVerificationMail;

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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create unverified user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'CUSTOMER',
            'email_verified_at' => null,
            'profile' => [
                'wallet_address' => $request->wallet_address,
            ],
        ]);

        // Generate and send OTP
        $this->sendOtp($user);

        return response()->json([
            'message' => 'Registration successful. Please verify your email with the OTP sent.',
            'user_id' => $user->id,
            'requires_verification' => true
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
    /**
     * Send OTP to user's email
     */
    protected function sendOtp($user)
    {
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(15);

        // Delete any existing OTPs for this user
        OtpVerification::where('email', $user->email)->delete();

        // Create new OTP
        OtpVerification::create([
            'email' => $user->email,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ]);

        // Send OTP via email
        Mail::to($user->email)->send(new OtpVerificationMail($otp));

        return $otp;
    }

    /**
     * Verify OTP for email verification
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $otpRecord = OtpVerification::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'message' => 'Invalid or expired OTP',
            ], 422);
        }

        // Mark user as verified
        $user = User::where('email', $request->email)->firstOrFail();
        $user->email_verified_at = now();
        $user->save();

        // Delete the used OTP
        $otpRecord->delete();

        // Generate and return token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();
        
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
            ], 400);
        }

        $this->sendOtp($user);

        return response()->json([
            'message' => 'OTP has been resent to your email',
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            $this->sendOtp($user);
            return response()->json([
                'requires_verification' => true,
                'user_id' => $user->id,
                'message' => 'Please verify your email with the OTP sent to your email.',
            ], 403);
        }

        // Revoke old tokens (optional, can keep multiple device sessions)
        // $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

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
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Web3 wallet authentication
     */
    public function web3Auth(Request $request)
    {
        $request->validate([
            'wallet_address' => 'required|string',
            'signature' => 'required|string',
            'message' => 'required|string',
        ]);

        // In a real implementation, verify the signature here
        // This is a simplified example
        $message = "Sign this message to login with your wallet.\nNonce: " . Str::random(32);
        
        // Verify signature logic would go here
        // For now, we'll just check if the wallet exists or create a new user
        
        $user = User::where('profile->wallet_address', $request->wallet_address)->first();
        
        if (!$user) {
            // Create new user with wallet
            $user = User::create([
                'name' => 'Wallet User ' . substr($request->wallet_address, 0, 8),
                'email' => $request->wallet_address . '@wallet',
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'profile' => [
                    'wallet_address' => $request->wallet_address,
                ],
            ]);
        }

        $token = $user->createToken('web3_auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
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
