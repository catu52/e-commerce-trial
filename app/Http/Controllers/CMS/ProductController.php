<?php

namespace App\Http\Controllers\CMS;

use App\Enums\ProductGender;
use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\AdjustStockRequest;
use App\Http\Requests\CMS\DeleteProductImageRequest;
use App\Http\Requests\CMS\StoreProductRequest;
use App\Http\Requests\CMS\UpdateProductRequest;
use App\Http\Requests\CMS\UploadProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use App\Services\ProductStockService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * ProductController constructor.
     */
    public function __construct(protected ProductStockService $stockService,
        protected ProductImageService $imageService,
    ) {}

    /**
     * Display a paginated list of products.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['gender' => ['nullable', Rule::enum(ProductGender::class)]]);

        // Retrieve paginated list of products based on query parameters.
        $products = $this->stockService->getPaginatedProducts(
            perPage: (int) $request->query('per_page', 15),
            search: $request->query('search'),
            gender: $request->query('gender')
        );

        return response()->json($products);
    }

    /**
     * Store a newly created product in the system.
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
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'product' => $product->load(['flashSales', 'category', 'images']),
        ]);
    }

    /**
     * Partially update the specified product (including editorial fields).
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $this->stockService->updateProduct($product, $request->validated()),
        ]);
    }

    /**
     * Upload an image for the product to the public disk.
     */
    public function uploadImage(UploadProductImageRequest $request, Product $product): JsonResponse
    {
        $image = $this->imageService->upload($product, $request->file('image'), $request->boolean('is_primary'));

        return response()->json([
            'message' => 'Image uploaded successfully.',
            'image' => [
                'id' => $image->id,
                'url' => $image->url(),
                'position' => $image->position,
                'is_primary' => $image->is_primary,
            ],
        ], 201);
    }

    /**
     * Delete a product image and its stored file.
     */
    public function deleteImage(DeleteProductImageRequest $request, Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        $this->imageService->delete($product, $image);

        return response()->json(['message' => 'Image deleted successfully.']);
    }

    /**
     * Adjust the stock of the specified product.
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
