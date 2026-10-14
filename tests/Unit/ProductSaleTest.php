<?php

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSaleTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $sale = [], string $sku = 'S1'): Product
    {
        return Product::create(array_merge([
            'sku' => $sku, 'name' => $sku, 'slug' => strtolower($sku), 'base_price' => 59.99, 'stock_quantity' => 3,
        ], $sale));
    }

    public function test_no_discount_means_not_on_sale(): void
    {
        $p = $this->make();
        $this->assertFalse($p->isOnSale());
        $this->assertSame(59.99, $p->salePrice());
    }

    public function test_open_ended_sale_and_rounding(): void
    {
        $p = $this->make(['sale_discount_percent' => 33]);
        $this->assertTrue($p->isOnSale());
        $this->assertSame(40.19, $p->salePrice());
        $this->assertSame(40.19, $p->getPrice());
    }

    public function test_window_is_respected(): void
    {
        $future = $this->make(['sale_discount_percent' => 20, 'sale_starts_at' => now()->addDay()], 'F');
        $expired = $this->make(['sale_discount_percent' => 20, 'sale_ends_at' => now()->subMinute()], 'E');
        $live = $this->make(['sale_discount_percent' => 20, 'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDay()], 'L');

        $this->assertFalse($future->isOnSale());
        $this->assertFalse($expired->isOnSale());
        $this->assertTrue($live->isOnSale());
        $this->assertSame($expired->base_price, $expired->salePrice());
    }

    public function test_scope_matches_model_logic_and_min_discount(): void
    {
        $this->make(['sale_discount_percent' => 20, 'sale_starts_at' => now()->addDay()], 'F');
        $this->make(['sale_discount_percent' => 20, 'sale_ends_at' => now()->subMinute()], 'E');
        $this->make(['sale_discount_percent' => 20], 'A');
        $this->make(['sale_discount_percent' => 50, 'sale_ends_at' => now()->addDay()], 'B');
        $this->make([], 'N');

        $this->assertEqualsCanonicalizing(['A', 'B'], Product::onSale()->pluck('sku')->all());
        $this->assertSame(['B'], Product::onSale(30)->pluck('sku')->all());
        $this->assertEqualsCanonicalizing(
            Product::all()->filter->isOnSale()->pluck('sku')->all(),
            Product::onSale()->pluck('sku')->all(),
        );
    }
}
