<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\FlashSaleResource\Pages\ListFlashSales;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager;
use App\Filament\Widgets\StoreStatsOverview;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([RolesAndPermissionsSeeder::class, UserSeeder::class]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function staff(string $name): User
    {
        return User::where('email', "{$name}@system.local")->firstOrFail();
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'sku' => 'SKU-1', 'name' => 'Parka', 'slug' => 'parka', 'base_price' => 100, 'stock_quantity' => 5,
        ], $overrides));
    }

    private function order(Product $product, OrderStatus $status = OrderStatus::COMPLETED): Order
    {
        $client = Client::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'c@example.com', 'password' => 'secret123']);
        $order = Order::create([
            'client_id' => $client->id, 'product_id' => $product->id, 'quantity' => 2, 'status' => $status,
            'total_amount' => 200, 'subtotal' => 200, 'order_number' => 'ORD-1', 'currency' => 'EUR',
        ]);
        $order->items()->create(['product_id' => $product->id, 'title' => 'Parka', 'sku' => 'SKU-1', 'quantity' => 2, 'unit_price' => 100]);

        return $order;
    }

    /* ===== Access ===== */

    public function test_login_page_is_served(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_sidebar_is_collapsible_and_every_nav_item_has_an_icon_for_tooltips(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->isSidebarCollapsibleOnDesktop());

        $this->actingAs($this->staff('admin'));
        foreach (Filament::getPanel('admin')->getNavigationItems() as $item) {
            $this->assertNotEmpty($item->getIcon(), $item->getLabel().' needs an icon to show when the sidebar is collapsed');
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    #[DataProvider('staffRoles')]
    public function test_staff_roles_can_open_the_panel(string $name): void
    {
        $this->actingAs($this->staff($name))->get('/admin')->assertOk();
    }

    /** @return array<string, array<int, string>> */
    public static function staffRoles(): array
    {
        return ['admin' => ['admin'], 'manager' => ['manager'], 'support' => ['support']];
    }

    public function test_staff_without_cms_permission_is_denied(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_client_cannot_access_the_panel(): void
    {
        $client = Client::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'x@example.com', 'password' => 'secret123']);

        $this->assertNotInstanceOf(FilamentUser::class, $client);
        $this->actingAs($client)->get('/admin')->assertForbidden();
    }

    public function test_demo_admin_can_log_in_through_the_form(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@system.local', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->staff('admin'));
    }

    public function test_api_guards_are_unaffected(): void
    {
        $this->postJson('/api/v1/staff/login', ['email' => 'admin@system.local', 'password' => 'password'])->assertOk();
    }

    /* ===== Resources ===== */

    public function test_resource_list_pages_render_for_admin(): void
    {
        $product = $this->product();
        $this->order($product);
        $this->actingAs($this->staff('admin'));

        foreach ([ListProducts::class, ListOrders::class, ListCategories::class, ListFlashSales::class] as $page) {
            Livewire::test($page)->assertSuccessful();
        }
        Livewire::test(StoreStatsOverview::class)->assertSee('Low-stock products');
    }

    public function test_support_sees_orders_but_not_products(): void
    {
        $this->actingAs($this->staff('support'));

        Livewire::test(ListOrders::class)->assertSuccessful();
        Livewire::test(ListProducts::class)->assertForbidden();
    }

    public function test_manager_manages_products_and_orders_are_read_only(): void
    {
        $this->actingAs($this->staff('manager'));

        Livewire::test(ListProducts::class)->assertSuccessful();
        $this->assertFalse(OrderResource::canCreate());
    }

    public function test_admin_creates_a_product(): void
    {
        $this->actingAs($this->staff('admin'));

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Windbreaker', 'slug' => 'windbreaker', 'sku' => 'WB-1', 'gender' => 'men',
                'currency' => 'eur', 'base_price' => 89.5, 'stock_quantity' => 12,
                'sizes' => ['S', 'M'], 'colors' => [['name' => 'Signal Red', 'hex' => '#ff0000']], 'specs' => ['fabric' => 'nylon'],
                'headline' => 'Light & fast',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'WB-1')->firstOrFail();
        $this->assertSame('EUR', $product->currency);
        $this->assertSame(['S', 'M'], $product->sizes);
        $this->assertSame([['name' => 'Signal Red', 'hex' => '#ff0000']], $product->colors);
        $this->assertSame(['fabric' => 'nylon'], $product->specs);
        $this->assertSame('Light & fast', $product->headline);
    }

    public function test_product_colors_require_a_name_and_valid_hex(): void
    {
        $this->actingAs($this->staff('admin'));

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Y', 'slug' => 'y', 'sku' => 'Y-1', 'gender' => 'men', 'currency' => 'EUR',
                'base_price' => 1, 'stock_quantity' => 1,
                'colors' => [['name' => '', 'hex' => '#ff0000']],
            ])
            ->call('create')
            ->assertHasFormErrors(['colors.0.name' => 'required']);
    }

    public function test_product_edit_form_shows_named_colors_and_normalises_legacy_strings(): void
    {
        $product = $this->product(['colors' => [['name' => 'Cobalt', 'hex' => '#0055ff'], 'legacy']]);
        $this->actingAs($this->staff('admin'));

        $component = Livewire::test(EditProduct::class, ['record' => $product->getKey()]);
        $colors = array_values($component->get('data.colors'));
        $this->assertSame('Cobalt', $colors[0]['name']);
        $this->assertSame('#0055ff', $colors[0]['hex']);
        $this->assertSame('legacy', $colors[1]['name']);

        Livewire::test(ListProducts::class)->assertSuccessful();
    }

    public function test_product_validation_rejects_duplicate_sku(): void
    {
        $this->product();
        $this->actingAs($this->staff('admin'));

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'X', 'slug' => 'x', 'sku' => 'SKU-1', 'base_price' => 1, 'stock_quantity' => 1, 'currency' => 'EUR', 'gender' => 'men'])
            ->call('create')
            ->assertHasFormErrors(['sku' => 'unique']);
    }

    public function test_stock_adjustment_action_uses_stock_service(): void
    {
        $product = $this->product();
        $this->actingAs($this->staff('manager'));

        Livewire::test(ListProducts::class)
            ->callTableAction('adjustStock', $product, ['type' => 'add', 'quantity' => 7, 'reason' => 'restock'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(12, $product->fresh()->stock_quantity);
    }

    public function test_product_images_are_stored_on_the_public_disk(): void
    {
        $product = $this->product();
        $this->actingAs($this->staff('admin'));

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: ['path' => UploadedFile::fake()->image('a.png'), 'position' => 1, 'is_primary' => false])
            ->assertHasNoTableActionErrors();

        $image = $product->images()->firstOrFail();
        $this->assertTrue($image->is_primary);
        $this->assertStringStartsWith("products/{$product->id}/", $image->path);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_support_refunds_an_order_and_stock_is_restored(): void
    {
        $product = $this->product();
        $order = $this->order($product);
        $this->actingAs($this->staff('support'));

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('refund', ['reason' => 'Customer request'])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::FAILED, $order->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    public function test_refund_from_table_and_hidden_without_permission(): void
    {
        $order = $this->order($this->product());

        $this->actingAs($this->staff('manager'));
        Livewire::test(ListOrders::class)->assertTableActionHidden('refund', $order);

        $this->actingAs($this->staff('support'));
        Livewire::test(ListOrders::class)
            ->callTableAction('refund', $order, ['reason' => 'dup'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(OrderStatus::FAILED, $order->fresh()->status);
    }
}
