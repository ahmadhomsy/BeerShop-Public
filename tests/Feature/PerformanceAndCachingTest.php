<?php

namespace Tests\Feature;

use App\Exceptions\InventoryServiceUnavailableException;
use App\Http\Middleware\EnsureReadAfterWriteConsistency;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SecondCategory;
use App\Models\User;
use App\Modules\Products\Services\ProductService;
use App\Services\Database\ReadAfterWriteManager;
use App\Services\Inventory\InventoryClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class PerformanceAndCachingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected string $adminToken;
    protected User $sellerUser;
    protected string $sellerToken;
    protected Category $category;
    protected SecondCategory $secondCategory;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->adminUser = User::create([
            'name'     => 'Performance Admin',
            'email'    => 'perf_admin_' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'role'     => User::ROLE_ADMIN,
        ]);
        $this->adminToken = auth('api')->tokenById($this->adminUser->id);

        $this->sellerUser = User::create([
            'name'     => 'Performance Seller',
            'email'    => 'perf_seller_' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'role'     => User::ROLE_SELLER,
        ]);
        $this->sellerToken = auth('api')->tokenById($this->sellerUser->id);

        $this->category = Category::first() ?? Category::create([
            'name'    => 'Perf Category',
            'namear'  => 'تصنيف تجريبي',
            'imgpath' => '/images/perf_cat.png',
        ]);

        $this->secondCategory = SecondCategory::first() ?? SecondCategory::create([
            'name'        => 'Perf SubCategory',
            'namear'      => 'تصنيف فرعي تجريبي',
            'imgpath'     => '/images/perf_subcat.png',
            'category_id' => $this->category->id,
        ]);

        $this->product = Product::create([
            'name'               => 'Perf Test Product',
            'namear'             => 'منتج تجريبي',
            'price'              => 29.99,
            'description'        => 'Performance benchmark product description.',
            'category_id'        => $this->category->id,
            'second_category_id' => $this->secondCategory->id,
            'seller_id'          => $this->sellerUser->id,
            'imgpath'            => 'uploads/perf_test.png',
        ]);

        auth()->forgetGuards();
    }

    /**
     * 1. Test catalog listing is cached in memory and served on subsequent requests.
     */
    public function test_catalog_listing_is_cached_and_serves_from_cache(): void
    {
        /** @var ProductService $service */
        $service = app(ProductService::class);
        $initialVersion = $service->getCatalogVersion();

        // First list call populates cache
        $list1 = $service->list([], 10);
        $this->assertNotEmpty($list1->items());

        // Assert cache key exists for this version
        $cacheKey = "products:catalog:v{$initialVersion}:" . md5(json_encode([
            'filters'  => [],
            'per_page' => 10,
            'page'     => 1,
        ]));
        $this->assertTrue(Cache::has($cacheKey));

        // Second list call retrieves from cache
        $list2 = $service->list([], 10);
        $this->assertSame($list1->total(), $list2->total());
        $this->assertSame($list1->items()[0]->id, $list2->items()[0]->id);
    }

    /**
     * 2. Test creating product invalidates catalog cache and increments catalog version.
     */
    public function test_creating_product_invalidates_catalog_cache_and_increments_version(): void
    {
        /** @var ProductService $service */
        $service = app(ProductService::class);
        $v1 = $service->getCatalogVersion();

        // Populate cache at version v1
        $service->list([], 10);

        // Create new product
        $newProduct = $service->create([
            'name'               => 'Newly Created Perf Product',
            'price'              => 49.99,
            'category_id'        => $this->category->id,
            'second_category_id' => $this->secondCategory->id,
        ], null, $this->sellerUser->id);

        $v2 = $service->getCatalogVersion();
        $this->assertGreaterThan($v1, $v2, 'Catalog version must increment on product creation');

        // Verify newly created product is returned in fresh list query
        $freshList = $service->list([], 10);
        $ids = collect($freshList->items())->pluck('id')->all();
        $this->assertContains($newProduct->id, $ids);
    }

    /**
     * 3. Test updating product invalidates specific product item cache and catalog version.
     */
    public function test_updating_product_invalidates_item_cache_and_catalog_cache(): void
    {
        // Mock InventoryClient for find call
        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('getStock')->andReturn([
            'product_id'      => $this->product->id,
            'total_stock'     => 10,
            'reserved_stock'  => 0,
            'available_stock' => 10,
        ]);
        $this->app->instance(InventoryClient::class, $mockClient);

        /** @var ProductService $service */
        $service = app(ProductService::class);

        // Warm item cache
        $item = $service->find($this->product->id);
        $this->assertNotNull($item);
        $this->assertTrue(Cache::has("product:item:{$this->product->id}"));

        $v1 = $service->getCatalogVersion();

        // Update product metadata
        $service->update($this->product, [
            'name'  => 'Updated Perf Product Name',
            'price' => 99.99,
        ], null);

        // Assert item cache was invalidated
        $this->assertFalse(Cache::has("product:item:{$this->product->id}"));
        $this->assertGreaterThan($v1, $service->getCatalogVersion());

        // Re-read product reflects new price
        $freshItem = $service->find($this->product->id);
        $this->assertSame('Updated Perf Product Name', $freshItem->name);
        $this->assertEquals(99.99, (float) $freshItem->price);
    }

    /**
     * 4. Test HTTP conditional caching (ETag & 304 Not Modified).
     */
    public function test_single_product_returns_etag_and_304_when_unmodified(): void
    {
        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('getStock')->andReturn([
            'product_id'      => $this->product->id,
            'total_stock'     => 15,
            'reserved_stock'  => 2,
            'available_stock' => 13,
        ]);
        $this->app->instance(InventoryClient::class, $mockClient);

        // 1. Initial GET request returns 200 OK with ETag and Cache-Control headers
        $response1 = $this->getJson("/api/v1/products/{$this->product->id}");
        $response1->assertStatus(200);
        $this->assertTrue($response1->headers->has('ETag'));
        $this->assertStringContainsString('must-revalidate', $response1->headers->get('Cache-Control'));

        $etag = $response1->headers->get('ETag');
        $this->assertNotEmpty($etag);

        // 2. Conditional request with matching If-None-Match returns 304 Not Modified with zero content
        $response2 = $this->withHeader('If-None-Match', $etag)
            ->getJson("/api/v1/products/{$this->product->id}");

        $response2->assertStatus(304);
        $this->assertEmpty($response2->getContent());
        $this->assertSame($etag, $response2->headers->get('ETag'));

        // 3. Modifying product price via service invalidates cache and changes the ETag
        /** @var ProductService $service */
        $service = app(ProductService::class);
        $service->update($this->product, ['price' => 77.50], null);

        $response3 = $this->withHeader('If-None-Match', $etag)
            ->getJson("/api/v1/products/{$this->product->id}");

        $response3->assertStatus(200);
        $newEtag = $response3->headers->get('ETag');
        $this->assertNotSame($etag, $newEtag);
        $response3->assertJsonPath('data.price', 77.5);
    }

    /**
     * 5. Test single product retrieves live stock dynamically via gRPC (never stale).
     */
    public function test_single_product_fetches_live_inventory_via_grpc_uncached(): void
    {
        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('getStock')
            ->once()
            ->with($this->product->id)
            ->andReturn([
                'product_id'      => $this->product->id,
                'total_stock'     => 100,
                'reserved_stock'  => 10,
                'available_stock' => 90,
            ]);
        $this->app->instance(InventoryClient::class, $mockClient);

        /** @var ProductService $service */
        $service = app(ProductService::class);
        $product = $service->find($this->product->id);

        $this->assertNotNull($product);
        $this->assertSame(90, $product->available_stock);
        $this->assertSame(100, $product->stock['total_stock']);

        // Test propagation of 503 when inventory service is down
        $downClient = Mockery::mock(InventoryClient::class);
        $downClient->shouldReceive('getStock')
            ->once()
            ->andThrow(new InventoryServiceUnavailableException('Microservice down'));
        $this->app->instance(InventoryClient::class, $downClient);

        $this->expectException(InventoryServiceUnavailableException::class);
        $freshService = $this->app->make(ProductService::class);
        $freshService->find($this->product->id);
    }

    /**
     * 6. Test graceful cache failure fallback to database without HTTP 500.
     */
    public function test_cache_store_failure_falls_back_to_database_gracefully(): void
    {
        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('getStock')->andReturn([
            'product_id'      => $this->product->id,
            'total_stock'     => 5,
            'reserved_stock'  => 0,
            'available_stock' => 5,
        ]);
        $this->app->instance(InventoryClient::class, $mockClient);

        // Force Cache::remember to throw an exception simulating Redis disconnection
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('Redis connection timeout'));
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('Redis connection timeout'));

        /** @var ProductService $service */
        $service = app(ProductService::class);

        // Catalog list should still succeed via database fallback
        $list = $service->list([], 10);
        $this->assertNotNull($list);
        $this->assertGreaterThanOrEqual(1, $list->total());

        // Single product find should still succeed via database fallback
        $item = $service->find($this->product->id);
        $this->assertNotNull($item);
        $this->assertSame($this->product->id, $item->id);
    }

    /**
     * 7. Test read-after-write consistency pinning mechanism.
     */
    public function test_read_after_write_consistency_pins_user_to_write_connection(): void
    {
        $rawManager = app(ReadAfterWriteManager::class);

        // 1. Unpinned user is not pinned
        $this->assertFalse($rawManager->isUserPinned(99999));

        // 2. Pin user
        $rawManager->pinUser(99999, 10);
        $this->assertTrue($rawManager->isUserPinned(99999));

        // 3. Pin resource
        $rawManager->pinResource("product:{$this->product->id}", 10);
        $this->assertTrue($rawManager->isResourcePinned("product:{$this->product->id}"));

        // 4. Test middleware triggers forcePrimaryRead
        $middleware = app(EnsureReadAfterWriteConsistency::class);
        $request = Request::create("/api/v1/products/{$this->product->id}", 'GET');
        $request->setUserResolver(fn () => $this->sellerUser);

        // Pin sellerUser
        $rawManager->pinUser($this->sellerUser->id, 10);

        $executed = false;
        $response = $middleware->handle($request, function ($req) use (&$executed) {
            $executed = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($executed);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * 8. Test eager loading prevents N+1 queries on catalog listing.
     */
    public function test_eager_loading_prevents_n_plus_one_queries_on_catalog_listing(): void
    {
        Cache::flush();

        // Create 5 additional products
        for ($i = 1; $i <= 5; $i++) {
            Product::create([
                'name'               => "Batch Product {$i}",
                'price'              => 10.0 + $i,
                'category_id'        => $this->category->id,
                'second_category_id' => $this->secondCategory->id,
                'seller_id'          => $this->sellerUser->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/products?per_page=10');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Count of queries must be bounded regardless of number of products:
        // 1 count query + 1 products select + 1 categories select + 1 second_categories select + 1 users select
        $this->assertLessThanOrEqual(6, count($queries), 'Product catalog queries must be strictly bounded to prevent N+1 issues.');
    }
}
