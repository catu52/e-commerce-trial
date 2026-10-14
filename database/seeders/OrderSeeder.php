<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo orders spread over the last ~90 days. Deterministic and idempotent:
 * orders are keyed by order_number (KFT-SEED-0001...) and never touch stock.
 */
class OrderSeeder extends Seeder
{
    private const COUNT = 36;

    public function run(): void
    {
        $clients = Client::orderBy('id')->get();
        $products = Product::orderBy('id')->get();

        if ($clients->isEmpty() || $products->isEmpty()) {
            $this->command?->warn('OrderSeeder skipped: seed clients and products first.');

            return;
        }

        mt_srand(20261012);
        $statuses = [OrderStatus::COMPLETED, OrderStatus::COMPLETED, OrderStatus::COMPLETED, OrderStatus::PROCESSING, OrderStatus::PENDING, OrderStatus::FAILED];
        $deliveries = array_keys(config('shop.delivery_costs'));
        $payments = ['card', 'paypal', 'apple-pay'];
        $cities = [['Madrid', '28001', 'ES'], ['Barcelona', '08001', 'ES'], ['Berlin', '10115', 'DE'], ['Paris', '75001', 'FR'], ['Lisbon', '1100-148', 'PT']];

        DB::transaction(function () use ($clients, $products, $statuses, $deliveries, $payments, $cities) {
            for ($i = 1; $i <= self::COUNT; $i++) {
                $number = sprintf('KFT-SEED-%04d', $i);
                $client = $clients[mt_rand(0, $clients->count() - 1)];
                $picked = $products->shuffle(fn () => mt_rand())->take(mt_rand(1, 3));
                $items = $picked->map(function (Product $p) {
                    $sizes = $p->sizes ?? [];
                    $colors = $p->colors ?? [];
                    $size = $sizes ? $this->label($sizes[mt_rand(0, count($sizes) - 1)]) : null;
                    $color = $colors ? $this->label($colors[mt_rand(0, count($colors) - 1)]) : null;

                    return [
                        'product_id' => $p->id, 'title' => $p->name, 'sku' => $p->sku, 'size' => $size, 'color' => $color,
                        'quantity' => mt_rand(1, 2), 'unit_price' => round($p->salePrice(), 2),
                    ];
                })->values();

                $subtotal = round($items->sum(fn ($it) => $it['unit_price'] * $it['quantity']), 2);
                $delivery = $deliveries[mt_rand(0, count($deliveries) - 1)];
                $deliveryCost = (float) config("shop.delivery_costs.$delivery");
                $promo = mt_rand(1, 4) === 1 ? 'KRAFT' : null;
                $discount = $promo ? round($subtotal * config("shop.promo_codes.$promo") / 100, 2) : 0.0;
                [$city, $zip, $country] = $cities[mt_rand(0, count($cities) - 1)];
                $createdAt = now()->subDays(mt_rand(0, 89))->subMinutes(mt_rand(0, 1439));

                $order = Order::firstOrNew(['order_number' => $number]);
                $order->forceFill([
                    'client_id' => $client->id,
                    'product_id' => $items[0]['product_id'],
                    'quantity' => $items->sum('quantity'),
                    'status' => $statuses[mt_rand(0, count($statuses) - 1)],
                    'total_amount' => round($subtotal + $deliveryCost - $discount, 2),
                    'email' => $client->email, 'first_name' => $client->first_name, 'last_name' => $client->last_name,
                    'phone' => $client->phone, 'address' => mt_rand(1, 120).' Demo Street', 'apartment' => mt_rand(0, 1) ? 'Apt '.mt_rand(1, 20) : null,
                    'city' => $city, 'postal_code' => $zip, 'country' => $country,
                    'delivery_method' => $delivery, 'payment_method' => $payments[mt_rand(0, count($payments) - 1)],
                    'promo_code' => $promo, 'subtotal' => $subtotal, 'delivery_cost' => $deliveryCost, 'discount' => $discount,
                    'currency' => 'EUR', 'created_at' => $createdAt, 'updated_at' => $createdAt,
                ])->save();

                $order->items()->delete();
                foreach ($items as $item) {
                    $order->items()->create($item);
                }
            }
        });
    }

    private function label(mixed $value): ?string
    {
        return is_array($value) ? ($value['name'] ?? $value['label'] ?? null) : (string) $value;
    }
}
