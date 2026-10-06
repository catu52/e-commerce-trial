<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductAndFlashSaleSeeder extends Seeder
{
    public function run(): void
    {
        $adminUserId = DB::table('users')->where('email', 'admin@system.local')->value('id');

        // 1. Create Base Catalog Products
        $products = [
            [
                'sku' => 'PROD-HEADSET-001',
                'name' => 'Pro Wireless Gaming Headset',
                'description' => 'Ultra-low latency lossless wireless audio headset with noise-canceling mic.',
                'base_price' => 199.99,
                'stock_quantity' => 1000,
            ],
            [
                'sku' => 'PROD-KEYBOARD-002',
                'name' => 'RGB Mechanical Keyboard',
                'description' => 'Hot-swappable mechanical switches with custom PBT keycaps.',
                'base_price' => 149.50,
                'stock_quantity' => 500,
            ],
            [
                'sku' => 'PROD-MONITOR-003',
                'name' => '27-inch 240Hz Gaming Monitor',
                'description' => '1ms QHD OLED display for competitive gaming.',
                'base_price' => 699.00,
                'stock_quantity' => 200,
            ],
        ];

        $productIdMap = [];
        foreach ($products as $prod) {
            $id = DB::table('products')->insertGetId(array_merge($prod, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $productIdMap[$prod['sku']] = $id;
        }

        // 2. Create Active & Scheduled Flash Sales
        $flashSales = [
            [
                'product_id' => $productIdMap['PROD-HEADSET-001'],
                'created_by' => $adminUserId,
                'sale_price' => 79.99, // Big discount
                'total_stock' => 100,  // Limited stock allocated
                'available_stock' => 100,
                'starts_at' => now()->subMinutes(10), // Currently Active
                'ends_at' => now()->addHours(2),
                'is_active' => true,
            ],
            [
                'product_id' => $productIdMap['PROD-KEYBOARD-002'],
                'created_by' => $adminUserId,
                'sale_price' => 49.99,
                'total_stock' => 50,
                'available_stock' => 50,
                'starts_at' => now()->addDay(), // Scheduled for Tomorrow
                'ends_at' => now()->addDay()->addHours(3),
                'is_active' => true,
            ],
        ];

        foreach ($flashSales as $sale) {
            DB::table('flash_sales')->insert(array_merge($sale, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
