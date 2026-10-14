<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlashSaleIndexRequest;
use App\Http\Resources\FlashSaleResource;
use App\Services\FlashSaleCatalogService;
use Illuminate\Http\JsonResponse;

class FlashSaleController extends Controller
{
    public function __construct(private FlashSaleCatalogService $flashSales) {}

    public function index(FlashSaleIndexRequest $request): JsonResponse
    {
        $filters = [
            'tier' => $request->validated('tier'),
            'category' => $request->validated('category'),
            'size' => $request->validated('size'),
            'sort' => $request->validated('sort'),
        ];

        $sales = $this->flashSales->paginate($filters, (int) $request->validated('per_page', config('flash_sale.default_per_page')));

        return response()->json([
            'data' => FlashSaleResource::collection($sales)->resolve(),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
                ...$this->flashSales->meta($filters),
            ],
        ]);
    }
}
