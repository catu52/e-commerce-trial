<?php

namespace App\Http\Resources;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Article */
class ArticleResource extends JsonResource
{
    private bool $withBody = false;

    public static function detail(Article $article): static
    {
        $resource = new static($article);
        $resource->withBody = true;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
        ];

        if ($this->withBody) {
            $data['body'] = $this->body;
        }

        return $data + [
            'coverImage' => $this->imageUrl(),
            'coverImageAlt' => $this->cover_image_alt ?: $this->title,
            'author' => $this->author_name ?: 'KRAFT Studio',
            'category' => $this->category,
            'isFeatured' => (bool) $this->is_featured,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'readingMinutes' => $this->readingMinutes(),
            'seoTitle' => $this->seo_title,
            'seoDescription' => $this->seo_description,
        ];
    }
}
