<?php

namespace App\Modules\Products\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Requests\StoreProductRequest;
use App\Modules\Products\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * List products with pagination and category filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'category_id'        => $request->query('category_id'),
            'second_category_id' => $request->query('second_category_id'),
            'search'             => $request->query('search'),
        ];

        $perPage = (int) $request->query('per_page', 10);
        $products = $this->productService->list($filters, $perPage);

        return $this->successResponse([
            'items' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
                'last_page'    => $products->lastPage(),
            ],
        ], 'Products retrieved successfully.');
    }

    /**
     * Show single product details.
     */
    public function show(int $id): JsonResponse
    {
        $product = $this->productService->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found.', 404);
        }

        return $this->successResponse($product, 'Product details retrieved.');
    }

    /**
     * Store new product with image.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $sellerId = auth('api')->id();
        $product = $this->productService->create(
            $request->validated(),
            $request->file('image'),
            $sellerId
        );

        return $this->successResponse($product, 'Product created successfully.', 201);
    }
}
