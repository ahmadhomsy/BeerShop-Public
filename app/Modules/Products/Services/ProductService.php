<?php

namespace App\Modules\Products\Services;

use App\Models\Product;
use App\Modules\Products\Contracts\ProductCatalogInterface;
use App\Services\Inventory\InventoryClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductService implements ProductCatalogInterface
{
    protected InventoryClient $inventoryClient;

    public function __construct(InventoryClient $inventoryClient)
    {
        $this->inventoryClient = $inventoryClient;
    }

    /**
     * Get paginated products with optional category filter.
     */
    public function list(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Product::with(['category', 'secondcategory', 'user:id,name,email']);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['second_category_id'])) {
            $query->where('second_category_id', $filters['second_category_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('namear', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Find single product by ID with current live stock from Inventory Microservice.
     * When inventory microservice is unavailable, propagates InventoryServiceUnavailableException (HTTP 503).
     */
    public function find(int $id): ?Product
    {
        $product = Product::with(['category', 'secondcategory', 'user:id,name,email', 'images'])->find($id);

        if ($product) {
            $stockInfo = $this->inventoryClient->getStock($id);
            $product->stock = $stockInfo;
            $product->available_stock = $stockInfo['available_stock'] ?? 0;
        }

        return $product;
    }

    /**
     * Implementation of ProductCatalogInterface for cross-module catalog access.
     */
    public function getProductsByIds(array $productIds): Collection
    {
        return Product::whereIn('id', $productIds)->get()->keyBy('id');
    }

    /**
     * Create product with image. Stock is managed by Inventory Microservice.
     */
    public function create(array $data, ?UploadedFile $image, int $sellerId): Product
    {
        $imgPath = null;
        if ($image) {
            $filename = Str::uuid()->toString() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads'), $filename);
            $imgPath = 'uploads/' . $filename;
        }

        return Product::create([
            'name'               => $data['name'],
            'namear'             => $data['namear'] ?? null,
            'price'              => $data['price'],
            'description'        => $data['description'] ?? null,
            'category_id'        => $data['category_id'],
            'second_category_id' => $data['second_category_id'] ?? 1,
            'seller_id'          => $sellerId,
            'imgpath'            => $imgPath,
        ]);
    }
}
