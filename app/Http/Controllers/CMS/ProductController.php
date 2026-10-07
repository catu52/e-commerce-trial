<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\AdjustStockRequest;
use App\Http\Requests\CMS\StoreProductRequest;
use App\Models\Product;
use App\Services\ProductStockService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * ProductController constructor.
     *
     * @param  \App\Services\ProductStockService  $stockService
     */
    public function __construct(protected ProductStockService $stockService) {}

    /**
     * Display a paginated list of products.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Retrieve paginated list of products based on query parameters.
        $products = $this->stockService->getPaginatedProducts(
            perPage: (int) $request->query('per_page', 15),
            search: $request->query('search')
        );

        return response()->json($products);
    }

    /**
     * Store a newly created product in the system.
     *
     * @param  \App\Http\Requests\Cms\StoreProductRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->stockService->createProduct($request->validated());

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    /**
     * Display the specified product.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'product' => $product->load('flashSales'),
        ]);
    }

    /**
     * Adjust the stock of the specified product.
     *
     * @param  \App\Http\Requests\Cms\AdjustStockRequest  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function adjustStock(AdjustStockRequest $request, Product $product): JsonResponse
    {
        // Attempt to adjust the stock of the product.
        try {
            // Call the stock service to adjust the product's inventory.
            $updatedProduct = $this->stockService->adjustStock(
                product: $product,
                type: $request->input('adjustment_type'),
                quantity: (int) $request->input('quantity'),
                reason: $request->input('reason'),
                actorUserId: $request->user()->id
            );

            return response()->json([
                'message' => 'Inventory adjusted successfully.',
                'product' => $updatedProduct,
            ]);
        } catch (Exception $e) { // Handle any exceptions that occur during stock adjustment.
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
