<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Client;
use App\Models\Reservation;
use App\Models\FlashSale;
use App\Models\Product;

use App\Enums\OrderStatus;

use Illuminate\Database\Eloquent\Attributes\Fillable;

// Attributes for mass assignment and hidden fields
#[Fillable(['client_id', 'reservation_id', 'flash_sale_id', 'product_id', 'quantity', 'status', 'total_amount'])]

class Order extends Model
{
    use HasFactory;

    /**
     * Get the casts for the model's attributes.
     *
     * @return array
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => OrderStatus::class,
            'total_amount' => 'float',
        ];
    }

    /* ========== Relationships ========== */

    /**
     * Get the client that owns the order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the reservation that owns the order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Get the flash sale that owns the order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */ 
    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    /**
     * Get the product that owns the order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /* ========== Scopes ========== */

    /**
     * Scope a query to only include completed orders.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::COMPLETED);
    }

    /**
     * Scope a query to only include pending orders.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::PENDING);
    }

    /**
     * Scope a query to only include failed orders.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::FAILED);
    }

    /* ========== State Transitions ========== */

    public function isCompleted(): bool
    {
        return $this->status === OrderStatus::COMPLETED;
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => OrderStatus::PROCESSING]);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => OrderStatus::COMPLETED]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => OrderStatus::FAILED]);
    }

}
