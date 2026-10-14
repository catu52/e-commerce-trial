<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicImageUrl
{
    public const DISK = 'public';

    /**
     * Absolute URL of a file on the public disk, or null when no path is set.
     */
    public static function for(?string $path): ?string
    {
        return $path === null || $path === '' ? null : Storage::disk(self::DISK)->url($path);
    }
}
