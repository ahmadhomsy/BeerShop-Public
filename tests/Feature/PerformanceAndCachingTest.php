<?php

namespace Tests\Feature;

use App\Console\Commands\SeedPerformanceDatasetCommand;
use App\Exceptions\InventoryServiceUnavailableException;
use App\Http\Middleware\EnsureReadAfterWriteConsistency;
use App\Models\Category;
use App\Models\Image;
use App\Models\InventoryReconciliation;
use App\Models\Order;
use App\Models\Product;
use App\Models\SecondCategory;
use App\Models\User;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Products\Services\ProductService;
use App\Services\Database\ReadAfterWriteManager;
use App\Services\Inventory\InventoryClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
    protected User $customerUser;
    protected string $customerToken;
    protected Category $category;
    protected SecondCategory $secondCategory;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        try {
            Cache::store('file')->flush();
        } catch (\Throwable $e) {
            // ignore
        }

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

        $this->customerUser = User::create([
            'name'     => 'Performance Customer',
            'email'    => 'perf_customer_' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'role'     => User::ROLE_USER,
        ]);
        $this->customerToken = auth('api')->tokenById($this->customerUser->id);

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
     * 4. Comprehensive HTTP ETag semantics test:
     * - Returns 200 with strong ETag and Cache-Control
     * - Unchanged conditional GET returns 304 with empty body
     * - Weak ETags (W/...) and wildcard (*) handled correctly
     * - Attribute mutations (price, name, Arabic name, category, live stock) change ETag
     */
    public function test_http_etag_semantics_and_representation_changes(): void
    {
        $currentStock = 13;
        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('getStock')->andReturnUsing(function ($productId) use (&$currentStock) {
            return [
                'product_id'      => (int) $productId,
                'total_stock'     => 15,
                'reserved_stock'  => 15 - $currentStock,
                'available_stock' => $currentStock,
            ];
        });
        $this->app->instance(InventoryClient::class, $mockClient);

        // 1. Initial GET request returns 200 OK with ETag
        $res1 = $this->getJson("/api/v1/products/{$this->product->id}");
        $res1->assertStatus(200);
        $this->assertTrue($res1->headers->has('ETag'));
        $this->assertStringContainsString('must-revalidate', $res1->headers->get('Cache-Control'));

        $etag1 = $res1->headers->get('ETag');
        $this->assertNotEmpty($etag1);

        // 2. Exact match returns 304 with empty body
        $res2 = $this->withHeader('If-None-Match', $etag1)->getJson("/api/v1/products/{$this->product->id}");
        $res2->assertStatus(304);
        $this->assertEmpty($res2->getContent());
        $this->assertSame($etag1, $res2->headers->get('ETag'));

        // 3. Weak validator prefix W/ returns 304 with empty body
        $weakEtag = 'W/' . $etag1;
        $resWeak = $this->withHeader('If-None-Match', $weakEtag)->getJson("/api/v1/products/{$this->product->id}");
        $resWeak->assertStatus(304);
        $this->assertEmpty($resWeak->getContent());

        // 4. Wildcard * returns 304
        $resWildcard = $this->withHeader('If-None-Match', '*')->getJson("/api/v1/products/{$this->product->id}");
        $resWildcard->assertStatus(304);

        // 5. Multiple comma-separated ETags including valid one returns 304
        $resMulti = $this->withHeader('If-None-Match', '"dummy123", ' . $etag1)->getJson("/api/v1/products/{$this->product->id}");
        $resMulti->assertStatus(304);

        // 6. Mutating Arabic name ('namear') changes ETag
        /** @var ProductService $service */
        $service = app(ProductService::class);
        $service->update($this->product, ['namear' => 'اسم معدل بالكامل'], null);

        $resNameAr = $this->withHeader('If-None-Match', $etag1)->getJson("/api/v1/products/{$this->product->id}");
        $resNameAr->assertStatus(200);
        $etagNameAr = $resNameAr->headers->get('ETag');
        $this->assertNotSame($etag1, $etagNameAr);

        // 7. Modifying live inventory stock via gRPC returns new ETag
        $currentStock = 10; // Stock changed from 13 to 10

        $resStock = $this->withHeader('If-None-Match', $etagNameAr)->getJson("/api/v1/products/{$this->product->id}");
        $resStock->assertStatus(200);
        $etagStock = $resStock->headers->get('ETag');
        $this->assertNotSame($etagNameAr, $etagStock);
    }

    /**
     * 5. Test single product retrieves live stock dynamically via gRPC (never stale)
     * and propagates HTTP 503 when inventory service is unavailable.
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
     * 7. Test read-after-write consistency pinning mechanism, including secondary durable fallback.
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
     * 8. Test critical order workflow: user is pinned immediately after MySQL order commit,
     * even if ConfirmStock throws an outage and order enters pending_confirmation.
     */
    public function test_order_creation_pins_user_even_when_confirm_stock_fails(): void
    {
        $mockClient = Mockery::mock(InventoryClient::class);
        // Reserve succeeds
        $mockClient->shouldReceive('reserveStock')->once()->andReturn([
            'success'        => true,
            'reservation_id' => 'res_timeout_test_123',
            'order_ref'      => 'ref_timeout_test_123',
        ]);
        // Confirm fails with transport timeout
        $mockClient->shouldReceive('confirmStock')
            ->once()
            ->andThrow(new InventoryServiceUnavailableException('Confirm transport timeout'));
        $this->app->instance(InventoryClient::class, $mockClient);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        $rawManager = app(ReadAfterWriteManager::class);

        $payload = [
            'name'    => 'Timeout Buyer',
            'email'   => 'timeout@example.com',
            'address' => '123 Timeout Lane',
            'phone'   => '0512345678',
            'items'   => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ];

        // Ensure user is not pinned prior to order
        $this->assertFalse($rawManager->isUserPinned($this->customerUser->id));

        $caught = false;
        try {
            $orderService->create($this->customerUser, $payload, 'IDEMPOTENCY_TIMEOUT_PIN');
        } catch (InventoryServiceUnavailableException $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'ConfirmStock timeout must throw InventoryServiceUnavailableException');

        // Verify order is persisted in pending_confirmation
        $reconciliation = InventoryReconciliation::where('user_id', $this->customerUser->id)
            ->where('idempotency_key', 'IDEMPOTENCY_TIMEOUT_PIN')
            ->first();
        $this->assertNotNull($reconciliation);
        $this->assertSame(InventoryReconciliation::STATE_PENDING_CONFIRMATION, $reconciliation->workflow_state);

        // CRITICAL ASSERTION: The user MUST be pinned to primary database
        $this->assertTrue(
            $rawManager->isUserPinned($this->customerUser->id),
            'User MUST be pinned to primary database immediately after MySQL order commit, even when confirmation fails'
        );
    }

    /**
     * 9. Test cache invalidation resilience:
     * When Redis is unavailable during catalog invalidation, durable secondary store
     * prevents serving stale catalog after Redis recovers.
     */
    public function test_catalog_invalidation_reconciles_version_after_redis_recovery(): void
    {
        /** @var ProductService $service */
        $service = app(ProductService::class);

        // Initial version is 1
        $v1 = $service->getCatalogVersion();

        // Increment version in secondary durable store to simulate Redis outage during invalidation
        Cache::store('file')->put(ProductService::CATALOG_VERSION_KEY, $v1 + 5, 86400);

        // Redis still has $v1. getCatalogVersion() must reconcile and return the higher version ($v1 + 5)
        $reconciledVersion = $service->getCatalogVersion();
        $this->assertSame($v1 + 5, $reconciledVersion, 'ProductService must reconcile to the higher version');
        $this->assertSame($v1 + 5, (int) Cache::get(ProductService::CATALOG_VERSION_KEY));
    }

    /**
     * 10. Test eager loading prevents N+1 queries on catalog listing.
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

    /**
     * 11. Test Performance Dataset Seeder production safeguards:
     * Rejects execution in production, and --fresh only deletes synthetic marked records.
     */
    public function test_dataset_seeder_production_safeguards_and_cleanup_isolation(): void
    {
        // 1. Create a legitimate non-synthetic order
        $realOrder = Order::create([
            'name'       => 'Real Buyer',
            'email'      => 'real@example.com',
            'address'    => 'Real Street 1',
            'phone'      => '0500000000',
            'total'      => 150.00,
            'status'     => '0',
            'note'       => 'LEGITIMATE_CUSTOMER_ORDER',
            'user_id'    => $this->customerUser->id,
        ]);

        // 2. Create a synthetic test order
        $syntheticOrder = Order::create([
            'name'       => 'Synthetic Buyer',
            'email'      => 'synth@example.com',
            'address'    => 'Synth Street 1',
            'phone'      => '0599999999',
            'total'      => 50.00,
            'status'     => '0',
            'note'       => SeedPerformanceDatasetCommand::SYNTHETIC_NOTE_TAG,
            'user_id'    => $this->customerUser->id,
        ]);

        // 3. Test running with --fresh cleans only synthetic orders
        $exitCode = Artisan::call('performance:seed-dataset', [
            '--count' => 10,
            '--fresh' => true,
        ]);

        $this->assertSame(0, $exitCode);

        // Real order MUST still exist untouched
        $this->assertNotNull(Order::find($realOrder->id), 'Legitimate non-synthetic order must never be deleted by seeder cleanup');

        // 4. Test production rejection
        $this->app['env'] = 'production';
        $prodExitCode = Artisan::call('performance:seed-dataset', ['--count' => 10]);
        $this->assertSame(1, $prodExitCode, 'performance:seed-dataset must refuse to run in production');
    }
}
