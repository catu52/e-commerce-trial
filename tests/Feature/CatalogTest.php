<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductAndFlashSaleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([RolesAndPermissionsSeeder::class, UserSeeder::class, CategorySeeder::class, ProductAndFlashSaleSeeder::class]);
    }

    public function test_lists_products_with_contract_shape_and_meta(): void
    {
        $response = $this->getJson('/api/v1/catalog/products?per_page=5');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 14)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure(['data' => [[
                'id', 'slug', 'title', 'code', 'category', 'subCategory', 'priceEUR', 'price', 'currency',
                'specWeight', 'substrate', 'tag', 'badge', 'colorway', 'colorHexList', 'colorsAvailable',
                'sizes', 'inStock', 'stockLeft', 'featured', 'image', 'galleryImages', 'description', 'sku',
                'specs' => ['origin', 'weight', 'composition', 'care'],
            ]], 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        $first = $response->json('data.0');
        $this->assertIsString($first['id']);
        $this->assertStringEndsWith('placeholder-1.svg', $first['image']);
        $this->assertSame('EUR', $first['currency']);
    }

    public function test_filters_by_category_featured_and_search(): void
    {
        $this->getJson('/api/v1/catalog/products?category=trousers')
            ->assertOk()->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/catalog/products?featured=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.featured', true);

        $this->getJson('/api/v1/catalog/products?search=parka')
            ->assertOk()->assertJsonPath('data.0.slug', 'modular-technical-parka');
    }

    public function test_in_stock_filter_excludes_sold_out_products(): void
    {
        Product::where('slug', 'modular-technical-parka')->update(['stock_quantity' => 0]);

        $this->getJson('/api/v1/catalog/products?in_stock=1')->assertJsonPath('meta.total', 13);
        $this->getJson('/api/v1/catalog/products/modular-technical-parka')
            ->assertJsonPath('data.inStock', false)->assertJsonPath('data.stockLeft', 0);
    }

    public function test_show_by_slug_and_404(): void
    {
        $this->getJson('/api/v1/catalog/products/bauhaus-pleated-pant')
            ->assertOk()
            ->assertJsonPath('data.title', '02 BAUHAUS PLEATED PANT')
            ->assertJsonPath('data.category', 'TROUSERS')
            ->assertJsonPath('data.colorsAvailable.0.hex', '#1a1a1a');

        $this->getJson('/api/v1/catalog/products/nope')->assertNotFound();
    }

    public function test_lists_categories(): void
    {
        $this->getJson('/api/v1/catalog/categories')
            ->assertOk()->assertJsonCount(5, 'data')->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
    }

    public function test_cors_allows_configured_frontend_origin(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173'])
            ->getJson('/api/v1/catalog/products')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');

        $this->call('OPTIONS', '/api/v1/client/login', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,authorization',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');

        $this->withHeaders(['Origin' => 'http://evil.example'])
            ->getJson('/api/v1/catalog/products')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_gender_filter_men_includes_unisex(): void
    {
        $response = $this->getJson('/api/v1/catalog/products?gender=men')->assertOk()
            ->assertJsonPath('meta.total', 10);

        $this->assertEqualsCanonicalizing(['men', 'unisex'], array_values(array_unique(array_column($response->json('data'), 'gender'))));
    }

    public function test_gender_filter_women_includes_unisex(): void
    {
        $response = $this->getJson('/api/v1/catalog/products?gender=women')->assertOk()
            ->assertJsonPath('meta.total', 7);

        $this->assertEqualsCanonicalizing(['women', 'unisex'], array_values(array_unique(array_column($response->json('data'), 'gender'))));
    }

    public function test_gender_filter_unisex_only_and_invalid_value_is_rejected(): void
    {
        $this->getJson('/api/v1/catalog/products?gender=unisex')->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/catalog/products?gender=kids')
            ->assertStatus(422)->assertJsonValidationErrors('gender');
    }

    public function test_resource_exposes_gender(): void
    {
        $this->getJson('/api/v1/catalog/products/'.Product::where('sku', 'SKU-KFT-TR-301')->value('slug'))
            ->assertOk()->assertJsonPath('data.gender', 'women');
    }

    public function test_cms_creates_product_with_gender_and_defaults_to_unisex(): void
    {
        Sanctum::actingAs(User::where('email', 'manager@system.local')->firstOrFail(), [], 'sanctum_users');

        $this->postJson('/api/v1/staff/products', ['sku' => 'G-1', 'name' => 'Skirt', 'base_price' => 50, 'stock_quantity' => 2, 'gender' => 'women'])
            ->assertCreated()->assertJsonPath('product.gender', 'women');
        $this->postJson('/api/v1/staff/products', ['sku' => 'G-2', 'name' => 'Cap', 'base_price' => 20, 'stock_quantity' => 2])
            ->assertCreated()->assertJsonPath('product.gender', 'unisex');
        $this->postJson('/api/v1/staff/products', ['sku' => 'G-3', 'name' => 'Bad', 'base_price' => 20, 'stock_quantity' => 2, 'gender' => 'kids'])
            ->assertStatus(422);

        $this->getJson('/api/v1/staff/products?gender=women')->assertOk()->assertJsonPath('total', 5);
    }
}
