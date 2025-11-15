<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Models\Inventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProductsFromJsonSeeder
 * 
 * Idempotent seeder that imports products from database/data/products.json
 * 
 * Features:
 * - Skips products with existing SKUs (safe to re-run)
 * - Creates sellers and users if they don't exist
 * - Creates inventory records for each store
 * - Uses transactions per product for data integrity
 * 
 * Usage:
 *   php artisan db:seed --class=ProductsFromJsonSeeder
 *   php artisan migrate:fresh --seed  (includes this seeder if called in DatabaseSeeder)
 *   php artisan products:import-json  (if artisan command is created)
 * 
 * Notes:
 * - Requires stores to exist before running (run StoresSeeder first)
 * - Creates basic seller users with @myshop.test emails if missing
 * - Image URLs are external (Unsplash) - use products:cache-images to download locally
 */
class ProductsFromJsonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('data/products.json');
        
        if (!file_exists($jsonPath)) {
            $this->command->error("Products JSON file not found at: {$jsonPath}");
            return;
        }

        $productsData = json_decode(file_get_contents($jsonPath), true);
        
        if (!is_array($productsData)) {
            $this->command->error('Invalid JSON format in products.json');
            return;
        }

        $this->command->info("Found " . count($productsData) . " products to process...");

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($productsData as $index => $productData) {
            try {
                $result = $this->importProduct($productData, $index + 1);
                
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $failed++;
                $sku = $productData['sku'] ?? 'UNKNOWN';
                $this->command->warn("Failed to import product {$sku}: " . $e->getMessage());
                Log::error("Product import failed", [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->command->info("\n=== Import Summary ===");
        $this->command->info("Created: {$created}");
        $this->command->info("Skipped: {$skipped}");
        
        if ($failed > 0) {
            $this->command->warn("Failed: {$failed}");
        }
        
        $this->command->info("Total products in database: " . Product::count());
    }

    /**
     * Import a single product with transaction safety
     * 
     * @param array $data Product data from JSON
     * @param int $number Product number for logging
     * @return string 'created', 'skipped', or 'failed'
     */
    protected function importProduct(array $data, int $number): string
    {
        // Validate required fields
        $required = ['title', 'sku', 'price_cents', 'currency', 'seller_email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        $sku = strtoupper(trim($data['sku']));

        // Check if product already exists (idempotent)
        if (Product::where('sku', $sku)->exists()) {
            $this->command->line("[{$number}] Skipping existing SKU: {$sku}");
            return 'skipped';
        }

        return DB::transaction(function () use ($data, $sku, $number) {
            // Get or create seller
            $seller = $this->getOrCreateSeller($data['seller_email']);

            // Extract category and quick item status
            $category = $data['category'] ?? 'Electronics';
            $isQuickItem = $data['is_quick_item'] ?? false;

            // Create the product
            $product = Product::create([
                'seller_id' => $seller->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'price_cents' => $data['price_cents'],
                'currency' => $data['currency'] ?? 'USD',
                'sku' => $sku,
                'image_url' => $this->getStableImageUrl($data, $category, $sku),
                'category' => $category,
                'tags' => json_encode($data['tags'] ?? []),
                'is_quick_item' => $isQuickItem,
                'status' => 'active',
            ]);

            // Create inventory records for stores
            if (!empty($data['initial_stock_by_store'])) {
                $this->createInventoryRecords($product, $data['initial_stock_by_store']);
            }

            $this->command->info("[{$number}] Created: {$sku} - {$product->title}");
            
            return 'created';
        });
    }

    /**
     * Get existing seller or create new one with basic user
     * 
     * @param string $email Seller email
     * @return Seller
     */
    protected function getOrCreateSeller(string $email): Seller
    {
        // Try to find existing user by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Create new seller user
            $user = User::create([
                'name' => $this->generateSellerName($email),
                'email' => $email,
                'password' => bcrypt('password'), // Default password
                'role' => 'SELLER',
                'profile' => [],
            ]);

            $this->command->line("  → Created seller user: {$email}");
        }

        // Get or create seller profile
        $seller = Seller::where('user_id', $user->id)->first();

        if (!$seller) {
            $seller = Seller::create([
                'user_id' => $user->id,
                'store_name' => $this->generateStoreName($email),
                'description' => 'Auto-generated seller account',
                'verified' => true, // Auto-verify seeded sellers
                'rating' => 4.5,
            ]);

            $this->command->line("  → Created seller profile: {$seller->store_name}");
        }

        return $seller;
    }

    /**
     * Create inventory records for the product at each store.
     */
    private function createInventoryRecords(Product $product, array $stockByStore): void
    {
        $storeMap = Store::pluck('id', 'store_code')->toArray();

        $inventories = [];
        $now = now();

        foreach ($stockByStore as $storeCode => $quantity) {
            if (isset($storeMap[$storeCode])) {
                $inventories[] = [
                    'product_id' => $product->id,
                    'store_id' => $storeMap[$storeCode],
                    'quantity' => $quantity,
                    'reserved' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (count($inventories) > 0) {
            Inventory::insert($inventories);
        }
    }

    /**
     * Get stable Unsplash image URL for a category
     * Returns high-quality images that won't change on refresh
     * 
     * @param string $category Product category
     * @param string $sku Product SKU for uniqueness
     * @return string Unsplash image URL
     */
    protected function getCategoryImageUrl(string $category, string $sku): string
    {
        // Map categories to Unsplash photo IDs for stable images
        $categoryImages = [
            'Electronics' => [
                '1505740420928-5e560c06d30e', // headphones
                '1498049794561-7780e7231661', // tech gadgets
                '1550009158-9ebfd4d588a8', // smartphone
                '1484704849700-f032a568e944', // laptop
            ],
            'Fashion' => [
                '1523381210434-271e8be1f52b', // clothing rack
                '1445205170230-053b83016050', // fashion wear
                '1490481651871-ab68de25d43d', // shoes
                '1556905055-8f358a7a47b2', // accessories
            ],
            'Home' => [
                '1556228453-efd6c1ff04f6', // home interior
                '1484101403633-562f891dc89a', // furniture
                '1513694203232-719a280e022f', // living room
                '1540518614846-7eded433c457', // home decor
            ],
            'Beauty' => [
                '1596462502278-27bfdc403348', // cosmetics
                '1512496015851-a90fb38ba796', // beauty products
                '1522338242992-e1a54906a8da', // skincare
                '1571781926291-c477dfd542ee', // makeup
            ],
            'Sports' => [
                '1517836357463-d25dfeac3438', // sports equipment
                '1461896836934-ffe607ba8211', // fitness
                '1534258936925-c58bed479fcb', // basketball
                '1476480862126-209bfaa8edc8', // running
            ],
            'Toys' => [
                '1515488042361-ee00e0ddd4e4', // toys
                '1560015534-c104dfb2e7e7', // lego
                '1587912781053-603d89bbe9e9', // games
                '1558877385-8c08babf4ae1', // kids toys
            ],
            'Grocery' => [
                '1542838132-92c53300491e', // food
                '1488459716781-31db52582fe9', // groceries
                '1543362906-acfc16c67564', // vegetables
                '1490818387583-1baba5e638af', // snacks
            ],
            'Office' => [
                '1484480974693-6ca0a78fb36b', // desk setup
                '1531403009284-440f080d1e12', // office supplies
                '1586023492125-27b2c045efd7', // workspace
                '1544717684-6e5e44c76fc3', // stationery
            ],
            'Accessories' => [
                '1523293182086-7651a899d37f', // tech accessories
                '1591337676887-a217a6970a8a', // gadgets
                '1526738549149-8e07eca6c147', // watches
                '1561715276-a2d087060f1d', // bags
            ],
        ];

        // Get category images or use default
        $images = $categoryImages[$category] ?? $categoryImages['Electronics'];
        
        // Use SKU hash to consistently select an image
        $index = abs(crc32($sku)) % count($images);
        $photoId = $images[$index];
        
        // Return stable Unsplash URL with optimizations
        return "https://images.unsplash.com/photo-{$photoId}?w=800&h=600&fit=crop&q=80&auto=format";
    }

    /**
     * Get stable image URL - prefer JSON URL if valid, otherwise generate from category
     * 
     * @param array $data Product data from JSON
     * @param string $category Product category
     * @param string $sku Product SKU
     * @return string Image URL
     */
    protected function getStableImageUrl(array $data, string $category, string $sku): string
    {
        $jsonUrl = $data['image_url'] ?? null;
        
        // If JSON has a valid stable Unsplash URL, use it
        if ($jsonUrl && str_contains($jsonUrl, 'images.unsplash.com/photo-')) {
            return $jsonUrl;
        }
        
        // Otherwise, generate a stable category-based URL
        return $this->getCategoryImageUrl($category, $sku);
    }

    /**
     * Generate seller name from email
     * 
     * @param string $email
     * @return string
     */
    protected function generateSellerName(string $email): string
    {
        $username = explode('@', $email)[0];
        $name = str_replace(['.', '_', '-'], ' ', $username);
        return ucwords($name);
    }

    /**
     * Generate store name from email
     * 
     * @param string $email
     * @return string
     */
    protected function generateStoreName(string $email): string
    {
        $username = explode('@', $email)[0];
        $name = str_replace(['.', '_', '-'], ' ', $username);
        return ucwords($name) . "'s Store";
    }
}
