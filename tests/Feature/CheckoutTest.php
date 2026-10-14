<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'OUTERWEAR', 'slug' => 'outerwear']);
        $this->product = Product::create([
            'sku' => 'SKU-1', 'name' => 'Parka', 'slug' => 'parka', 'base_price' => 100, 'stock_quantity' => 5,
            'category_id' => $category->id, 'sizes' => ['S', 'M'],
            'colors' => [['name' => 'Olive', 'hex' => '#3d443b']],
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'items' => [['productId' => (string) $this->product->id, 'size' => 'M', 'color' => 'Olive', 'quantity' => 2]],
            'email' => 'buyer@example.com',
            'country' => 'ES',
            'firstName' => 'Buy',
            'lastName' => 'Er',
            'address' => 'Calle 1',
            'postalCode' => '28001',
            'city' => 'Madrid',
            'deliveryMethod' => 'express',
            'paymentMethod' => 'card',
            'promoCode' => 'BAUHAUS10',
        ], $overrides);
    }

    private function actAsClient(): Client
    {
        $client = Client::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'password' => 'password']);
        Sanctum::actingAs($client, [], 'sanctum_clients');

        return $client;
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/client/orders', $this->payload())->assertUnauthorized();
        $this->getJson('/api/v1/client/orders')->assertUnauthorized();
    }

    public function test_creates_order_and_decrements_stock(): void
    {
        $this->actAsClient();

        $response = $this->postJson('/api/v1/client/orders', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.deliveryCost', 15)
            ->assertJsonPath('data.discount', 20)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.total', 195)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.color', 'Olive');

        $this->assertSame(3, $this->product->fresh()->stock_quantity);

        $number = $response->json('data.orderNumber');
        $this->getJson('/api/v1/client/orders')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/client/orders/{$number}")->assertOk()->assertJsonPath('data.orderNumber', $number);
    }

    public function test_ignores_client_supplied_money_and_unknown_promo(): void
    {
        $this->actAsClient();

        $this->postJson('/api/v1/client/orders', $this->payload([
            'deliveryMethod' => 'sameday', 'deliveryCostEUR' => 0, 'discountEUR' => 999, 'promoCode' => 'FAKE',
        ]))->assertCreated()
            ->assertJsonPath('data.deliveryCost', 25)
            ->assertJsonPath('data.discount', 0)
            ->assertJsonPath('data.total', 225);

        $this->postJson('/api/v1/client/orders', $this->payload([
            'deliveryMethod' => 'standard', 'deliveryCostEUR' => 50, 'promoCode' => 'kraft',
        ]))->assertCreated()
            ->assertJsonPath('data.deliveryCost', 0)
            ->assertJsonPath('data.discount', 20)
            ->assertJsonPath('data.total', 180);
    }

    public function test_rejects_insufficient_stock_without_side_effects(): void
    {
        $this->actAsClient();

        $this->postJson('/api/v1/client/orders', $this->payload([
            'items' => [['productId' => (string) $this->product->id, 'size' => 'M', 'color' => 'Olive', 'quantity' => 6]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['items.0.quantity']);

        $this->assertSame(5, $this->product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_rejects_invalid_size_and_color(): void
    {
        $this->actAsClient();

        $this->postJson('/api/v1/client/orders', $this->payload([
            'items' => [['productId' => (string) $this->product->id, 'size' => 'XXL', 'color' => 'Olive', 'quantity' => 1]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['items.0.size']);

        $this->postJson('/api/v1/client/orders', $this->payload([
            'items' => [['productId' => (string) $this->product->id, 'size' => 'S', 'color' => 'Pink', 'quantity' => 1]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['items.0.color']);

        $this->assertSame(5, $this->product->fresh()->stock_quantity);
    }

    public function test_cannot_read_another_clients_order(): void
    {
        $this->actAsClient();
        $number = $this->postJson('/api/v1/client/orders', $this->payload())->json('data.orderNumber');

        $other = Client::create(['first_name' => 'C', 'last_name' => 'D', 'email' => 'c@example.com', 'password' => 'password']);
        Sanctum::actingAs($other, [], 'sanctum_clients');

        $this->getJson("/api/v1/client/orders/{$number}")->assertNotFound();
    }
}
