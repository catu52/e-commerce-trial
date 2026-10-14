<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([RolesAndPermissionsSeeder::class, UserSeeder::class]);
        $this->product = Product::create(['sku' => 'SKU-1', 'name' => 'Parka', 'slug' => 'parka', 'base_price' => 100, 'stock_quantity' => 5]);
    }

    private function actAs(string $email): void
    {
        Sanctum::actingAs(User::where('email', $email)->firstOrFail(), [], 'sanctum_users');
    }

    public function test_manager_uploads_and_deletes_images(): void
    {
        $this->actAs('manager@system.local');

        $first = $this->postJson("/api/v1/staff/products/{$this->product->id}/images", [
            'image' => UploadedFile::fake()->image('a.png'),
        ])->assertCreated()->assertJsonPath('image.is_primary', true);

        $second = $this->postJson("/api/v1/staff/products/{$this->product->id}/images", [
            'image' => UploadedFile::fake()->image('b.png'),
        ])->assertCreated()->assertJsonPath('image.is_primary', false);

        $this->assertCount(2, Storage::disk('public')->allFiles());

        $this->getJson('/api/v1/catalog/products/parka')->assertJsonCount(2, 'data.galleryImages');

        $this->deleteJson("/api/v1/staff/products/{$this->product->id}/images/{$first->json('image.id')}")->assertOk();
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->assertDatabaseHas('product_images', ['id' => $second->json('image.id'), 'is_primary' => true]);
    }

    public function test_rejects_non_images_and_unauthorized_staff(): void
    {
        $this->actAs('manager@system.local');
        $this->postJson("/api/v1/staff/products/{$this->product->id}/images", [
            'image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable();

        $this->actAs('support@system.local');
        $this->postJson("/api/v1/staff/products/{$this->product->id}/images", [
            'image' => UploadedFile::fake()->image('a.png'),
        ])->assertForbidden();
    }
}
