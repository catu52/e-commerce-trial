<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ProductStockService
{
    /**
     * Get a paginated list of products, optionally filtered by a search term.
     *
     * @param  int  $perPage
     * @param  string|null  $search
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getPaginatedProducts(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        // Get a paginated list of products, optionally filtered by a search term.
        return Product::when($search, function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%")
                      ->orWhere('sku', 'ilike', "%{$search}%");
            })
            ->latest() // Order by the most recently created products first
            ->paginate($perPage);
    }

    /**
     * Create a new product with the given data.
     *
     * @param  array  $data
     * @return \App\Models\Product
     */
    public function createProduct(array $data): Product
    {
        // Create a new product with the given data.
        return Product::create([
            'sku' => $data['sku'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'base_price' => $data['base_price'],
            'stock_quantity' => $data['stock_quantity'],
        ]);
    }

    /**
     * Adjust the stock quantity of the specified product.
     *
     * @param  \App\Models\Product  $product
     * @param  string  $type  ('add', 'subtract', 'set')
     * @param  int  $quantity
     * @param  string  $reason
     * @param  int  $actorUserId
     * @return \App\Models\Product
     *
     * @throws \InvalidArgumentException
     */
    public function adjustStock(Product $product, string $type, int $quantity, string $reason, int $actorUserId): Product
    {
        // Adjust the stock quantity of the specified product within a database transaction to ensure atomicity.
        return DB::transaction(function () use ($product, $type, $quantity, $reason, $actorUserId) {
            // Lock record for update to prevent concurrent stock adjustments
            /** @var Product $lockedProduct */
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $oldQuantity = $lockedProduct->stock_quantity;

            // Determine the new stock quantity based on the adjustment type.
            switch ($type) {
                case 'add': // Add the specified quantity to the current stock
                    $newQuantity = $oldQuantity + $quantity;
                    break;
                case 'subtract': // Subtract the specified quantity from the current stock
                    if ($oldQuantity < $quantity) {
                        throw new InvalidArgumentException("Insufficient inventory to subtract {$quantity} units. Current stock: {$oldQuantity}");
                    }
                    $newQuantity = $oldQuantity - $quantity;
                    break;
                case 'set':
                    $newQuantity = $quantity;
                    break;
                default:
                    throw new InvalidArgumentException("Invalid adjustment type: {$type}");
            }

            $lockedProduct->update(['stock_quantity' => $newQuantity]);

            // Log the stock adjustment for auditing purposes.
            Log::info("Stock adjusted for Product #{$lockedProduct->id} ({$lockedProduct->sku})", [
                'type' => $type,
                'quantity_change' => $quantity,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'reason' => $reason,
                'adjusted_by' => $actorUserId,
            ]);

            return $lockedProduct;
        });
    }
}
