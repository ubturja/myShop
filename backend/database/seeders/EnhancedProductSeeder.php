<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Seeder;

class EnhancedProductSeeder extends Seeder
{
    /**
     * Comprehensive product catalog similar to Amazon/AliExpress
     */
    public function run(): void
    {
        $sellers = Seller::all();
        if ($sellers->isEmpty()) {
            $this->command->error('No sellers found. Run DatabaseSeeder first.');
            return;
        }

        $categories = [
            'Electronics' => [
                ['title' => 'Wireless Bluetooth Earbuds Pro', 'price' => 49.99, 'desc' => 'Premium sound quality with active noise cancellation, 30-hour battery life, IPX7 waterproof rating.', 'tags' => ['wireless', 'bluetooth', 'audio', 'noise-cancelling']],
                ['title' => 'Smart Watch Fitness Tracker', 'price' => 79.99, 'desc' => 'Track your fitness goals with heart rate monitoring, sleep tracking, GPS, and 100+ sport modes.', 'tags' => ['smartwatch', 'fitness', 'health', 'gps']],
                ['title' => 'Portable Power Bank 20000mAh', 'price' => 29.99, 'desc' => 'Fast charging portable charger with 3 USB ports, LED display, compatible with all smartphones.', 'tags' => ['powerbank', 'charger', 'portable', 'fast-charge']],
                ['title' => '4K Webcam with Microphone', 'price' => 89.99, 'desc' => 'Ultra HD webcam perfect for streaming, video calls, and content creation. Auto-focus and low-light correction.', 'tags' => ['webcam', '4k', 'streaming', 'microphone']],
                ['title' => 'Mechanical Gaming Keyboard RGB', 'price' => 69.99, 'desc' => 'Customizable RGB backlight, blue switches, anti-ghosting, programmable keys for pro gamers.', 'tags' => ['keyboard', 'gaming', 'rgb', 'mechanical']],
                ['title' => 'Wireless Gaming Mouse 6400 DPI', 'price' => 39.99, 'desc' => 'Precision gaming mouse with adjustable DPI, ergonomic design, and rechargeable battery.', 'tags' => ['mouse', 'gaming', 'wireless', 'dpi']],
                ['title' => 'USB-C Hub 7-in-1 Adapter', 'price' => 34.99, 'desc' => 'Multi-port hub with HDMI, USB 3.0, SD card reader, ethernet, and PD charging for laptops.', 'tags' => ['usb-c', 'hub', 'adapter', 'hdmi']],
                ['title' => 'Portable SSD 1TB External Drive', 'price' => 99.99, 'desc' => 'Ultra-fast portable storage with USB 3.2 Gen 2, shock-resistant, compact design.', 'tags' => ['ssd', 'storage', 'portable', 'usb']],
                ['title' => 'LED Desk Lamp with USB Charging', 'price' => 24.99, 'desc' => 'Adjustable brightness and color temperature, eye-care technology, USB charging port.', 'tags' => ['lamp', 'led', 'desk', 'usb-charging']],
                ['title' => 'Smart Home Security Camera', 'price' => 59.99, 'desc' => '1080P HD camera with night vision, motion detection, two-way audio, cloud storage.', 'tags' => ['security', 'camera', 'smart-home', 'wifi']],
            ],
            
            'Fashion' => [
                ['title' => 'Premium Cotton T-Shirt Pack (5)', 'price' => 39.99, 'desc' => 'Soft, breathable cotton t-shirts in assorted colors. Perfect fit and long-lasting quality.', 'tags' => ['tshirt', 'cotton', 'mens', 'basics']],
                ['title' => 'Slim Fit Denim Jeans', 'price' => 54.99, 'desc' => 'Classic blue denim with stretch fabric for comfort. Modern slim fit design.', 'tags' => ['jeans', 'denim', 'mens', 'slim-fit']],
                ['title' => 'Leather Crossbody Bag', 'price' => 79.99, 'desc' => 'Genuine leather handbag with adjustable strap, multiple compartments, elegant design.', 'tags' => ['bag', 'leather', 'womens', 'crossbody']],
                ['title' => 'Running Shoes Athletic Sneakers', 'price' => 64.99, 'desc' => 'Lightweight running shoes with breathable mesh, cushioned sole, perfect for daily workouts.', 'tags' => ['shoes', 'running', 'athletic', 'sneakers']],
                ['title' => 'Wool Winter Coat', 'price' => 129.99, 'desc' => 'Warm winter coat with premium wool blend, classic design, available in multiple colors.', 'tags' => ['coat', 'winter', 'wool', 'outerwear']],
                ['title' => 'Stainless Steel Watch', 'price' => 149.99, 'desc' => 'Elegant timepiece with sapphire crystal, water-resistant, Japanese quartz movement.', 'tags' => ['watch', 'mens', 'stainless-steel', 'accessories']],
                ['title' => 'Summer Floral Dress', 'price' => 44.99, 'desc' => 'Lightweight floral print dress perfect for summer, breathable fabric, elegant fit.', 'tags' => ['dress', 'womens', 'summer', 'floral']],
                ['title' => 'Polarized Sunglasses UV400', 'price' => 29.99, 'desc' => 'Stylish sunglasses with 100% UV protection, polarized lenses, durable frame.', 'tags' => ['sunglasses', 'polarized', 'uv-protection', 'accessories']],
                ['title' => 'Leather Belt Mens Classic', 'price' => 24.99, 'desc' => 'Genuine leather belt with metal buckle, adjustable, perfect for formal and casual wear.', 'tags' => ['belt', 'leather', 'mens', 'accessories']],
                ['title' => 'Yoga Pants Workout Leggings', 'price' => 34.99, 'desc' => 'High-waist yoga pants with pocket, moisture-wicking fabric, perfect for gym and yoga.', 'tags' => ['leggings', 'yoga', 'womens', 'workout']],
            ],

            'Home & Kitchen' => [
                ['title' => 'Stainless Steel Cookware Set 12-Piece', 'price' => 179.99, 'desc' => 'Professional-grade cookware with tri-ply construction, induction compatible, dishwasher safe.', 'tags' => ['cookware', 'kitchen', 'stainless-steel', 'set']],
                ['title' => 'Air Fryer 6 Quart Digital', 'price' => 89.99, 'desc' => 'Healthy cooking with 85% less oil, 8 preset functions, easy-to-clean non-stick basket.', 'tags' => ['air-fryer', 'kitchen', 'appliance', 'healthy']],
                ['title' => 'Memory Foam Pillow Set (2)', 'price' => 44.99, 'desc' => 'Premium memory foam pillows with cooling gel, hypoallergenic, removable washable cover.', 'tags' => ['pillow', 'memory-foam', 'bedroom', 'sleep']],
                ['title' => 'Bamboo Cutting Board Set', 'price' => 34.99, 'desc' => 'Eco-friendly bamboo cutting boards in 3 sizes, juice groove, easy to maintain.', 'tags' => ['cutting-board', 'bamboo', 'kitchen', 'eco-friendly']],
                ['title' => 'Vacuum Insulated Water Bottle 32oz', 'price' => 24.99, 'desc' => 'Keep drinks cold for 24h or hot for 12h, leak-proof lid, BPA-free stainless steel.', 'tags' => ['water-bottle', 'insulated', 'stainless-steel', 'eco-friendly']],
                ['title' => 'Smart LED Light Bulbs 4-Pack', 'price' => 39.99, 'desc' => 'WiFi enabled smart bulbs, voice control compatible, 16 million colors, dimmable.', 'tags' => ['light-bulb', 'smart-home', 'led', 'wifi']],
                ['title' => 'Electric Kettle 1.7L Glass', 'price' => 34.99, 'desc' => 'Rapid boil electric kettle with blue LED light, auto shut-off, BPA-free.', 'tags' => ['kettle', 'electric', 'kitchen', 'appliance']],
                ['title' => 'Non-Stick Baking Sheet Set', 'price' => 29.99, 'desc' => 'Heavy-duty carbon steel baking sheets with non-stick coating, warp-resistant.', 'tags' => ['baking-sheet', 'non-stick', 'kitchen', 'bakeware']],
                ['title' => 'Ceramic Dinnerware Set 16-Piece', 'price' => 69.99, 'desc' => 'Modern dinnerware set for 4, microwave and dishwasher safe, scratch-resistant.', 'tags' => ['dinnerware', 'ceramic', 'kitchen', 'dining']],
                ['title' => 'Robot Vacuum Cleaner Smart', 'price' => 199.99, 'desc' => 'Automatic robot vacuum with app control, mapping, self-charging, HEPA filter.', 'tags' => ['vacuum', 'robot', 'smart-home', 'cleaning']],
            ],

            'Sports & Outdoors' => [
                ['title' => 'Yoga Mat Extra Thick Non-Slip', 'price' => 29.99, 'desc' => '6mm thick yoga mat with carrying strap, eco-friendly TPE material, perfect for all yoga types.', 'tags' => ['yoga-mat', 'fitness', 'exercise', 'non-slip']],
                ['title' => 'Adjustable Dumbbell Set 50lbs', 'price' => 149.99, 'desc' => 'Space-saving adjustable dumbbells, quick weight change, anti-roll design.', 'tags' => ['dumbbell', 'weights', 'fitness', 'home-gym']],
                ['title' => 'Camping Tent 4-Person Waterproof', 'price' => 119.99, 'desc' => 'Easy setup family tent with rain fly, ventilation windows, durable poles.', 'tags' => ['tent', 'camping', 'outdoor', 'waterproof']],
                ['title' => 'Hiking Backpack 50L Daypack', 'price' => 54.99, 'desc' => 'Large capacity hiking backpack with rain cover, multiple pockets, breathable back panel.', 'tags' => ['backpack', 'hiking', 'outdoor', 'travel']],
                ['title' => 'Resistance Bands Set 5 Levels', 'price' => 19.99, 'desc' => 'Exercise resistance bands with handles, door anchor, carrying bag, workout guide.', 'tags' => ['resistance-bands', 'fitness', 'exercise', 'portable']],
                ['title' => 'Bike Helmet Adult Adjustable', 'price' => 39.99, 'desc' => 'Safety certified bike helmet with adjustable fit, ventilation, removable visor.', 'tags' => ['helmet', 'bike', 'safety', 'cycling']],
                ['title' => 'Jump Rope Speed Training', 'price' => 12.99, 'desc' => 'Professional speed jump rope with ball bearings, adjustable length, foam handles.', 'tags' => ['jump-rope', 'fitness', 'cardio', 'training']],
                ['title' => 'Foam Roller for Muscle Recovery', 'price' => 24.99, 'desc' => 'High-density foam roller for deep tissue massage, physical therapy, muscle recovery.', 'tags' => ['foam-roller', 'recovery', 'fitness', 'massage']],
                ['title' => 'Sports Water Bottle with Filter', 'price' => 19.99, 'desc' => 'BPA-free water bottle with built-in filter, leak-proof, easy carry loop.', 'tags' => ['water-bottle', 'sports', 'filter', 'hydration']],
                ['title' => 'Folding Camping Chair Portable', 'price' => 34.99, 'desc' => 'Lightweight camping chair with cup holder, carrying bag, supports up to 300lbs.', 'tags' => ['camping-chair', 'outdoor', 'portable', 'folding']],
            ],

            'Beauty & Personal Care' => [
                ['title' => 'Facial Cleansing Brush Electric', 'price' => 44.99, 'desc' => 'Waterproof sonic facial brush with 3 speed modes, deep cleansing, USB rechargeable.', 'tags' => ['facial-brush', 'skincare', 'cleansing', 'beauty']],
                ['title' => 'Hair Dryer Professional 1875W', 'price' => 59.99, 'desc' => 'Ionic hair dryer with diffuser and concentrator, 3 heat settings, cool shot button.', 'tags' => ['hair-dryer', 'styling', 'ionic', 'professional']],
                ['title' => 'Electric Toothbrush Rechargeable', 'price' => 39.99, 'desc' => 'Sonic electric toothbrush with 5 modes, smart timer, 4 brush heads included.', 'tags' => ['toothbrush', 'electric', 'dental', 'sonic']],
                ['title' => 'Essential Oil Diffuser 300ml', 'price' => 29.99, 'desc' => 'Ultrasonic aromatherapy diffuser with LED lights, auto shut-off, whisper-quiet.', 'tags' => ['diffuser', 'essential-oils', 'aromatherapy', 'home']],
                ['title' => 'Makeup Brush Set Professional 12-Piece', 'price' => 34.99, 'desc' => 'Premium synthetic brushes with wooden handles, vegan and cruelty-free, carrying case.', 'tags' => ['makeup-brushes', 'cosmetics', 'beauty', 'professional']],
                ['title' => 'LED Vanity Mirror with Lights', 'price' => 49.99, 'desc' => 'Tri-fold makeup mirror with 21 LED lights, 3x magnification, touch control.', 'tags' => ['mirror', 'vanity', 'led', 'makeup']],
                ['title' => 'Hair Straightener Ceramic', 'price' => 44.99, 'desc' => 'Professional flat iron with ceramic plates, adjustable temperature, fast heat-up.', 'tags' => ['straightener', 'hair', 'ceramic', 'styling']],
                ['title' => 'Jade Roller and Gua Sha Set', 'price' => 19.99, 'desc' => 'Natural jade facial massage tools for skincare routine, reduce puffiness, improve circulation.', 'tags' => ['jade-roller', 'gua-sha', 'facial', 'massage']],
                ['title' => 'Nail Polish Set 12 Colors', 'price' => 24.99, 'desc' => 'Long-lasting nail polish collection with top coat, quick-dry formula, vibrant colors.', 'tags' => ['nail-polish', 'manicure', 'beauty', 'cosmetics']],
                ['title' => 'Shower Head High Pressure', 'price' => 29.99, 'desc' => 'Luxury rain shower head with 5 spray modes, chrome finish, easy installation.', 'tags' => ['shower-head', 'bathroom', 'high-pressure', 'rain']],
            ],

            'Books & Media' => [
                ['title' => 'The Complete Python Programming Guide', 'price' => 34.99, 'desc' => 'Comprehensive guide to Python from basics to advanced topics, includes practical projects.', 'tags' => ['book', 'programming', 'python', 'education']],
                ['title' => 'Business Strategy Masterclass', 'price' => 29.99, 'desc' => 'Learn proven business strategies from industry leaders, case studies and frameworks.', 'tags' => ['book', 'business', 'strategy', 'management']],
                ['title' => 'Cookbook Healthy Recipes', 'price' => 24.99, 'desc' => '200+ delicious and nutritious recipes for healthy living, meal prep tips included.', 'tags' => ['cookbook', 'recipes', 'healthy', 'cooking']],
                ['title' => 'Mindfulness Meditation Guide', 'price' => 19.99, 'desc' => 'Complete guide to meditation practices, stress reduction, mindfulness techniques.', 'tags' => ['book', 'meditation', 'mindfulness', 'wellness']],
                ['title' => 'Photography Basics Manual', 'price' => 27.99, 'desc' => 'Master photography fundamentals, composition, lighting, and post-processing.', 'tags' => ['book', 'photography', 'manual', 'learning']],
            ],

            'Toys & Games' => [
                ['title' => 'Educational STEM Building Blocks', 'price' => 44.99, 'desc' => '300-piece building set for kids, develops creativity, motor skills, and problem-solving.', 'tags' => ['toys', 'stem', 'educational', 'building']],
                ['title' => 'Board Game Family Night Pack', 'price' => 29.99, 'desc' => 'Classic board game for 2-6 players, ages 8+, perfect for family game nights.', 'tags' => ['board-game', 'family', 'games', 'entertainment']],
                ['title' => 'Remote Control Car Racing', 'price' => 54.99, 'desc' => 'High-speed RC car with rechargeable battery, 2.4GHz remote, off-road capability.', 'tags' => ['rc-car', 'remote-control', 'toys', 'racing']],
                ['title' => 'Puzzle 1000 Pieces Landscape', 'price' => 19.99, 'desc' => 'Beautiful landscape jigsaw puzzle, high-quality pieces, relaxing family activity.', 'tags' => ['puzzle', 'jigsaw', 'games', 'family']],
                ['title' => 'Art Set Drawing Kit 150-Piece', 'price' => 39.99, 'desc' => 'Complete art supplies with colored pencils, markers, crayons, sketch book, carrying case.', 'tags' => ['art-set', 'drawing', 'kids', 'creative']],
            ],

            'Office Supplies' => [
                ['title' => 'Ergonomic Office Chair Mesh', 'price' => 189.99, 'desc' => 'Comfortable office chair with lumbar support, adjustable height and armrests, breathable mesh.', 'tags' => ['office-chair', 'ergonomic', 'furniture', 'desk']],
                ['title' => 'Standing Desk Adjustable Height', 'price' => 299.99, 'desc' => 'Electric standing desk with memory presets, cable management, sturdy construction.', 'tags' => ['standing-desk', 'adjustable', 'office', 'furniture']],
                ['title' => 'Wireless Presenter Remote', 'price' => 24.99, 'desc' => 'Presentation clicker with red laser pointer, USB receiver, 100ft range.', 'tags' => ['presenter', 'remote', 'office', 'presentation']],
                ['title' => 'Label Maker Portable', 'price' => 34.99, 'desc' => 'Handheld label maker with QWERTY keyboard, multiple fonts and sizes, tape included.', 'tags' => ['label-maker', 'office', 'organization', 'portable']],
                ['title' => 'Desk Organizer Bamboo', 'price' => 29.99, 'desc' => 'Multi-compartment desk organizer with phone holder, eco-friendly bamboo.', 'tags' => ['organizer', 'desk', 'bamboo', 'office']],
            ],
        ];

        $this->command->info('Creating comprehensive product catalog...');
        
        $productCount = 0;
        foreach ($categories as $category => $products) {
            foreach ($products as $product) {
                $seller = $sellers->random();
                
                Product::create([
                    'seller_id' => $seller->id,
                    'title' => $product['title'],
                    'description' => $product['desc'],
                    'price_cents' => (int)($product['price'] * 100),
                    'currency' => 'USD',
                    'sku' => 'SKU-' . strtoupper(substr(md5($product['title']), 0, 10)),
                    'image_url' => 'https://picsum.photos/seed/' . md5($product['title']) . '/640/480',
                    'category' => $category,
                    'tags' => $product['tags'],
                    'is_quick_item' => rand(1, 10) > 7, // 30% are quick items
                    'status' => 'ACTIVE',
                ]);
                
                $productCount++;
            }
        }

        $this->command->info("Created {$productCount} products across " . count($categories) . " categories.");
    }
}
