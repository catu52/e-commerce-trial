<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSaleApiTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $sku, array $extra = []): Product
    {
        return Product::create(array_merge([
            'sku' => $sku, 'name' => $sku, 'slug' => strtolower($sku), 'base_price' => 100, 'stock_quantity' => 10,
        ], $extra));
    }

    private function seedSales(): void
    {
        $this->make('REG');
        $this->make('S20', ['sale_discount_percent' => 20, 'sale_ends_at' => now()->addDays(2)]);
        $this->make('S50', ['sale_discount_percent' => 50]);
        $this->make('FUT', ['sale_discount_percent' => 80, 'sale_starts_at' => now()->addDay()]);
        $this->make('EXP', ['sale_discount_percent' => 90, 'sale_ends_at' => now()->subHour()]);
    }

    public function test_on_sale_filter_excludes_future_and_expired(): void
    {
        $this->seedSales();

        $r = $this->getJson('/api/v1/catalog/products?on_sale=1')->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertEqualsCanonicalizing(['S20', 'S50'], collect($r->json('data'))->pluck('sku')->all());
        $this->getJson('/api/v1/catalog/products')->assertJsonPath('meta.total', 5);
    }

    public function test_min_discount_implies_on_sale_and_combines_with_filters(): void
    {
        $this->seedSales();

        $this->getJson('/api/v1/catalog/products?min_discount=30')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.sku', 'S50');
        $this->getJson('/api/v1/catalog/products?min_discount=10&search=S20')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.sku', 'S20');
        $this->getJson('/api/v1/catalog/products?on_sale=1&in_stock=1&min_discount=60')
            ->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_invalid_params_return_422(): void
    {
        foreach (['min_discount=0', 'min_discount=91', 'min_discount=abc', 'on_sale=maybe'] as $q) {
            $this->getJson("/api/v1/catalog/products?$q")->assertStatus(422);
        }
    }

    public function test_resource_exposes_effective_price_and_sale_fields(): void
    {
        $this->seedSales();

        $sale = collect($this->getJson('/api/v1/catalog/products')->json('data'))->firstWhere('sku', 'S20');
        $this->assertSame(80, (int) $sale['price']);
        $this->assertSame(80.0, (float) $sale['priceEUR']);
        $this->assertTrue($sale['onSale']);
        $this->assertSame(20, $sale['discountPercent']);
        $this->assertSame(100.0, (float) $sale['compareAtPriceEUR']);
        $this->assertNotNull($sale['saleEndsAt']);

        $this->getJson('/api/v1/catalog/products/exp')->assertOk()
            ->assertJsonPath('data.onSale', false)
            ->assertJsonPath('data.discountPercent', null)
            ->assertJsonPath('data.compareAtPriceEUR', null)
            ->assertJsonPath('data.saleEndsAt', null)
            ->assertJsonPath('data.price', 100);
    }

    public function test_meta_max_discount(): void
    {
        $this->make('NONE');
        $this->getJson('/api/v1/catalog/products')->assertJsonPath('meta.max_discount', null);

        $this->seedSales();
        $this->getJson('/api/v1/catalog/products')->assertJsonPath('meta.max_discount', 50);
        $this->getJson('/api/v1/catalog/products?min_discount=10&search=S20')->assertJsonPath('meta.max_discount', 50);
    }

    public function test_checkout_uses_discounted_price(): void
    {
        $product = $this->make('CHK', ['sale_discount_percent' => 25, 'sizes' => ['M']]);
        Sanctum::actingAs(Client::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'password' => 'password']), [], 'sanctum_clients');

        $this->postJson('/api/v1/client/orders', [
            'items' => [['productId' => (string) $product->id, 'size' => 'M', 'quantity' => 2]],
            'email' => 'b@example.com', 'country' => 'ES', 'firstName' => 'B', 'lastName' => 'C', 'address' => 'Calle 1',
            'postalCode' => '28001', 'city' => 'Madrid', 'deliveryMethod' => 'express', 'paymentMethod' => 'card',
        ])->assertCreated()
            ->assertJsonPath('data.items.0.unitPrice', 75)
            ->assertJsonPath('data.items.0.lineTotal', 150);

        $this->assertSame('150.00', number_format((float) Order::first()->subtotal, 2, '.', ''));
    }
}
