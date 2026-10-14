<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FlashSale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class FlashSaleCatalogService
{
    private const DISCOUNT_SQL = '(products.base_price - flash_sales.sale_price) * 100';

    /**
     * @param  array{tier?: ?string, category?: ?string, size?: ?string, sort?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->filtered($filters)
            ->with(['product.category', 'product.images'])
            ->select('flash_sales.*');

        $this->applySort($query, $filters['sort'] ?? 'discount');

        return $query->paginate($perPage);
    }

    /**
     * @param  array{tier?: ?string, category?: ?string, size?: ?string}  $filters
     * @return array{nextEndsAt: ?string, serverTime: string, tiers: array<string, int>, categories: array<int, array{name: string, slug: string, count: int}>}
     */
    public function meta(array $filters): array
    {
        $tiers = ['all' => $this->filtered([...$filters, 'tier' => null])->count()];
        foreach (config('flash_sale.tiers') as $tier) {
            $tiers[(string) $tier] = $this->filtered([...$filters, 'tier' => (string) $tier])->count();
        }

        $counts = $this->filtered([...$filters, 'category' => null])
            ->reorder()
            ->selectRaw('products.category_id, count(*) as aggregate')
            ->groupBy('products.category_id')
            ->pluck('aggregate', 'category_id');

        $categories = Category::whereIn('id', $counts->keys())->orderBy('name')->get()
            ->map(fn (Category $c) => ['name' => strtoupper($c->name), 'slug' => $c->slug, 'count' => (int) $counts[$c->id]])
            ->values()->all();

        return [
            'nextEndsAt' => FlashSale::active()->orderBy('ends_at')->first()?->ends_at->toIso8601String(),
            'serverTime' => now()->toIso8601String(),
            'tiers' => $tiers,
            'categories' => $categories,
        ];
    }

    /**
     * Active and upcoming sales matching the filters.
     *
     * @param  array{tier?: ?string, category?: ?string, size?: ?string}  $filters
     */
    private function filtered(array $filters): Builder
    {
        $tier = $filters['tier'] ?? null;
        $category = $filters['category'] ?? null;
        $size = $filters['size'] ?? null;

        return FlashSale::query()
            ->join('products', 'products.id', '=', 'flash_sales.product_id')
            ->where('flash_sales.is_active', true)
            ->where('flash_sales.ends_at', '>=', now())
            ->when($tier && $tier !== 'all', fn (Builder $q) => $q->whereRaw(self::DISCOUNT_SQL.' >= ? * products.base_price', [(int) $tier]))
            ->when($category, fn (Builder $q) => $q->whereIn('products.category_id', Category::query()
                ->where('slug', strtolower($category))
                ->orWhereRaw('lower(name) = ?', [strtolower($category)])
                ->select('id')))
            ->when($size, fn (Builder $q) => $q->whereJsonContains('products.sizes', $size));
    }

    private function applySort(Builder $query, string $sort): void
    {
        $query->orderByRaw('case when flash_sales.starts_at <= ? then 0 else 1 end', [now()]);

        match ($sort) {
            'depletion' => $query->orderByRaw('(flash_sales.total_stock - flash_sales.available_stock) * 1.0 / flash_sales.total_stock desc'),
            'price_asc' => $query->orderBy('flash_sales.sale_price'),
            'price_desc' => $query->orderByDesc('flash_sales.sale_price'),
            default => $query->orderByRaw(self::DISCOUNT_SQL.' / products.base_price desc'),
        };

        $query->orderBy('flash_sales.id');
    }
}
