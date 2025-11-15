<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Inventory;
use Database\Seeders\StoresSeeder;
use Database\Seeders\ProductsFromJsonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that products can be seeded from JSON file
     */
    public function test_products_can_be_seeded_from_json(): void
    {
        // Create stores first (required for inventory)
        $this->seed(StoresSeeder::class);
        
        // Run the products seeder
        $this->seed(ProductsFromJsonSeeder::class);

        // Assert at least 60 products were created
        $this->assertGreaterThanOrEqual(60, Product::count(), 'Expected at least 60 products to be seeded');
    }

    /**
     * Test that sample products from JSON exist
     */
    public function test_sample_products_exist_with_correct_data(): void
    {
        $this->seed(StoresSeeder::class);
        $this->seed(ProductsFromJsonSeeder::class);

        // Check the first product from JSON
        $atlasHeadphones = Product::where('sku', 'AWH-1000-BLK')->first();
        $this->assertNotNull($atlasHeadphones, 'Atlas Headphones product should exist');
        $this->assertEquals('Atlas Wireless Noise-Cancelling Headphones', $atlasHeadphones->title);
        $this->assertEquals(12999, $atlasHeadphones->price_cents);
        $this->assertEquals('USD', $atlasHeadphones->currency);
        $this->assertEquals('Electronics', $atlasHeadphones->category);
        $this->assertIsArray($atlasHeadphones->tags);
        $this->assertContains('headphones', $atlasHeadphones->tags);
    }

        /**
     * Test that inventory records are created for products with initial stock data.
     */
    public function test_inventory_records_created_for_products(): void
    {
        $this->seed(StoresSeeder::class);
        $this->seed(ProductsFromJsonSeeder::class);

        $charger = Product::where('sku', 'QC18W-USB-C')->first();
        $this->assertNotNull($charger, 'QuickCharge product should exist');

        // Check inventory at STORE_001 (should have 200 units per JSON)
        if ($charger && $charger->isQuickItem) {
            $store001 = Store::where('store_code', 'STORE_001')->first();
            $this->assertNotNull($store001, 'Store STORE_001 should exist');

            $inventory = Inventory::where('product_id', $charger->id)
                ->where('store_id', $store001->id)
                ->first();
            $this->assertNotNull($inventory, 'Inventory should exist in STORE_001');
            $this->assertEquals(200, $inventory->quantity);
        }
    }

    /**
     * Test that products endpoint returns seeded products
     */
    public function test_products_endpoint_returns_seeded_products(): void
    {
        $this->seed(StoresSeeder::class);
        $this->seed(ProductsFromJsonSeeder::class);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'price_cents',
                        'currency',
                        'sku',
                        'image_url',
                        'category',
                        'tags',
                        'is_quick_item',
                    ]
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

        // Verify pagination has products
        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Products API should return data');
        $this->assertGreaterThanOrEqual(15, count($data), 'Should return at least 15 products per page');
    }

    /**
     * Test that specific product can be retrieved by SKU
     */
    public function test_specific_product_can_be_retrieved(): void
    {
        $this->seed(StoresSeeder::class);
        $this->seed(ProductsFromJsonSeeder::class);

        $product = Product::where('sku', 'AWH-1000-BLK')->first();
        $this->assertNotNull($product);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'product' => [
                    'sku' => 'AWH-1000-BLK',
                    'title' => 'Atlas Wireless Noise-Cancelling Headphones',
                    'category' => 'Electronics',
                ]
            ]);
    }

    /**
     * Test that seeder is idempotent (can be run multiple times)
     */
    public function test_seeder_is_idempotent(): void
    {
        $this->seed(StoresSeeder::class);
        
        // Run seeder first time
        $this->seed(ProductsFromJsonSeeder::class);
        $firstCount = Product::count();

        // Run seeder second time
        $this->seed(ProductsFromJsonSeeder::class);
        $secondCount = Product::count();

        // Count should be the same (no duplicates)
        $this->assertEquals($firstCount, $secondCount, 'Running seeder twice should not create duplicate products');
    }

    /**
     * Test that products have required relationships
     */
    public function test_products_have_required_relationships(): void
    {
        $this->seed(StoresSeeder::class);
        $this->seed(ProductsFromJsonSeeder::class);

        $product = Product::where('sku', 'AWH-1000-BLK')->first();
        $this->assertNotNull($product);

        // Test seller relationship
        $this->assertNotNull($product->seller_id, 'Product should have a seller_id');
        $this->assertNotNull($product->seller, 'Product should have a seller relationship');
        $this->assertNotNull($product->seller->user, 'Seller should have a user relationship');
    }

    /**
     * Test that categories are diverse
     */
    public function test_products_have_diverse_categories(): void
    {
        $this->seed(StoresSeeder::class);
        $this->seed(ProductsFromJsonSeeder::class);

        $categories = Product::distinct('category')->pluck('category')->toArray();

        // Should have at least 8 categories
        $this->assertGreaterThanOrEqual(8, count($categories), 'Should have at least 8 product categories');

        // Check for specific expected categories
        $expectedCategories = ['Electronics', 'Fashion', 'Home', 'Beauty', 'Sports', 'Toys', 'Grocery', 'Office'];
        foreach ($expectedCategories as $expected) {
            $this->assertContains($expected, $categories, "Category '{$expected}' should exist");
        }
    }
}
