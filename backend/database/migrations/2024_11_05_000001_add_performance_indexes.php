<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Database Performance Optimization - Compound Indexes
     * 
     * Adds composite indexes based on common query patterns to improve performance.
     */
    public function up(): void
    {
        // Group Buys - Compound indexes for common queries
        $this->createIndexIfNotExists('group_buys', 'idx_gb_product_status_expires', 'product_id, status, expires_at');
        
        // Cart Items - User + product + store lookups
        $this->createIndexIfNotExists('cart_items', 'idx_cart_user_product_store', 'user_id, product_id, store_id');
        $this->createIndexIfNotExists('cart_items', 'idx_cart_user_store', 'user_id, store_id');
        
        // Gamification - Daily spin constraint queries
        $this->createIndexIfNotExists('gamification_events', 'idx_gam_user_type_created', 'user_id, type, created_at');
        $this->createIndexIfNotExists('gamification_events', 'idx_gam_user_created', 'user_id, created_at');
        
        // Provenance - Product event timeline
        $this->createIndexIfNotExists('provenance_events', 'idx_prov_product_created', 'product_id, created_at');
        
        // NFT Tokens - User NFT listing with sort
        $this->createIndexIfNotExists('nft_tokens', 'idx_nft_user_minted', 'user_id, minted_at');
        
        // Products - Status-based filtering
        $this->createIndexIfNotExists('products', 'idx_products_status_category', 'status, category');
        $this->createIndexIfNotExists('products', 'idx_products_status_quick', 'status, is_quick_item');
        $this->createIndexIfNotExists('products', 'idx_products_status_seller', 'status, seller_id');
        
        // Orders - User and seller order management
        $this->createIndexIfNotExists('orders', 'idx_orders_user_status', 'user_id, status');
        $this->createIndexIfNotExists('orders', 'idx_orders_seller_status', 'seller_id, status');
        $this->createIndexIfNotExists('orders', 'idx_orders_user_created', 'user_id, created_at');
        
        // Order Items - Order product lookups
        $this->createIndexIfNotExists('order_items', 'idx_order_items_order_product', 'order_id, product_id');
        
        // Inventories - Stock availability queries
        $this->createIndexIfNotExists('inventories', 'idx_inv_product_quantity', 'product_id, quantity');
        $this->createIndexIfNotExists('inventories', 'idx_inv_store_quantity', 'store_id, quantity');
    }
    
    /**
     * Helper to create index only if it doesn't exist
     */
    private function createIndexIfNotExists(string $table, string $indexName, string $columns): void
    {
        $exists = DB::select("SHOW INDEXES FROM $table WHERE Key_name = ?", [$indexName]);
        if (empty($exists)) {
            DB::statement("CREATE INDEX $indexName ON $table ($columns)");
            echo "✓ Created index: $indexName on $table\n";
        } else {
            echo "→ Index already exists: $indexName on $table\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_gb_product_status_expires ON group_buys');
        DB::statement('DROP INDEX IF EXISTS idx_cart_user_product_store ON cart_items');
        DB::statement('DROP INDEX IF EXISTS idx_cart_user_store ON cart_items');
        DB::statement('DROP INDEX IF EXISTS idx_gam_user_type_created ON gamification_events');
        DB::statement('DROP INDEX IF EXISTS idx_gam_user_created ON gamification_events');
        DB::statement('DROP INDEX IF EXISTS idx_prov_product_created ON provenance_events');
        DB::statement('DROP INDEX IF EXISTS idx_nft_user_minted ON nft_tokens');
        DB::statement('DROP INDEX IF EXISTS idx_products_status_category ON products');
        DB::statement('DROP INDEX IF EXISTS idx_products_status_quick ON products');
        DB::statement('DROP INDEX IF EXISTS idx_products_status_seller ON products');
        DB::statement('DROP INDEX IF EXISTS idx_orders_user_status ON orders');
        DB::statement('DROP INDEX IF EXISTS idx_orders_seller_status ON orders');
        DB::statement('DROP INDEX IF EXISTS idx_orders_user_created ON orders');
        DB::statement('DROP INDEX IF EXISTS idx_order_items_order_product ON order_items');
        DB::statement('DROP INDEX IF EXISTS idx_inv_product_quantity ON inventories');
        DB::statement('DROP INDEX IF EXISTS idx_inv_store_quantity ON inventories');
    }
};
