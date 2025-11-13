<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['Electronics', 'Fashion', 'Home & Garden', 'Sports', 'Books', 'Toys', 'Food & Beverage', 'Beauty', 'Automotive', 'Health'];
        $category = fake()->randomElement($categories);
        
        $tags = $this->generateTags($category);
        
        return [
            'seller_id' => Seller::factory(),
            'title' => $this->generateProductTitle($category),
            'description' => fake()->paragraphs(3, true),
            'price_cents' => fake()->numberBetween(999, 99999), // $9.99 to $999.99
            'currency' => 'USD',
            'sku' => strtoupper(fake()->bothify('???-#####')),
            'image_url' => fake()->imageUrl(640, 480, $category, true),
            'category' => $category,
            'tags' => $tags,
            'is_quick_item' => fake()->boolean(30), // 30% are quick items
            'status' => fake()->randomElement(['ACTIVE', 'ACTIVE', 'ACTIVE', 'DRAFT']), // 75% active
        ];
    }

    /**
     * Generate product title based on category.
     */
    private function generateProductTitle(string $category): string
    {
        $titles = [
            'Electronics' => ['Wireless Headphones', 'Smart Watch', 'Laptop Stand', '4K Webcam', 'Bluetooth Speaker', 'Gaming Mouse', 'USB-C Hub', 'Portable Charger'],
            'Fashion' => ['Cotton T-Shirt', 'Denim Jeans', 'Running Shoes', 'Leather Jacket', 'Summer Dress', 'Wool Sweater', 'Canvas Sneakers', 'Silk Scarf'],
            'Home & Garden' => ['LED Desk Lamp', 'Ceramic Planter', 'Memory Foam Pillow', 'Bamboo Cutting Board', 'Stainless Steel Kettle', 'Wall Clock', 'Area Rug', 'Storage Basket'],
            'Sports' => ['Yoga Mat', 'Resistance Bands', 'Water Bottle', 'Gym Bag', 'Jump Rope', 'Foam Roller', 'Tennis Racket', 'Bicycle Helmet'],
            'Books' => ['Mystery Novel', 'Cooking Guide', 'Self-Help Book', 'Science Fiction', 'Biography', 'Travel Journal', 'Art Book', 'Children\'s Story'],
            'Toys' => ['Building Blocks', 'Action Figure', 'Puzzle Set', 'Board Game', 'Stuffed Animal', 'Remote Control Car', 'Art Supplies', 'Musical Instrument'],
            'Food & Beverage' => ['Organic Coffee', 'Green Tea', 'Dark Chocolate', 'Olive Oil', 'Honey', 'Granola', 'Protein Powder', 'Spice Set'],
            'Beauty' => ['Face Moisturizer', 'Lip Balm', 'Hair Serum', 'Body Lotion', 'Makeup Brush Set', 'Essential Oil', 'Face Mask', 'Nail Polish'],
            'Automotive' => ['Car Phone Mount', 'Tire Pressure Gauge', 'Cleaning Kit', 'Air Freshener', 'Seat Cover', 'Emergency Kit', 'Dashboard Camera', 'Floor Mats'],
            'Health' => ['Vitamin C', 'Protein Supplement', 'First Aid Kit', 'Blood Pressure Monitor', 'Thermometer', 'Massage Gun', 'Heating Pad', 'Fitness Tracker'],
        ];

        $categoryTitles = $titles[$category] ?? ['Generic Product'];
        $baseTitle = fake()->randomElement($categoryTitles);
        
        // Add variations
        $adjectives = ['Premium', 'Professional', 'Deluxe', 'Eco-Friendly', 'Portable', 'Compact', 'Heavy-Duty', 'Ultra', 'Advanced'];
        
        if (fake()->boolean(40)) {
            return fake()->randomElement($adjectives) . ' ' . $baseTitle;
        }
        
        return $baseTitle;
    }

    /**
     * Generate relevant tags based on category.
     */
    private function generateTags(string $category): array
    {
        $commonTags = ['new', 'bestseller', 'free-shipping'];
        
        $categoryTags = [
            'Electronics' => ['wireless', 'bluetooth', 'rechargeable', 'smart', 'USB', 'HD', '5G'],
            'Fashion' => ['cotton', 'polyester', 'size-M', 'size-L', 'unisex', 'waterproof', 'breathable'],
            'Home & Garden' => ['modern', 'rustic', 'eco-friendly', 'dishwasher-safe', 'handmade', 'durable'],
            'Sports' => ['professional', 'lightweight', 'non-slip', 'adjustable', 'outdoor', 'indoor'],
            'Books' => ['paperback', 'hardcover', 'illustrated', 'bestseller', 'award-winning'],
            'Toys' => ['ages-3+', 'educational', 'safe', 'colorful', 'interactive', 'battery-free'],
            'Food & Beverage' => ['organic', 'gluten-free', 'vegan', 'non-GMO', 'fair-trade', 'natural'],
            'Beauty' => ['natural', 'paraben-free', 'cruelty-free', 'hypoallergenic', 'dermatologist-tested'],
            'Automotive' => ['universal', 'durable', 'easy-install', 'heavy-duty', 'weather-resistant'],
            'Health' => ['FDA-approved', 'non-GMO', 'clinically-tested', 'natural', 'vegan'],
        ];

        $tags = $categoryTags[$category] ?? [];
        $selectedTags = fake()->randomElements($tags, fake()->numberBetween(2, 4));
        
        // Sometimes add common tags
        if (fake()->boolean(50)) {
            $selectedTags[] = fake()->randomElement($commonTags);
        }
        
        return array_unique($selectedTags);
    }

    /**
     * Indicate that the product is a quick commerce item.
     */
    public function quickItem(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_quick_item' => true,
        ]);
    }

    /**
     * Indicate that the product is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Indicate that the product is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'DRAFT',
        ]);
    }
}
