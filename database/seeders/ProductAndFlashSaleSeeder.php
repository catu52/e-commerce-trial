<?php

namespace Database\Seeders;

use App\Models\FlashSale;
use App\Services\FlashSaleReservationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductAndFlashSaleSeeder extends Seeder
{
    public function run(): void
    {
        $adminUserId = DB::table('users')->where('email', 'admin@system.local')->value('id');

        // 1. Create Base Catalog Products
        $categoryIds = DB::table('categories')->pluck('id', 'slug');
        $catalog = json_decode(file_get_contents(__DIR__.'/data/products.json'), true);

        $productIdMap = [];
        foreach ($catalog as $item) {
            $id = DB::table('products')->insertGetId([
                'sku' => $item['sku'],
                'slug' => $item['slug'],
                'code' => $item['code'],
                'name' => $item['name'],
                'description' => $item['description'],
                'base_price' => $item['price'],
                'currency' => 'EUR',
                'stock_quantity' => $item['stock'],
                'category_id' => $categoryIds[Str::slug($item['category'])] ?? null,
                'gender' => $item['gender'] ?? 'unisex',
                'sub_category' => $item['sub_category'] ?? null,
                'tag' => $item['tag'] ?? null,
                'badge' => $item['badge'] ?? null,
                'substrate' => $item['substrate'] ?? null,
                'spec_weight' => $item['spec_weight'] ?? null,
                'colorway' => $item['colorway'] ?? null,
                'is_featured' => $item['featured'],
                'sizes' => json_encode($item['sizes']),
                'colors' => json_encode($item['colors']),
                'specs' => json_encode($item['specs']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $productIdMap[$item['sku']] = $id;

            $this->seedImages($id, $item);
        }
        $this->seedFlashSales($productIdMap, $adminUserId);
    }

    /**
     * Demo flash sales: [sku, discount %, total stock, available stock, starts (h from now), ends (h from now)].
     *
     * @param  array<string, int>  $productIdMap
     */
    private function seedFlashSales(array $productIdMap, ?int $adminUserId): void
    {
        $demo = [
            ['SKU-KFT-9402', 60, 12, 2, -1, 3],
            ['SKU-KFT-JKT-11', 55, 10, 3, -2, 4],
            ['SKU-KFT-BMB-01', 50, 15, 5, -1, 5],
            ['SKU-KFT-KN-05', 45, 20, 9, -3, 6],
            ['SKU-KFT-CAR-02', 40, 18, 12, -1, 8],
            ['SKU-KFT-CHR-09', 50, 8, 1, -4, 2],
            ['SKU-KFT-TR-301', 55, 10, 10, 6, 12],
            ['SKU-KFT-MCK-11', 60, 14, 14, 24, 30],
        ];

        $service = app(FlashSaleReservationService::class);
        $clientIds = DB::table('clients')->pluck('id');

        foreach ($demo as [$sku, $discount, $total, $available, $startsIn, $endsIn]) {
            $basePrice = (float) DB::table('products')->where('id', $productIdMap[$sku])->value('base_price');

            $sale = FlashSale::create([
                'product_id' => $productIdMap[$sku],
                'created_by' => $adminUserId,
                'sale_price' => round($basePrice * (100 - $discount) / 100, 2),
                'total_stock' => $total,
                'available_stock' => $available,
                'starts_at' => now()->addHours($startsIn),
                'ends_at' => now()->addHours($endsIn),
                'is_active' => true,
            ]);

            // Fresh seed: drop leftover per-client holds from previous runs, then load stock into Redis.
            foreach ($clientIds as $clientId) {
                Redis::del("flash_sale:{$sale->id}:client:{$clientId}");
            }
            $service->warmFlashSaleStock($sale);
        }
    }

    /**
     * Generate offline placeholder SVGs on the public disk and register them as product images.
     *
     * @param  array<string, mixed>  $item
     */
    private function seedImages(int $productId, array $item): void
    {
        $disk = Storage::disk('public');
        $bg = $item['colors'][0]['hex'] ?? '#1a1a1a';
        $fg = in_array(strtolower($bg), ['#f5f0e8', '#d9d4cc', '#d0cbc3', '#ffcc00'], true) ? '#1a1a1a' : '#f5f0e8';

        for ($i = 0; $i < $item['gallery']; $i++) {
            $path = "products/{$productId}/placeholder-".($i + 1).'.svg';
            $label = e($item['code']);
            $title = e($item['name']);
            $view = $i + 1;
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="1000" viewBox="0 0 800 1000">
  <rect width="800" height="1000" fill="{$bg}"/>
  <rect x="40" y="40" width="720" height="920" fill="none" stroke="{$fg}" stroke-opacity="0.35" stroke-width="2"/>
  <text x="70" y="110" font-family="monospace" font-size="28" fill="{$fg}">{$label}</text>
  <text x="70" y="930" font-family="monospace" font-size="22" fill="{$fg}" fill-opacity="0.7">{$title} / VIEW 0{$view}</text>
</svg>
SVG;
            $disk->put($path, $svg);

            DB::table('product_images')->insert([
                'product_id' => $productId,
                'path' => $path,
                'position' => $i,
                'is_primary' => $i === 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
