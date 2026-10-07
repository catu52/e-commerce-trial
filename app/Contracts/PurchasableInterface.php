<?php

namespace App\Contracts;

interface PurchasableInterface
{
    /**
     * Get the ID of the purchasable item.
     */
    public function getPurchasableId(): int;

    /**
     * Get the price of the purchasable item.
     */
    public function getPrice(): float;

    /**
     * Get the SKU of the purchasable item.
     */
    public function getSku(): string;
}
