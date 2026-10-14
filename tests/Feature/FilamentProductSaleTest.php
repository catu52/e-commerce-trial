<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentProductSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, UserSeeder::class]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'admin@system.local')->firstOrFail());
    }

    private function make(string $sku, array $extra = []): Product
    {
        return Product::create(array_merge([
            'sku' => $sku, 'name' => $sku, 'slug' => strtolower($sku), 'base_price' => 100, 'stock_quantity' => 10,
        ], $extra));
    }

    public function test_set_sale_via_edit_form_then_toggle_off_clears_it(): void
    {
        $p = $this->make('A');

        Livewire::test(EditProduct::class, ['record' => $p->getRouteKey()])
            ->fillForm(['on_sale' => true, 'sale_discount_percent' => 30])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(30, $p->fresh()->sale_discount_percent);
        $this->assertSame(70.0, $p->fresh()->salePrice());

        Livewire::test(EditProduct::class, ['record' => $p->getRouteKey()])
            ->assertFormSet(['on_sale' => true, 'sale_discount_percent' => 30])
            ->fillForm(['on_sale' => false])
            ->call('save')->assertHasNoFormErrors();
        $fresh = $p->fresh();
        $this->assertNull($fresh->sale_discount_percent);
        $this->assertNull($fresh->sale_starts_at);
        $this->assertNull($fresh->sale_ends_at);
    }

    public function test_validation(): void
    {
        $p = $this->make('A');
        $page = fn () => Livewire::test(EditProduct::class, ['record' => $p->getRouteKey()]);

        $page()->fillForm(['on_sale' => true, 'sale_discount_percent' => null])->call('save')
            ->assertHasFormErrors(['sale_discount_percent' => 'required']);
        $page()->fillForm(['on_sale' => true, 'sale_discount_percent' => 91])->call('save')
            ->assertHasFormErrors(['sale_discount_percent']);
        $page()->fillForm(['on_sale' => true, 'sale_discount_percent' => 0])->call('save')
            ->assertHasFormErrors(['sale_discount_percent']);
        $page()->fillForm(['on_sale' => true, 'sale_discount_percent' => 10, 'sale_starts_at' => '2030-01-02 10:00:00', 'sale_ends_at' => '2030-01-01 10:00:00'])
            ->call('save')->assertHasFormErrors(['sale_ends_at']);
    }

    public function test_create_with_sale(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'New', 'slug' => 'new', 'sku' => 'NEW', 'currency' => 'EUR', 'base_price' => 50, 'stock_quantity' => 1, 'gender' => 'unisex', 'on_sale' => true, 'sale_discount_percent' => 10])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame(10, Product::where('sku', 'NEW')->value('sale_discount_percent'));
    }

    public function test_on_sale_and_tier_filters(): void
    {
        $live = $this->make('LIVE', ['sale_discount_percent' => 40]);
        $big = $this->make('BIG', ['sale_discount_percent' => 60]);
        $expired = $this->make('EXP', ['sale_discount_percent' => 40, 'sale_ends_at' => now()->subDay()]);
        $reg = $this->make('REG');

        Livewire::test(ListProducts::class)
            ->filterTable('on_sale', true)
            ->assertCanSeeTableRecords([$live, $big])->assertCanNotSeeTableRecords([$expired, $reg]);
        Livewire::test(ListProducts::class)
            ->filterTable('on_sale', false)
            ->assertCanSeeTableRecords([$expired, $reg])->assertCanNotSeeTableRecords([$live, $big]);
        Livewire::test(ListProducts::class)
            ->filterTable('discount_tier', '50')
            ->assertCanSeeTableRecords([$big])->assertCanNotSeeTableRecords([$live, $expired, $reg]);
    }

    public function test_bulk_put_on_sale_and_end_sale(): void
    {
        $a = $this->make('A');
        $b = $this->make('B', ['sale_discount_percent' => 10]);

        Livewire::test(ListProducts::class)
            ->selectTableRecords([$a, $b])
            ->callAction(TestAction::make('putOnSale')->table()->bulk(), data: ['percent' => 35])
            ->assertHasNoActionErrors();
        $this->assertSame(35, $a->fresh()->sale_discount_percent);
        $this->assertSame(35, $b->fresh()->sale_discount_percent);

        Livewire::test(ListProducts::class)
            ->selectTableRecords([$a, $b])
            ->callAction(TestAction::make('endSale')->table()->bulk());
        $this->assertNull($a->fresh()->sale_discount_percent);
        $this->assertNull($b->fresh()->sale_discount_percent);
    }

    public function test_bulk_actions_hidden_without_manage_permission(): void
    {
        $this->actingAs(User::where('email', 'support@system.local')->firstOrFail());
        $this->assertFalse(ProductResource::staffCan('products.manage'));
    }

    public function test_product_title_links_to_storefront_page_and_edit_page_has_view_action(): void
    {
        $product = $this->make('LNK', ['slug' => 'my-jacket']);
        $url = config('shop.frontend_url').'/products/my-jacket';

        Livewire::test(ListProducts::class)->assertSeeHtml('href="'.$url.'"');
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionHasUrl('viewOnSite', $url);
    }
}
