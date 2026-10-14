<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductGender;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function __construct(private CatalogService $catalog) {}

    /**
     * Public paginated product listing with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'gender' => ['nullable', Rule::enum(ProductGender::class)],
            'on_sale' => ['nullable', 'boolean'],
            'min_discount' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $products = $this->catalog->paginate([
            'category' => $request->query('category'),
            'search' => $request->query('search'),
            'gender' => $request->query('gender'),
            'featured' => $request->boolean('featured'),
            'in_stock' => $request->boolean('in_stock'),
            'on_sale' => $request->boolean('on_sale'),
            'min_discount' => $request->filled('min_discount') ? (int) $request->query('min_discount') : null,
        ], (int) $request->query('per_page', 50));

        return response()->json([
            'data' => ProductResource::collection($products)->resolve(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'max_discount' => $this->catalog->maxDiscount(),
            ],
        ]);
    }

    /**
     * Public product detail by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::with(['category', 'images'])->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => (new ProductResource($product))->resolve()]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
