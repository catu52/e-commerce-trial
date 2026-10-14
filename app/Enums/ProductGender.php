<?php

namespace App\Enums;

enum ProductGender: string
{
    case MEN = 'men';
    case WOMEN = 'women';
    case UNISEX = 'unisex';

    /**
     * Genders a product may have to be listed under this section.
     *
     * @return array<int, string>
     */
    public function visibleGenders(): array
    {
        return match ($this) {
            self::MEN => [self::MEN->value, self::UNISEX->value],
            self::WOMEN => [self::WOMEN->value, self::UNISEX->value],
            self::UNISEX => [self::UNISEX->value],
        };
    }
}
