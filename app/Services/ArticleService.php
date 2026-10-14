<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ArticleService
{
    public const DEFAULT_PER_PAGE = 9;

    public const MAX_PER_PAGE = 24;

    public const RELATED_LIMIT = 3;

    public function paginate(?string $category, int $page, int $perPage): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        return Article::published()
            ->when($category !== null && $category !== '', fn ($q) => $q->where('category', $category))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    /** @return list<string> */
    public function categories(): array
    {
        return Article::published()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    public function findPublishedBySlug(string $slug): ?Article
    {
        return Article::published()->where('slug', $slug)->first();
    }

    public function findAnyBySlug(string $slug): ?Article
    {
        return Article::where('slug', $slug)->first();
    }

    /** @return Collection<int, Article> */
    public function related(Article $article): Collection
    {
        $query = Article::published()->where('id', '!=', $article->id);

        if ($article->category !== null && $article->category !== '') {
            $query->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$article->category]);
        }

        return $query->orderByDesc('published_at')->orderByDesc('id')->limit(self::RELATED_LIMIT)->get();
    }
}
