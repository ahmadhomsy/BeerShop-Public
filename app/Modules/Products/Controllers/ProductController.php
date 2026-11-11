<?php

namespace App\Modules\Products\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Products\Requests\StoreProductRequest;
use App\Modules\Products\Requests\UpdateProductRequest;
use App\Modules\Products\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
     * Show single product details with HTTP conditional caching (ETag & 304 Not Modified).
     */
    public function show(Request $request, int $id): Response
    {
        $product = $this->productService->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found.', 404);
        }

        // Deterministic, canonical representation for ETag calculation
        $etagRepresentation = [
            'id'                 => $product->id,
            'name'               => $product->name,
            'price'              => (string) $product->price,
            'description'        => $product->description,
            'category_id'        => $product->category_id,
            'second_category_id' => $product->second_category_id,
            'updated_at'         => optional($product->updated_at)->toIso8601String(),
            'stock'              => $product->stock ?? null,
            'available_stock'    => $product->available_stock ?? null,
        ];

        $etag = '"' . sha1(json_encode($etagRepresentation)) . '"';

        // Check incoming If-None-Match header
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch) {
            $clientEtags = array_map('trim', explode(',', $ifNoneMatch));
            if (
                in_array($etag, $clientEtags, true) ||
                in_array(trim($etag, '"'), $clientEtags, true) ||
                in_array('*', $clientEtags, true)
            ) {
                return response('', 304, [
                    'ETag'          => $etag,
                    'Cache-Control' => 'public, max-age=0, must-revalidate',
                ]);
            }
        }

        $response = $this->successResponse($product, 'Product details retrieved.');
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'public, max-age=0, must-revalidate');

        return $response;
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

    /**
     * Update product with RBAC/ownership validation and automatic cache invalidation.
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $user = auth('api')->user();
        if ($user->role !== 'admin' && $product->seller_id !== $user->id) {
            return $this->errorResponse('Unauthorized to update this product.', 403);
        }

        $updatedProduct = $this->productService->update(
            $product,
            $request->validated(),
            $request->file('image')
        );

        return $this->successResponse($updatedProduct, 'Product updated successfully.');
    }
}
