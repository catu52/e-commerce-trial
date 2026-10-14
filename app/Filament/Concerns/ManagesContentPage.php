<?php

namespace App\Filament\Concerns;

use App\Support\PublicImageUrl;
use Illuminate\Support\Facades\Storage;

/**
 * Shared plumbing for single-record CMS content pages (permission gate, replaced-file cleanup).
 */
trait ManagesContentPage
{
    use AuthorizesByPermission;

    protected static ?string $viewPermission = 'cms.access';

    protected static ?string $managePermission = 'content.manage';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Content';
    }

    public static function canAccess(): bool
    {
        return static::staffCan(static::$viewPermission) && static::staffCan(static::$managePermission);
    }

    /**
     * Delete files that were referenced before saving but are no longer referenced.
     *
     * @param  array<int, string|null>  $before
     * @param  array<int, string|null>  $after
     */
    protected function deleteReplacedFiles(array $before, array $after): void
    {
        foreach (array_diff(array_filter($before), array_filter($after)) as $path) {
            Storage::disk(PublicImageUrl::DISK)->delete($path);
        }
    }

    /** Normalises a FileUpload state (single file, keyed array or null) to a path string. */
    protected static function firstPath(mixed $state): ?string
    {
        if (is_array($state)) {
            $state = reset($state) ?: null;
        }

        return is_string($state) && $state !== '' ? $state : null;
    }
}
