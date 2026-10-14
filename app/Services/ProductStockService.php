<?php

namespace App\Services;

use App\Enums\ProductGender;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductStockService
{
    /**
     * Get a paginated list of products, optionally filtered by a search term.
     */
    public function getPaginatedProducts(int $perPage = 15, ?string $search = null, ?string $gender = null): LengthAwarePaginator
    {
        // Get a paginated list of products, optionally filtered by a search term and gender.
        return Product::with(['category', 'images'])->when($search, function ($query, $search) {
            $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('sku', 'ilike', "%{$search}%"));
        })
            ->when($gender, fn ($query, $gender) => $query->where('gender', $gender))
            ->latest() // Order by the most recently created products first
            ->paginate($perPage);
    }

    /**
     * Create a new product with the given data.
     */
    public function createProduct(array $data): Product
    {
        // Create a new product with the given data.
        $product = Product::create([
            'sku' => $data['sku'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'base_price' => $data['base_price'],
            'stock_quantity' => $data['stock_quantity'],
            'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
            'code' => $data['code'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'sub_category' => $data['sub_category'] ?? null,
            'tag' => $data['tag'] ?? null,
            'badge' => $data['badge'] ?? null,
            'substrate' => $data['substrate'] ?? null,
            'spec_weight' => $data['spec_weight'] ?? null,
            'colorway' => $data['colorway'] ?? null,
            'gender' => $data['gender'] ?? ProductGender::UNISEX->value,
            'is_featured' => $data['is_featured'] ?? false,
            'currency' => strtoupper($data['currency'] ?? 'EUR'),
            'sizes' => $data['sizes'] ?? [],
            'colors' => $data['colors'] ?? [],
            'specs' => $data['specs'] ?? null,
            'headline' => $data['headline'] ?? null,
            'long_description' => $data['long_description'] ?? null,
            'care_instructions' => $data['care_instructions'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ]);

        return $product->load(['category', 'images']);
    }

    /**
     * Update the given fields of a product (only keys present in $data are touched).
     */
    public function updateProduct(Product $product, array $data): Product
    {
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $product->update($data);

        return $product->load(['category', 'images']);
    }

    /**
     * Build a URL-safe slug that does not collide with an existing product.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 2;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Adjust the stock quantity of the specified product.
     *
     * @param  string  $type  ('add', 'subtract', 'set')
     *
     * @throws InvalidArgumentException
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
