<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Seller;
use App\Models\Product;
use App\Models\Store;
use App\Models\Inventory;
use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * Creates:
     * - 1 admin user
     * - 10 customer users
     * - 3 sellers (with verified status)
     * - 50 products (distributed across sellers)
     * - 5 stores (for quick commerce)
     * - Inventory records for quick commerce items
     * - 5 group buys with various statuses
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding...');

        // Create admin user
        $this->command->info('Creating admin user...');
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@myshop.local',
            'password' => bcrypt('password'),
        ]);

        // Create regular customers
        $this->command->info('Creating customer users...');
        $customers = User::factory(10)->create();

        // Create sellers
        $this->command->info('Creating sellers...');
        $sellers = collect();
        for ($i = 1; $i <= 3; $i++) {
            $sellerUser = User::factory()->seller()->create([
                'name' => "Seller {$i}",
                'email' => "seller{$i}@myshop.local",
                'password' => bcrypt('password'),
            ]);
            
            $seller = Seller::factory()->verified()->create([
                'user_id' => $sellerUser->id,
            ]);
            
            $sellers->push($seller);
        }

        // Create stores for quick commerce
        $this->command->info('Creating stores...');
        $stores = Store::factory(5)->create();

        // Create products
        $this->command->info('Creating products...');
        $products = collect();
        
        foreach ($sellers as $index => $seller) {
            // Each seller gets ~17 products (total 51, close to 50)
            $sellerProducts = Product::factory(17)->create([
                'seller_id' => $seller->id,
            ]);
            
            $products = $products->merge($sellerProducts);
        }

        // Create inventory records for quick commerce items
        $this->command->info('Creating inventory records...');
        $quickItems = $products->where('is_quick_item', true);
        
        foreach ($quickItems as $product) {
            // Add inventory to 2-3 random stores for each quick item
            $randomStores = $stores->random(rand(2, 3));
            
            foreach ($randomStores as $store) {
                Inventory::factory()->inStock()->create([
                    'product_id' => $product->id,
                    'store_id' => $store->id,
                ]);
            }
        }

        // Create group buys with various statuses
        $this->command->info('Creating group buys...');
        
        // 2 active group buys
        $activeGroupBuys = GroupBuy::factory(2)->active()->create([
            'product_id' => fn() => $products->random()->id,
            'starter_user_id' => fn() => $customers->random()->id,
        ]);

        // Add some members to active group buys
        foreach ($activeGroupBuys as $groupBuy) {
            $memberCount = rand(1, $groupBuy->target_size - 1); // Not yet fulfilled
            $members = $customers->random($memberCount);
            
            foreach ($members as $member) {
                GroupBuyMember::create([
                    'group_buy_id' => $groupBuy->id,
                    'user_id' => $member->id,
                    'joined_at' => now()->subHours(rand(1, 48)),
                    'paid' => false,
                ]);
            }
        }

        // 1 fulfilled group buy
        $fulfilledGroupBuy = GroupBuy::factory()->fulfilled()->create([
            'product_id' => $products->random()->id,
            'starter_user_id' => $customers->random()->id,
            'target_size' => min(5, $customers->count()), // Ensure we have enough customers
        ]);

        // Add members to fulfilled group buy (target reached)
        $memberCount = min($fulfilledGroupBuy->target_size, $customers->count());
        $members = $customers->random($memberCount);
        foreach ($members as $member) {
            GroupBuyMember::create([
                'group_buy_id' => $fulfilledGroupBuy->id,
                'user_id' => $member->id,
                'joined_at' => now()->subHours(rand(24, 72)),
                'paid' => true,
            ]);
        }

        // 1 failed group buy
        $failedGroupBuy = GroupBuy::factory()->failed()->create([
            'product_id' => $products->random()->id,
            'starter_user_id' => $customers->random()->id,
        ]);

        // Add some members to failed group buy (didn't reach target)
        $memberCount = $failedGroupBuy->target_size - rand(2, 5);
        $members = $customers->random(max(1, $memberCount));
        foreach ($members as $member) {
            GroupBuyMember::create([
                'group_buy_id' => $failedGroupBuy->id,
                'user_id' => $member->id,
                'joined_at' => now()->subHours(rand(48, 120)),
                'paid' => false,
            ]);
        }

        // 1 expired group buy
        $expiredGroupBuy = GroupBuy::factory()->expired()->create([
            'product_id' => $products->random()->id,
            'starter_user_id' => $customers->random()->id,
        ]);

        $this->command->info('✅ Database seeding completed!');
        $this->command->newLine();
        $this->command->info('📊 Summary:');
        $this->command->info("   Users: {$customers->count()} customers + {$sellers->count()} sellers + 1 admin");
        $this->command->info("   Sellers: {$sellers->count()}");
        $this->command->info("   Products: {$products->count()} ({$quickItems->count()} quick items)");
        $this->command->info("   Stores: {$stores->count()}");
        $this->command->info("   Inventory records: " . Inventory::count());
        $this->command->info("   Group buys: 5 (2 active, 1 fulfilled, 1 failed, 1 expired)");
        $this->command->newLine();
        $this->command->info('🔑 Login credentials:');
        $this->command->info('   Admin: admin@myshop.local / password');
        $this->command->info('   Sellers: seller1@myshop.local, seller2@myshop.local, seller3@myshop.local / password');
    }
}
