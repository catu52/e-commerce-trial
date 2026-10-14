<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Services\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function __construct(private readonly ArticleService $articles) {}

    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $paginator = $this->articles->paginate(
            is_string($category) ? $category : null,
            (int) $request->query('page', 1),
            (int) $request->query('per_page', ArticleService::DEFAULT_PER_PAGE),
        );

        return response()->json([
            'data' => ArticleResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'categories' => $this->articles->categories(),
        ]);
    }

    public function preview(string $slug): JsonResponse
    {
        $article = $this->articles->findAnyBySlug($slug);

        if ($article === null) {
            return response()->json(['message' => 'Article not found.'], 404);
        }

        return response()->json([
            'data' => ArticleResource::detail($article)->resolve(),
            'related' => ArticleResource::collection($this->articles->related($article))->resolve(),
            'preview' => ['status' => $article->visibility()],
        ], 200, ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function show(string $slug): JsonResponse
    {
        $article = $this->articles->findPublishedBySlug($slug);

        if ($article === null) {
            return response()->json(['message' => 'Article not found.'], 404);
        }

        return response()->json([
            'data' => ArticleResource::detail($article)->resolve(),
            'related' => ArticleResource::collection($this->articles->related($article))->resolve(),
        ]);
    }
}
