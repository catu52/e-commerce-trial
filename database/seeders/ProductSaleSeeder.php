<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSaleSeeder extends Seeder
{
    /**
     * Regular product sales: [sku, discount %, starts (days from now|null), ends (days from now|null)].
     */
    private const DEMO = [
        ['SKU-KFT-TR-04', 10, null, null],
        ['SKU-KFT-KN-03', 20, -2, 14],
        ['SKU-KFT-BG-04', 30, null, 7],
        ['SKU-KFT-ACC-06', 40, -1, null],
        ['SKU-KFT-FT-08', 60, null, 30],
        ['SKU-KFT-ACC-99', 25, 3, 10],   // scheduled: not live yet
        ['SKU-KFT-KN-05', 15, -10, -1],  // expired
    ];

    public function run(): void
    {
        foreach (self::DEMO as [$sku, $percent, $starts, $ends]) {
            DB::table('products')->where('sku', $sku)->update([
                'sale_discount_percent' => $percent,
                'sale_starts_at' => $starts === null ? null : now()->addDays($starts),
                'sale_ends_at' => $ends === null ? null : now()->addDays($ends),
                'updated_at' => now(),
            ]);
        }
    }
}
