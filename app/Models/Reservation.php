<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Attributes for mass assignment and hidden fields
#[Fillable(['client_id', 'flash_sale_id', 'quantity', 'status', 'expires_at'])]

class Reservation extends Model
{
    use HasFactory;

    /**
     * Get the casts for the model's attributes.
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => ReservationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    /* ========== Relationships ========== */

    /**
     * Get the client that owns the reservation.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the flash sale that owns the reservation.
     */
    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    /**
     * Get the order associated with the reservation.
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    /* ========== Scopes ========== */

    /**
     * Scope a query to only include active holds.
     */
    public function scopeActiveHold(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::RESERVED)
            ->where('expires_at', '>', now());
    }

    /**
     * Scope a query to only include overdue holds.
     * Meaning the reservation has passed its expiration date.
     */
    public function scopeOverdueHolds(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::RESERVED)
            ->where('expires_at', '<=', now());
    }

    /* ========== State Transitions ========== */

    /**
     * Determine if the reservation is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === ReservationStatus::EXPIRED ||
            ($this->status === ReservationStatus::RESERVED && $this->expires_at->isPast());
    }

    public function isReserved(): bool
    {
        return $this->status === ReservationStatus::RESERVED && ! $this->isExpired();
    }

    public function isCompleted(): bool
    {
        return $this->status === ReservationStatus::COMPLETED;
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => ReservationStatus::COMPLETED]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => ReservationStatus::EXPIRED]);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => ReservationStatus::CANCELLED]);
    }
}
