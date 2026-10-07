<?php

namespace App\Models;

use App\Contracts\ReservableInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'created_by', 'sale_price', 'total_stock', 'available_stock', 'starts_at', 'ends_at', 'is_active'])]

class FlashSale extends Model implements ReservableInterface
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sale_price' => 'float',
            'total_stock' => 'integer',
            'available_stock' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /* ========== Relationships ========== */

    /**
     * Get the product that owns the flash sale.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the orders associated with the flash sale.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the reservations associated with the flash sale.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Get the user who created the flash sale.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ========== Scopes ========== */

    /**
     * Scope a query to only include active flash sales.
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
    }

    /**
     * Scope a query to only include upcoming flash sales.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('starts_at', '>', now());
    }

    /**
     * Scope a query to only include expired flash sales.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('ends_at', '<', now());
    }

    /* ========== Interface Implementations & domain logic ========== */

    public function getReservableId(): int
    {
        return $this->id;
    }

    public function getAvailableStock(): int
    {
        return $this->available_stock;
    }

    public function isCurrentlyActive(): bool
    {
        $now = now();

        return $this->is_active &&
            $this->starts_at <= $now &&
            $this->ends_at >= $now;
    }

    public function isSoldOut(): bool
    {
        return $this->available_stock <= 0;
    }

    /**
     * Get the discounted percentage for the flash sale.
     */
    public function getDiscountedPercentage(): float
    {
        if (! $this->product || $this->product->base_price <= 0) {
            return 0.0;
        }

        $discount = (($this->product->base_price - $this->sale_price) / $this->product->base_price) * 100;

        return round(max(0, $discount), 2);
    }
}
