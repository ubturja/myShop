<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class UpdateProductImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:update-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update product images to use stable Unsplash URLs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating product images to stable URLs...');
        
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
        
        $products = Product::all();
        $updated = 0;
        
        foreach ($products as $product) {
            // Skip if already has stable URL
            if ($product->getRawOriginal('image_url') && 
                str_contains($product->getRawOriginal('image_url'), 'images.unsplash.com/photo-')) {
                continue;
            }
            
            $category = $product->category;
            $images = $categoryImages[$category] ?? $categoryImages['Electronics'];
            
            // Use SKU hash to consistently select an image
            $index = abs(crc32($product->sku)) % count($images);
            $photoId = $images[$index];
            
            $stableUrl = "https://images.unsplash.com/photo-{$photoId}?w=800&h=600&fit=crop&q=80&auto=format";
            
            $product->update(['image_url' => $stableUrl]);
            $updated++;
            
            $this->info("Updated: {$product->sku} -> {$stableUrl}");
        }
        
        $this->info("✓ Updated {$updated} product images");
        $this->info("Total products: {$products->count()}");
        
        return Command::SUCCESS;
    }
}
