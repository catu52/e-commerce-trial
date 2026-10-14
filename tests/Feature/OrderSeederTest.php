<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ClientSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\ProductAndFlashSaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_consistent_orders_and_is_idempotent(): void
    {
        $this->seed([ClientSeeder::class, CategorySeeder::class, ProductAndFlashSaleSeeder::class, OrderSeeder::class]);
        $count = Order::count();
        $this->assertGreaterThan(20, $count);

        Order::with('items')->get()->each(function (Order $o) {
            $this->assertNotEmpty($o->items);
            $this->assertEqualsWithDelta($o->subtotal, $o->items->sum(fn ($i) => $i->unit_price * $i->quantity), 0.01);
            $this->assertEqualsWithDelta($o->total_amount, $o->subtotal + $o->delivery_cost - $o->discount, 0.01);
            $this->assertSame((int) $o->items->sum('quantity'), $o->quantity);
        });

        $this->seed(OrderSeeder::class);
        $this->assertSame($count, Order::count());
        $this->assertSame(Order::count(), OrderItem::distinct('order_id')->count('order_id'));
    }
}
