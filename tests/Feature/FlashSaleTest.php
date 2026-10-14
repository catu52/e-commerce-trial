<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FlashSale;
use App\Services\FlashSaleReservationService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductAndFlashSaleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([RolesAndPermissionsSeeder::class, UserSeeder::class, CategorySeeder::class, ProductAndFlashSaleSeeder::class]);
    }

    private function newClient(): Client
    {
        return Client::create([
            'name' => 'Buyer', 'first_name' => 'Buyer', 'last_name' => 'One',
            'email' => uniqid().'@example.com', 'password' => 'password',
        ]);
    }

    public function test_lists_flash_sales_with_contract_shape_and_meta(): void
    {
        $response = $this->getJson('/api/v1/catalog/flash-sales')->assertOk()
            ->assertJsonPath('meta.total', 8)
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'status', 'startsAt', 'endsAt', 'salePrice', 'originalPrice', 'currency', 'discountPercent',
                    'totalStock', 'availableStock', 'depletionPercent', 'stockState',
                    'product' => ['id', 'slug', 'title', 'code', 'category', 'image', 'sizes', 'sku', 'gender'],
                ]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'nextEndsAt', 'serverTime', 'tiers' => ['all', '40', '50', '60'], 'categories' => [['name', 'slug', 'count']]],
            ]);

        $statuses = array_column($response->json('data'), 'status');
        $this->assertSame(['active', 'upcoming'], array_values(array_unique($statuses)));
        $this->assertSame(array_merge(array_fill(0, 6, 'active'), array_fill(0, 2, 'upcoming')), $statuses);

        $earliest = FlashSale::active()->orderBy('ends_at')->first();
        $this->assertSame($earliest->ends_at->toIso8601String(), $response->json('meta.nextEndsAt'));
        $this->assertSame(['all' => 8, '40' => 8, '50' => 6, '60' => 2], $response->json('meta.tiers'));
        $this->assertStringStartsWith('http', $response->json('data.0.product.image'));
    }

    public function test_resource_math_and_stock_states(): void
    {
        $sale = FlashSale::with('product')->first();
        $sale->update(['sale_price' => 100, 'total_stock' => 10, 'available_stock' => 6]);
        $sale->product->update(['base_price' => 400]);

        $item = collect($this->getJson('/api/v1/catalog/flash-sales')->json('data'))->firstWhere('id', (string) $sale->id);

        $this->assertSame(75, $item['discountPercent']);
        $this->assertSame(40, $item['depletionPercent']);
        $this->assertSame(400.0, (float) $item['originalPrice']);
        $this->assertSame('low', $item['stockState']);

        $this->assertSame('critical', (new FlashSale(['total_stock' => 100, 'available_stock' => 15]))->stock_state);
        $this->assertSame('critical', (new FlashSale(['total_stock' => 100, 'available_stock' => 3]))->stock_state);
        $this->assertSame('low', (new FlashSale(['total_stock' => 100, 'available_stock' => 30]))->stock_state);
        $this->assertSame('ok', (new FlashSale(['total_stock' => 100, 'available_stock' => 60]))->stock_state);
    }

    public function test_filters_by_tier_category_and_size(): void
    {
        $this->getJson('/api/v1/catalog/flash-sales?tier=60')->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.tiers.all', 8);

        $this->assertEqualsCanonicalizing(['KNITWEAR'], array_unique(array_column(array_column(
            $this->getJson('/api/v1/catalog/flash-sales?category=knitwear')->json('data'), 'product'), 'category')));

        $sizes = $this->getJson('/api/v1/catalog/flash-sales?size=XL')->assertOk()->json('data');
        $this->assertNotEmpty($sizes);
        foreach ($sizes as $sale) {
            $this->assertContains('XL', $sale['product']['sizes']);
        }

        $this->getJson('/api/v1/catalog/flash-sales?tier=70')->assertUnprocessable();
        $this->getJson('/api/v1/catalog/flash-sales?per_page=49')->assertUnprocessable();
    }

    public function test_sorting_and_pagination(): void
    {
        $discounts = array_column($this->getJson('/api/v1/catalog/flash-sales?sort=discount&per_page=6')->json('data'), 'discountPercent');
        $sorted = $discounts;
        rsort($sorted);
        $this->assertSame($sorted, $discounts);

        $prices = array_column($this->getJson('/api/v1/catalog/flash-sales?sort=price_asc&per_page=6')->json('data'), 'salePrice');
        $asc = $prices;
        sort($asc);
        $this->assertSame($asc, $prices);

        $prices = array_column($this->getJson('/api/v1/catalog/flash-sales?sort=price_desc&per_page=6')->json('data'), 'salePrice');
        $desc = $prices;
        rsort($desc);
        $this->assertSame($desc, $prices);

        $depletion = array_column($this->getJson('/api/v1/catalog/flash-sales?sort=depletion&per_page=6')->json('data'), 'depletionPercent');
        $d = $depletion;
        rsort($d);
        $this->assertSame($d, $depletion);

        $this->getJson('/api/v1/catalog/flash-sales?per_page=3&page=2')->assertOk()
            ->assertJsonCount(3, 'data')->assertJsonPath('meta.last_page', 3);
    }

    public function test_reserve_requires_authentication(): void
    {
        $this->postJson('/api/v1/client/flash-sales/1/reserve')->assertUnauthorized();
    }

    public function test_reserve_success_decrements_stock_then_enforces_limit(): void
    {
        Event::fake();
        $sale = FlashSale::active()->where('available_stock', '>', 1)->firstOrFail();
        $client = $this->newClient();
        Redis::del("flash_sale:{$sale->id}:client:{$client->id}");
        Sanctum::actingAs($client, [], 'sanctum_clients');
        $before = $sale->available_stock;

        $this->postJson("/api/v1/client/flash-sales/{$sale->id}/reserve")
            ->assertStatus(202)->assertJsonPath('status', 'processing');

        $this->assertSame($before - 1, $sale->fresh()->available_stock);
        $listed = collect($this->getJson('/api/v1/catalog/flash-sales')->json('data'))->firstWhere('id', (string) $sale->id);
        $this->assertSame($before - 1, $listed['availableStock']);

        $this->postJson("/api/v1/client/flash-sales/{$sale->id}/reserve")
            ->assertStatus(422)->assertJsonPath('status', 'failed');
    }

    public function test_reserve_sold_out(): void
    {
        Event::fake();
        $sale = FlashSale::active()->firstOrFail();
        $sale->update(['available_stock' => 0]);
        app(FlashSaleReservationService::class)->warmFlashSaleStock($sale);
        $client = $this->newClient();
        Redis::del("flash_sale:{$sale->id}:client:{$client->id}");
        Sanctum::actingAs($client, [], 'sanctum_clients');

        $this->postJson("/api/v1/client/flash-sales/{$sale->id}/reserve")
            ->assertStatus(422)->assertJsonPath('message', 'Flash sale is sold out!');
    }
}
