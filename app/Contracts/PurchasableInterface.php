<?php

namespace App\Contracts;

interface PurchasableInterface
{
    /**
     * Get the ID of the purchasable item.
     *
     * @return int
     */
    public function getPurchasableId(): int;

    /**
     * Get the price of the purchasable item.
     *
     * @return float
     */
    public function getPrice(): float;

    /**
     * Get the SKU of the purchasable item.
     *
     * @return string
     */
    public function getSku(): string;
}
