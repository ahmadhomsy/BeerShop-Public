<?php

namespace App\Modules\Products\Services;

use App\Models\Product;
use App\Modules\Products\Contracts\ProductCatalogInterface;
use App\Services\Database\ReadAfterWriteManager;
use App\Services\Inventory\InventoryClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductService implements ProductCatalogInterface
{
    public const CATALOG_TTL_SECONDS = 300;      // 5 minutes
    public const PRODUCT_ITEM_TTL_SECONDS = 300;  // 5 minutes
    public const CATALOG_VERSION_KEY = 'products:catalog:version';

    protected InventoryClient $inventoryClient;

    public function __construct(InventoryClient $inventoryClient)
    {
        $this->inventoryClient = $inventoryClient;
    }

    /**
     * Get paginated products with optional category filter.
     * When user has an active write pin, bypasses cache to guarantee read-after-write consistency.
     * Otherwise cached with versioned keys and resilient fallback on cache store failures.
     */
    public function list(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $rawManager = app(ReadAfterWriteManager::class);
        $user = auth('api')->user();

        // If user is pinned for read-after-write consistency, bypass cache and query primary database
        if ($user && $rawManager->isUserPinned($user->id)) {
            return $this->queryCatalogFromDatabase($filters, $perPage);
        }

        $page = (int) request('page', 1);
        $version = $this->getCatalogVersion();
        $cacheKey = "products:catalog:v{$version}:" . md5(json_encode([
            'filters'  => $filters,
            'per_page' => $perPage,
            'page'     => $page,
        ]));

        try {
            return Cache::remember($cacheKey, self::CATALOG_TTL_SECONDS, function () use ($filters, $perPage) {
                return $this->queryCatalogFromDatabase($filters, $perPage);
            });
        } catch (\Throwable $e) {
            Log::warning("[ProductService] Cache list failure; falling back to direct database query: " . $e->getMessage());
            return $this->queryCatalogFromDatabase($filters, $perPage);
        }
    }

    /**
     * Query authoritative database for catalog listing.
     */
    protected function queryCatalogFromDatabase(array $filters, int $perPage): LengthAwarePaginator
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
     * Find single product by ID with stable metadata cached in Redis,
     * but live inventory stock ALWAYS fetched in real time via gRPC.
     * When user or resource is pinned, bypasses cache to guarantee read-after-write consistency.
     * When inventory microservice is unavailable, propagates InventoryServiceUnavailableException (HTTP 503).
     */
    public function find(int $id): ?Product
    {
        $rawManager = app(ReadAfterWriteManager::class);
        $user = auth('api')->user();
        $isPinned = ($user && $rawManager->isUserPinned($user->id)) || $rawManager->isResourcePinned("product:{$id}");

        if ($isPinned) {
            // Direct authoritative database read
            $product = Product::with(['category', 'secondcategory', 'user:id,name,email', 'images'])->find($id);
        } else {
            $cacheKey = "product:item:{$id}";
            try {
                $product = Cache::remember($cacheKey, self::PRODUCT_ITEM_TTL_SECONDS, function () use ($id) {
                    return Product::with(['category', 'secondcategory', 'user:id,name,email', 'images'])->find($id);
                });
            } catch (\Throwable $e) {
                Log::warning("[ProductService] Cache item failure for product {$id}; falling back to database: " . $e->getMessage());
                $product = Product::with(['category', 'secondcategory', 'user:id,name,email', 'images'])->find($id);
            }
        }

        if ($product) {
            // ALWAYS retrieve live stock from Python gRPC microservice (strictly uncached)
            $stockInfo = $this->inventoryClient->getStock($id);

            // Clone to avoid mutating in-memory cache representations
            $product = clone $product;
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
     * Invalidates catalog cache and pins user for read-after-write consistency.
     */
    public function create(array $data, ?UploadedFile $image, int $sellerId): Product
    {
        $imgPath = null;
        if ($image) {
            $filename = Str::uuid()->toString() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads'), $filename);
            $imgPath = 'uploads/' . $filename;
        }

        $product = DB::transaction(function () use ($data, $image, $sellerId, $imgPath) {
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
        });

        // Invalidate catalog list cache across primary Redis and durable file store
        $this->invalidateCatalogCache();

        // Pin user for read-after-write consistency
        app(ReadAfterWriteManager::class)->pinUser($sellerId);

        return $product;
    }

    /**
     * Update product metadata.
     * Invalidates affected item cache and catalog list cache.
     */
    public function update(Product $product, array $data, ?UploadedFile $image): Product
    {
        if ($image) {
            $filename = Str::uuid()->toString() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads'), $filename);
            $data['imgpath'] = 'uploads/' . $filename;
        }

        DB::transaction(function () use ($product, $data) {
            $product->update($data);
        });

        // Invalidate item cache and catalog list cache
        $this->invalidateProductCache($product->id);
        $this->invalidateCatalogCache();

        // Pin user and resource for read-after-write consistency
        $userId = auth('api')->id() ?? $product->seller_id;
        app(ReadAfterWriteManager::class)->pinUser($userId);
        app(ReadAfterWriteManager::class)->pinResource("product:{$product->id}");

        return $product->fresh(['category', 'secondcategory', 'user:id,name,email']);
    }

    /**
     * Get current catalog cache version number.
     * Reconciles Redis with secondary durable store to prevent resurrecting stale caches
     * after temporary Redis partitions during invalidation.
     */
    public function getCatalogVersion(): int
    {
        $vRedis = 1;
        $vFile = 1;

        try {
            $vRedis = (int) Cache::get(self::CATALOG_VERSION_KEY, 1);
        } catch (\Throwable $e) {
            // Redis error handled
        }

        try {
            $vFile = (int) Cache::store('file')->get(self::CATALOG_VERSION_KEY, 1);
        } catch (\Throwable $e) {
            // File error handled
        }

        $version = max($vRedis, $vFile, 1);

        // If durable file version is ahead of Redis, reconcile Redis with higher version
        if ($version > $vRedis) {
            try {
                Cache::put(self::CATALOG_VERSION_KEY, $version, 86400 * 30);
            } catch (\Throwable $e) {
                // Redis temporarily down
            }
        }

        return $version;
    }

    /**
     * Invalidate catalog cache globally across Redis and durable secondary store in O(1).
     */
    public function invalidateCatalogCache(): void
    {
        // 1. Primary Redis version increment
        try {
            if (!Cache::has(self::CATALOG_VERSION_KEY)) {
                Cache::put(self::CATALOG_VERSION_KEY, 2, 86400 * 30);
            } else {
                Cache::increment(self::CATALOG_VERSION_KEY);
            }
        } catch (\Throwable $e) {
            Log::warning("[ProductService] Invalidate catalog cache failure in Redis: " . $e->getMessage());
        }

        // 2. Secondary durable store increment to guarantee resilience against Redis recovery
        try {
            if (!Cache::store('file')->has(self::CATALOG_VERSION_KEY)) {
                Cache::store('file')->put(self::CATALOG_VERSION_KEY, 2, 86400 * 30);
            } else {
                Cache::store('file')->increment(self::CATALOG_VERSION_KEY);
            }
        } catch (\Throwable $e) {
            // File fallback
        }
    }

    /**
     * Invalidate specific product item cache across stores.
     */
    public function invalidateProductCache(int $productId): void
    {
        try {
            Cache::forget("product:item:{$productId}");
        } catch (\Throwable $e) {
            Log::warning("[ProductService] Invalidate product item cache failure: " . $e->getMessage());
        }

        try {
            Cache::store('file')->forget("product:item:{$productId}");
        } catch (\Throwable $e) {
            // File fallback
        }
    }
}
