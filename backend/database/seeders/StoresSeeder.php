<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoresSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stores = [
            [
                'store_code' => 'STORE_001',
                'name' => 'MyShop Express NYC',
                'latitude' => 40.7580,
                'longitude' => -73.9855,
                'address' => '350 5th Ave',
                'city' => 'New York',
                'postal_code' => '10118',
            ],
            [
                'store_code' => 'STORE_002',
                'name' => 'MyShop Quick LA',
                'latitude' => 34.0549,
                'longitude' => -118.2426,
                'address' => '800 W Olympic Blvd',
                'city' => 'Los Angeles',
                'postal_code' => '90015',
            ],
            [
                'store_code' => 'STORE_003',
                'name' => 'MyShop Market Chicago',
                'latitude' => 41.8789,
                'longitude' => -87.6359,
                'address' => '233 S Wacker Dr',
                'city' => 'Chicago',
                'postal_code' => '60606',
            ],
        ];

        foreach ($stores as $storeData) {
            // Check if store already exists by store_code
            if (Store::where('store_code', $storeData['store_code'])->exists()) {
                $this->command->line("Skipping existing store: {$storeData['store_code']}");
                continue;
            }

            Store::create(array_merge($storeData, [
                'opening_hours' => [
                    'monday' => '08:00-22:00',
                    'tuesday' => '08:00-22:00',
                    'wednesday' => '08:00-22:00',
                    'thursday' => '08:00-22:00',
                    'friday' => '08:00-23:00',
                    'saturday' => '09:00-23:00',
                    'sunday' => '10:00-20:00',
                ],
                'is_active' => true,
            ]));

            $this->command->info("Created store: {$storeData['store_code']} - {$storeData['name']}");
        }
    }
}
