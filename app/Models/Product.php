<?php

namespace App\Models;

use App\Contracts\PurchasableInterface;
use App\Enums\ProductGender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'sku', 'description', 'base_price', 'stock_quantity', 'slug', 'code', 'category_id',
    'sub_category', 'tag', 'badge', 'substrate', 'spec_weight', 'colorway', 'is_featured',
    'currency', 'sizes', 'colors', 'specs', 'gender',
    'headline', 'long_description', 'care_instructions', 'seo_title', 'seo_description',
    'sale_discount_percent', 'sale_starts_at', 'sale_ends_at',
])]
class Product extends Model implements PurchasableInterface
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'base_price' => 'float',
            'stock_quantity' => 'integer',
            'is_featured' => 'boolean',
            'sizes' => 'array',
            'colors' => 'array',
            'specs' => 'array',
            'gender' => ProductGender::class,
            'sale_discount_percent' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
        ];
    }

    /* ========== Relationships ========== */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('position')->orderBy('id');
    }

    /**
     * Get the flash sales associated with the product.
     */
    public function flashSales(): HasMany
    {
        return $this->hasMany(FlashSale::class);
    }

    /**
     * Get the orders associated with the product.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /* ========== Query Scopes ========== */

    /**
     * Scope a query to only include products that are in stock.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /**
     * Scope a query to a gender section (men/women also include unisex items).
     */
    public function scopeForGender(Builder $query, ProductGender|string|null $gender): Builder
    {
        if ($gender === null || $gender === '') {
            return $query;
        }

        $gender = $gender instanceof ProductGender ? $gender : ProductGender::from($gender);

        return $query->whereIn('gender', $gender->visibleGenders());
    }

    /**
     * Scope to products with a regular sale active right now (optionally at least $minDiscount %).
     */
    public function scopeOnSale(Builder $query, ?int $minDiscount = null): Builder
    {
        $now = now();

        return $query
            ->whereNotNull('sale_discount_percent')
            ->where('sale_discount_percent', '>=', max(1, $minDiscount ?? 1))
            ->where(fn (Builder $q) => $q->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>', $now));
    }

    /* ========== Interface Implementations & Domain logic ========== */

    public function getPurchasableId(): int
    {
        return $this->id;
    }

    public function getPrice(): float
    {
        return $this->salePrice();
    }

    public function isOnSale(): bool
    {
        if ($this->sale_discount_percent === null || $this->sale_discount_percent < 1) {
            return false;
        }

        $now = now();

        return ($this->sale_starts_at === null || $this->sale_starts_at->lte($now))
            && ($this->sale_ends_at === null || $this->sale_ends_at->gt($now));
    }

    /** Effective price: discounted while a regular sale is active, otherwise the base price. */
    public function salePrice(): float
    {
        if (! $this->isOnSale()) {
            return (float) $this->base_price;
        }

        return round($this->base_price * (100 - $this->sale_discount_percent) / 100, 2);
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function hasStock(): bool
    {
        return $this->stock_quantity > 0;
    }
}
