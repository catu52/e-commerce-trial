<?php

namespace App\Contracts;

interface ReservableInterface
{
    /**
     * Get the ID of the reservable item.
     *
     * @return int
     */
    public function getReservableId(): int;

    /**
     * Get the available stock of the reservable item.
     *
     * @return int
     */
    public function getAvailableStock(): int;

    /**
     * Determine if the reservable item is currently active.
     *
     * @return bool
     */
    public function isCurrentlyActive(): bool;

    /**
     * Determine if the reservable item is sold out.
     *
     * @return bool
     */
    public function isSoldOut(): bool;
}
