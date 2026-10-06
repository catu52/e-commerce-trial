<?php

namespace App\Models;

use App\Contracts\PurchasableInterface;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\FlashSale;
use App\Models\Order;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'sku', 'description', 'base_price', 'stock_quantity'])]

class Product extends Model implements PurchasableInterface
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'base_price' => 'float',
            'stock_quantity' => 'integer',
        ];
    }

    /* ========== Relationships ========== */

    /**
     * Get the flash sales associated with the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function flashSales(): HasMany
    {
        return $this->hasMany(FlashSale::class);
    }

    /**
     * Get the orders associated with the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /* ========== Query Scopes ========== */

    /**
     * Scope a query to only include products that are in stock.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /* ========== Interface Implementations & Domain logic ========== */

    public function getPurchasableId(): int
    {
        return $this->id;
    }

    public function getPrice(): float
    {
        return $this->base_price;
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
