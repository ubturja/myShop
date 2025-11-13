<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\GroupBuyController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\Web3Controller;
use App\Http\Controllers\ProvenanceController;
use App\Http\Controllers\GamificationController;
use App\Http\Controllers\StripeWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Webhooks (no authentication - verified by signature)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'service' => 'myShop API',
    ]);
});

// Authentication routes
Route::prefix('auth')->group(function () {
    // Standard email/password auth
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // OTP verification
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    
    // Web3 wallet auth
    Route::post('/web3/login', [AuthController::class, 'web3Auth']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

// Public group buy browsing (no auth required)
Route::prefix('group-buys')->group(function () {
    Route::get('/', [GroupBuyController::class, 'index']);
    Route::get('/{id}', [GroupBuyController::class, 'show']);
    Route::get('/product/{productId}', [GroupBuyController::class, 'getByProduct']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User profile
    Route::get('/user/profile', [AuthController::class, 'profile']);
    
    // Products (public read, authenticated write)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    
    // Seller product management
    Route::prefix('seller')->middleware('can:isSeller')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    });
    
    // Group buying (authenticated actions)
    Route::prefix('group-buys')->group(function () {
        Route::post('/', [GroupBuyController::class, 'create']);
        Route::post('/{id}/join', [GroupBuyController::class, 'join']);
    });
    
    // Cart endpoints
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);
    
    // Checkout
    Route::post('/checkout', [CheckoutController::class, 'process']);
    
    // AI endpoints (with PII redaction middleware)
    Route::post('/ai/embeddings/sync', [AIController::class, 'syncEmbeddings']);
    Route::get('/ai/recommendations', [AIController::class, 'recommendations'])->middleware('sanitize');
    Route::post('/ai/assistant', [AIController::class, 'assistant'])->middleware('sanitize');
    
    // Web3/NFT endpoints
    Route::get('/web3/health', [Web3Controller::class, 'health']);
    Route::post('/web3/mint-loyalty', [Web3Controller::class, 'mintLoyalty']);
    Route::get('/web3/tokens/{id}', [Web3Controller::class, 'show']);
    Route::get('/web3/my-tokens', [Web3Controller::class, 'myTokens']);
    
    // Provenance endpoints
    Route::get('/provenance/health', [ProvenanceController::class, 'health']);
    Route::post('/provenance/events', [ProvenanceController::class, 'store']);
    Route::get('/provenance/events/{id}', [ProvenanceController::class, 'show']);
    Route::get('/provenance/stats', [ProvenanceController::class, 'stats']);
    Route::get('/products/{productId}/provenance', [ProvenanceController::class, 'getByProduct']);
    
    // Stores & Inventory (TODO: implement StoreController)
    // Route::get('/stores', [StoreController::class, 'index']);
    // Route::get('/stores/{id}/inventory', [StoreController::class, 'inventory']);
    
    // Gamification
    Route::post('/gamification/spin', [GamificationController::class, 'spin']);
    Route::get('/gamification/rewards', [GamificationController::class, 'rewards']);
});

// Public endpoints (no auth required)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/gamification/prizes', [GamificationController::class, 'prizes']);
