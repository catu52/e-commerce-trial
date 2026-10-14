<?php

namespace App\Models;

use App\Support\PublicImageUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hero_eyebrow', 'hero_title', 'hero_subtitle', 'editorial_title', 'editorial_body', 'editorial_image_path',
])]
class HomePage extends Model
{
    /**
     * The single home page row, created empty on first access.
     */
    public static function current(): self
    {
        $row = static::query()->firstOrCreate([]);

        // Load DB column defaults on a freshly created row.
        return $row->wasRecentlyCreated ? $row->refresh() : $row;
    }

    public function lookbookItems(): HasMany
    {
        return $this->hasMany(LookbookItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function editorialImageUrl(): ?string
    {
        return PublicImageUrl::for($this->editorial_image_path);
    }
}
