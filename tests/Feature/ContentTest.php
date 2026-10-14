<?php

namespace Tests\Feature;

use App\Models\HomePage;
use App\Models\Product;
use App\Models\SocialLink;
use App\Models\StoreLocation;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_returns_safe_defaults_on_empty_database(): void
    {
        $this->getJson('/api/v1/content/home')->assertOk()->assertExactJson(['data' => [
            'heroEyebrow' => '',
            'heroTitle' => '',
            'heroSubtitle' => '',
            'lookbook' => [],
            'editorialTitle' => '',
            'editorialBody' => '',
            'editorialImageUrl' => null,
        ]]);
    }

    public function test_home_returns_ordered_lookbook_with_absolute_image_urls(): void
    {
        Storage::fake('public');
        $page = HomePage::current();
        $page->update(['hero_title' => 'HERO', 'editorial_image_path' => 'content/editorial.jpg']);
        $page->lookbookItems()->create(['title' => 'B', 'caption' => 'cb', 'link' => '/b', 'sort_order' => 2, 'image_path' => 'content/b.jpg']);
        $page->lookbookItems()->create(['title' => 'A', 'caption' => 'ca', 'link' => '/a', 'sort_order' => 1]);

        $response = $this->getJson('/api/v1/content/home')->assertOk();

        $response->assertJsonPath('data.heroTitle', 'HERO')
            ->assertJsonPath('data.lookbook.0.title', 'A')
            ->assertJsonPath('data.lookbook.0.imageUrl', null)
            ->assertJsonPath('data.lookbook.1.title', 'B')
            ->assertJsonStructure(['data' => ['lookbook' => [['id', 'title', 'caption', 'link', 'imageUrl']]]]);
        $this->assertStringEndsWith('content/b.jpg', $response->json('data.lookbook.1.imageUrl'));
        $this->assertStringEndsWith('content/editorial.jpg', $response->json('data.editorialImageUrl'));
    }

    public function test_site_settings_returns_empty_arrays_on_empty_database(): void
    {
        $this->getJson('/api/v1/content/site-settings')->assertOk()->assertExactJson(['data' => [
            'announcement' => '',
            'footerTagline' => '',
            'socialLinks' => [],
            'storeLocations' => [],
        ]]);
    }

    public function test_site_settings_lists_only_active_items_in_order(): void
    {
        SocialLink::create(['label' => 'TWO', 'icon' => 'i2', 'href' => 'https://two.test', 'sort_order' => 2]);
        SocialLink::create(['label' => 'ONE', 'icon' => 'i1', 'href' => 'https://one.test', 'sort_order' => 1]);
        SocialLink::create(['label' => 'HIDDEN', 'icon' => 'x', 'href' => 'https://x.test', 'sort_order' => 0, 'is_active' => false]);
        StoreLocation::create(['name' => 'B', 'address' => 'addr b', 'hours' => 'OPEN 2', 'sort_order' => 2]);
        StoreLocation::create(['name' => 'A', 'address' => 'addr a', 'hours' => 'OPEN 1', 'sort_order' => 1]);
        StoreLocation::create(['name' => 'OFF', 'sort_order' => 0, 'is_active' => false]);

        $this->getJson('/api/v1/content/site-settings')->assertOk()
            ->assertJsonCount(2, 'data.socialLinks')
            ->assertJsonPath('data.socialLinks.0', ['label' => 'ONE', 'icon' => 'i1', 'href' => 'https://one.test'])
            ->assertJsonPath('data.socialLinks.1.label', 'TWO')
            ->assertJsonCount(2, 'data.storeLocations')
            ->assertJsonPath('data.storeLocations.0', ['name' => 'A', 'address' => 'addr a', 'hours' => 'OPEN 1'])
            ->assertJsonPath('data.storeLocations.1.name', 'B');
    }

    public function test_content_seeder_is_idempotent(): void
    {
        $this->seed(ContentSeeder::class);
        $this->seed(ContentSeeder::class);

        $this->getJson('/api/v1/content/home')->assertOk()
            ->assertJsonCount(1, 'data.lookbook')
            ->assertJsonPath('data.heroTitle', 'GEOMETRIC UTILITY COLLECTION');
        $this->getJson('/api/v1/content/site-settings')->assertOk()
            ->assertJsonCount(4, 'data.socialLinks')
            ->assertJsonCount(4, 'data.storeLocations')
            ->assertJsonPath('data.storeLocations.0.name', 'BERLIN MITTE');
    }

    public function test_product_resource_exposes_editorial_fields(): void
    {
        Product::create([
            'sku' => 'SKU-E', 'name' => 'Parka', 'slug' => 'parka', 'base_price' => 100, 'stock_quantity' => 5,
            'headline' => 'Head', 'long_description' => 'Long', 'care_instructions' => 'Care',
            'seo_title' => 'SEO T', 'seo_description' => 'SEO D',
        ]);
        Product::create(['sku' => 'SKU-N', 'name' => 'Plain', 'slug' => 'plain', 'base_price' => 10, 'stock_quantity' => 1]);

        $this->getJson('/api/v1/catalog/products/parka')->assertOk()->assertJsonPath('data.headline', 'Head')
            ->assertJsonPath('data.longDescription', 'Long')
            ->assertJsonPath('data.careInstructions', 'Care')
            ->assertJsonPath('data.seoTitle', 'SEO T')
            ->assertJsonPath('data.seoDescription', 'SEO D');
        $this->getJson('/api/v1/catalog/products/plain')->assertOk()->assertJsonPath('data.headline', null)
            ->assertJsonPath('data.longDescription', null)
            ->assertJsonPath('data.seoDescription', null);
    }

    public function test_staff_can_update_editorial_fields(): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, UserSeeder::class]);
        $product = Product::create(['sku' => 'SKU-U', 'name' => 'Parka', 'slug' => 'parka', 'base_price' => 100, 'stock_quantity' => 5]);

        Sanctum::actingAs(User::where('email', 'manager@system.local')->firstOrFail(), [], 'sanctum_users');
        $this->putJson("/api/v1/staff/products/{$product->id}", [
            'headline' => 'New head', 'long_description' => 'New long', 'care_instructions' => 'Wash',
            'seo_title' => 'T', 'seo_description' => 'D',
        ])->assertOk()->assertJsonPath('product.headline', 'New head');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'headline' => 'New head', 'seo_description' => 'D', 'name' => 'Parka']);

        $this->postJson('/api/v1/staff/products', [
            'sku' => 'SKU-C', 'name' => 'Created', 'base_price' => 20, 'stock_quantity' => 1, 'headline' => 'H2',
        ])->assertCreated()->assertJsonPath('product.headline', 'H2');
    }
}
