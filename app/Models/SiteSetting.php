<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['announcement', 'footer_tagline'])]
class SiteSetting extends Model
{
    /**
     * The single settings row, created empty on first access.
     */
    public static function current(): self
    {
        $row = static::query()->firstOrCreate([]);

        // Load DB column defaults on a freshly created row.
        return $row->wasRecentlyCreated ? $row->refresh() : $row;
    }
}
