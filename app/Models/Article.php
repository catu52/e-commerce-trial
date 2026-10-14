<?php

namespace App\Models;

use App\Support\PublicImageUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug', 'title', 'excerpt', 'body', 'cover_image_path', 'cover_image_alt', 'author_name', 'category',
    'is_featured', 'status', 'published_at', 'seo_title', 'seo_description',
])]
class Article extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'published_at' => 'datetime'];
    }

    /** Visible on the storefront: published and not scheduled for the future. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** draft | scheduled | published, as visitors would experience it right now. */
    public function visibility(): string
    {
        if ($this->status !== self::STATUS_PUBLISHED || $this->published_at === null) {
            return self::STATUS_DRAFT;
        }

        return $this->published_at->isFuture() ? 'scheduled' : self::STATUS_PUBLISHED;
    }

    public function isLive(): bool
    {
        return $this->visibility() === self::STATUS_PUBLISHED;
    }

    public function imageUrl(): ?string
    {
        return PublicImageUrl::for($this->cover_image_path);
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->body)) / 200));
    }
}
