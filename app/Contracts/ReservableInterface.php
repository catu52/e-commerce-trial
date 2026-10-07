<?php

namespace App\Contracts;

interface ReservableInterface
{
    /**
     * Get the ID of the reservable item.
     */
    public function getReservableId(): int;

    /**
     * Get the available stock of the reservable item.
     */
    public function getAvailableStock(): int;

    /**
     * Determine if the reservable item is currently active.
     */
    public function isCurrentlyActive(): bool;

    /**
     * Determine if the reservable item is sold out.
     */
    public function isSoldOut(): bool;
}
