<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * ProductController handles product listing and management.
 * 
 * Endpoints:
 * - GET /api/products - List products with filters
 * - GET /api/products/{id} - Get single product
 * - POST /api/seller/products - Create product (seller only)
 * - PUT /api/seller/products/{id} - Update product (seller only)
 * - DELETE /api/seller/products/{id} - Delete product (seller only)
 */
class ProductController extends Controller
{
    /**
     * List products with optional filtering and pagination.
     * 
     * Query params:
     * - category: Filter by category
     * - q: Search query (title/description)
     * - is_quick_item: Filter quick commerce items (1/0)
     * - page: Page number
     * - per_page: Items per page (default 20, max 100)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Integrate with AI service for semantic search
     * TODO: Add price range filters
     * TODO: Add sorting options (price, rating, newest)
     * TODO: Cache popular queries
     */
    public function index(Request $request)
    {
        $query = Product::query()->where('status', 'ACTIVE');

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by quick commerce availability
        if ($request->has('is_quick_item')) {
            $query->where('is_quick_item', (bool) $request->is_quick_item);
        }

        // Search by keyword (full-text search)
        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhereJsonContains('tags', $searchTerm);
            });
        }

        // Filter by seller
        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }

        // Eager load seller relationship
        $query->with('seller.user');

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Get single product by ID with related data.
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Load inventory for quick commerce items
     * TODO: Load active group buys
     * TODO: Load provenance events
     * TODO: Track product views for analytics
     */
    public function show(int $id)
    {
        $product = Product::with([
            'seller.user',
            'groupBuys' => function ($query) {
                $query->where('status', 'ACTIVE')
                      ->where('expires_at', '>', now());
            },
            'inventories.store', // For quick commerce
        ])->findOrFail($id);

        return response()->json([
            'product' => $product,
        ]);
    }

    /**
     * Create new product (seller only).
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Validate seller_id matches authenticated user's seller profile
     * TODO: Upload and validate image
     * TODO: Generate SKU automatically if not provided
     * TODO: Queue job to generate AI embeddings
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price_cents' => 'required|integer|min:0',
            'currency' => 'sometimes|string|size:3',
            'sku' => 'required|string|unique:products,sku',
            'image_url' => 'sometimes|url',
            'category' => 'required|string',
            'tags' => 'sometimes|array',
            'is_quick_item' => 'sometimes|boolean',
            'status' => 'sometimes|in:ACTIVE,DRAFT,ARCHIVED',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get seller_id from authenticated user
        $user = $request->user();
        if (!$user->seller) {
            return response()->json([
                'error' => 'User does not have a seller profile',
            ], 403);
        }

        $product = Product::create([
            'seller_id' => $user->seller->id,
            'title' => $request->title,
            'description' => $request->description,
            'price_cents' => $request->price_cents,
            'currency' => $request->currency ?? 'USD',
            'sku' => $request->sku,
            'image_url' => $request->image_url,
            'category' => $request->category,
            'tags' => $request->tags ?? [],
            'is_quick_item' => $request->is_quick_item ?? false,
            'status' => $request->status ?? 'DRAFT',
        ]);

        // TODO: Dispatch job to generate AI embeddings
        // SyncProductEmbedding::dispatch($product);

        return response()->json([
            'product' => $product,
            'message' => 'Product created successfully',
        ], 201);
    }

    /**
     * Update existing product (seller only).
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Verify product belongs to authenticated seller
     * TODO: Handle image upload/update
     * TODO: Re-generate embeddings if content changed
     */
    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        // Verify ownership
        $user = $request->user();
        if (!$user->seller || $product->seller_id !== $user->seller->id) {
            return response()->json([
                'error' => 'Unauthorized to update this product',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price_cents' => 'sometimes|integer|min:0',
            'currency' => 'sometimes|string|size:3',
            'sku' => 'sometimes|string|unique:products,sku,' . $id,
            'image_url' => 'sometimes|url',
            'category' => 'sometimes|string',
            'tags' => 'sometimes|array',
            'is_quick_item' => 'sometimes|boolean',
            'status' => 'sometimes|in:ACTIVE,DRAFT,ARCHIVED',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $product->update($request->only([
            'title', 'description', 'price_cents', 'currency', 'sku',
            'image_url', 'category', 'tags', 'is_quick_item', 'status',
        ]));

        // TODO: Re-sync embeddings if content changed
        // if ($request->hasAny(['title', 'description', 'category', 'tags'])) {
        //     SyncProductEmbedding::dispatch($product);
        // }

        return response()->json([
            'product' => $product,
            'message' => 'Product updated successfully',
        ]);
    }

    /**
     * Delete product (seller only).
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * 
     * TODO: Verify product belongs to authenticated seller
     * TODO: Check for active orders/group buys before deletion
     * TODO: Soft delete instead of hard delete
     */
    public function destroy(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        // Verify ownership
        $user = $request->user();
        if (!$user->seller || $product->seller_id !== $user->seller->id) {
            return response()->json([
                'error' => 'Unauthorized to delete this product',
            ], 403);
        }

        // TODO: Check for active group buys or pending orders
        // For now, just archive instead of delete
        $product->update(['status' => 'ARCHIVED']);

        return response()->json([
            'message' => 'Product archived successfully',
        ]);
    }
}
