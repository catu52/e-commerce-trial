<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /**
     * Create a multi-item order, locking and decrementing product stock atomically.
     *
     * @param  array<string, mixed>  $data  validated checkout payload
     *
     * @throws ValidationException
     */
    public function placeOrder(Client $client, array $data): Order
    {
        return DB::transaction(function () use ($client, $data) {
            $lines = $data['items'];

            $ids = $this->resolveProductIds($lines);
            $products = $this->lockProducts($ids);
            $resolved = $this->resolveLines($lines, $ids, $products);
            $requested = $this->requestedQuantities($resolved);
            $this->assertStock($requested, $ids, $products);

            $subtotal = $this->subtotal($resolved);
            $deliveryCost = $this->deliveryCost($data['deliveryMethod']);
            $promoCode = $this->normalizePromoCode($data['promoCode'] ?? null);
            $discount = $this->discount($promoCode, $subtotal);

            $order = Order::create([
                'client_id' => $client->id,
                'product_id' => $resolved[0]['product']->id,
                'quantity' => array_sum($requested),
                'status' => OrderStatus::PENDING,
                'total_amount' => round($subtotal + $deliveryCost - $discount, 2),
                'order_number' => $this->generateOrderNumber(),
                'email' => $data['email'],
                'country' => $data['country'],
                'first_name' => $data['firstName'],
                'last_name' => $data['lastName'],
                'address' => $data['address'],
                'apartment' => $data['apartment'] ?? null,
                'postal_code' => $data['postalCode'],
                'city' => $data['city'],
                'phone' => $data['phone'] ?? null,
                'delivery_method' => $data['deliveryMethod'],
                'payment_method' => $data['paymentMethod'],
                'promo_code' => $discount > 0 ? $promoCode : null,
                'subtotal' => $subtotal,
                'delivery_cost' => $deliveryCost,
                'discount' => $discount,
                'currency' => config('shop.currency'),
            ]);

            $this->createItems($order, $resolved);
            $this->decrementStock($requested, $products);

            return $order->load('items');
        });
    }

    /**
     * Resolve product references (numeric id or slug) to ids, keyed by line index.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, int>
     */
    private function resolveProductIds(array $lines): array
    {
        $ids = [];
        foreach ($lines as $index => $line) {
            $ref = (string) $line['productId'];
            $id = ctype_digit($ref)
                ? Product::whereKey((int) $ref)->value('id')
                : Product::where('slug', $ref)->value('id');

            if (! $id) {
                throw ValidationException::withMessages(["items.$index.productId" => ['Product not found.']]);
            }
            $ids[$index] = $id;
        }

        return $ids;
    }

    /**
     * Lock in a stable order to avoid deadlocks between concurrent checkouts.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     */
    private function lockProducts(array $ids): Collection
    {
        return Product::whereIn('id', array_unique($ids))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, int>  $ids
     * @param  Collection<int, Product>  $products
     * @return array<int, array{product: Product, size: ?string, color: ?string, quantity: int}>
     */
    private function resolveLines(array $lines, array $ids, Collection $products): array
    {
        $resolved = [];
        foreach ($lines as $index => $line) {
            $product = $products[$ids[$index]];
            $size = $line['size'] ?? null;

            $this->assertSize($product, $size, $index);

            $resolved[] = [
                'product' => $product,
                'size' => $size,
                'color' => $this->resolveColor($product, $line['color'] ?? null, $index),
                'quantity' => $line['quantity'],
            ];
        }

        return $resolved;
    }

    private function assertSize(Product $product, ?string $size, int $index): void
    {
        $sizes = $product->sizes ?? [];
        if ($sizes !== [] && ($size === null || ! in_array($size, $sizes, true))) {
            throw ValidationException::withMessages(["items.$index.size" => ["Invalid size for {$product->name}."]]);
        }
    }

    private function resolveColor(Product $product, ?string $color, int $index): ?string
    {
        if ($color === null || $color === '') {
            return null;
        }

        $match = collect($product->colors ?? [])->first(
            fn ($c) => strcasecmp($c['name'], $color) === 0 || strcasecmp($c['hex'], $color) === 0
        );
        if (! $match) {
            throw ValidationException::withMessages(["items.$index.color" => ["Invalid color for {$product->name}."]]);
        }

        return $match['name'];
    }

    /**
     * @param  array<int, array{product: Product, quantity: int}>  $resolved
     * @return array<int, int> total quantity per product id
     */
    private function requestedQuantities(array $resolved): array
    {
        $requested = [];
        foreach ($resolved as $line) {
            $id = $line['product']->id;
            $requested[$id] = ($requested[$id] ?? 0) + $line['quantity'];
        }

        return $requested;
    }

    /**
     * @param  array<int, int>  $requested
     * @param  array<int, int>  $ids
     * @param  Collection<int, Product>  $products
     */
    private function assertStock(array $requested, array $ids, Collection $products): void
    {
        foreach ($requested as $productId => $quantity) {
            if ($products[$productId]->stock_quantity < $quantity) {
                $index = array_search($productId, $ids, true);
                throw ValidationException::withMessages([
                    "items.$index.quantity" => ["Insufficient stock for {$products[$productId]->name}. Available: {$products[$productId]->stock_quantity}."],
                ]);
            }
        }
    }

    /**
     * @param  array<int, array{product: Product, quantity: int}>  $resolved
     */
    private function subtotal(array $resolved): float
    {
        $subtotal = 0.0;
        foreach ($resolved as $line) {
            $subtotal += $line['product']->salePrice() * $line['quantity'];
        }

        return round($subtotal, 2);
    }

    private function deliveryCost(string $method): float
    {
        return round((float) config("shop.delivery_costs.$method", 0), 2);
    }

    private function normalizePromoCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return array_key_exists($code, config('shop.promo_codes', [])) ? $code : null;
    }

    private function discount(?string $promoCode, float $subtotal): float
    {
        if ($promoCode === null) {
            return 0.0;
        }

        $percent = (float) config("shop.promo_codes.$promoCode");

        return round(min($subtotal * $percent / 100, $subtotal), 2);
    }

    /**
     * @param  array<int, array{product: Product, size: ?string, color: ?string, quantity: int}>  $resolved
     */
    private function createItems(Order $order, array $resolved): void
    {
        foreach ($resolved as $line) {
            $order->items()->create([
                'product_id' => $line['product']->id,
                'title' => $line['product']->name,
                'sku' => $line['product']->sku,
                'size' => $line['size'],
                'color' => $line['color'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['product']->salePrice(),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $requested
     * @param  Collection<int, Product>  $products
     */
    private function decrementStock(array $requested, Collection $products): void
    {
        foreach ($requested as $productId => $quantity) {
            $products[$productId]->decrement('stock_quantity', $quantity);
        }
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'KFT-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
