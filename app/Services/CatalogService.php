<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CatalogService
{
    /**
     * @param  array{category?: mixed, search?: mixed, gender?: ?string, featured?: bool, in_stock?: bool, on_sale?: bool, min_discount?: ?int}  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $category = $filters['category'] ?? null;
        $search = $filters['search'] ?? null;

        return Product::with(['category', 'images'])
            ->when($category, fn ($q) => $q->whereHas('category', function ($cq) use ($category) {
                $cq->where('slug', strtolower($category))->orWhereRaw('lower(name) = ?', [strtolower($category)]);
            }))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->forGender($filters['gender'] ?? null)
            ->when($filters['featured'] ?? false, fn ($q) => $q->where('is_featured', true))
            ->when($filters['in_stock'] ?? false, fn ($q) => $q->inStock())
            ->when(($filters['on_sale'] ?? false) || ($filters['min_discount'] ?? null), fn ($q) => $q->onSale($filters['min_discount'] ?? null))
            ->orderBy('id')
            ->paginate($perPage);
    }

    /** Highest discount percent among products currently on sale (null when none). */
    public function maxDiscount(): ?int
    {
        $max = Product::onSale()->max('sale_discount_percent');

        return $max === null ? null : (int) $max;
    }
}
