<?php

namespace Tests\Feature;

use App\Exceptions\InventoryServiceUnavailableException;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SecondCategory;
use App\Models\User;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Products\Services\ProductService;
use App\Services\Inventory\InventoryClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class InventoryReliabilityAndLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $customer;
    protected string $customerToken;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::first() ?? Category::create([
            'name'    => 'Electronics',
            'namear'  => 'إلكترونيات',
            'imgpath' => '/images/categories/1.jpg',
        ]);

        $secondCategory = SecondCategory::first() ?? SecondCategory::create([
            'name'        => 'Smartphones',
            'namear'      => 'هواتف',
            'category_id' => $category->id,
        ]);

        $seller = User::where('role', 'seller')->first() ?? User::create([
            'name'     => 'Test Seller',
            'email'    => 'seller_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => User::ROLE_SELLER,
        ]);

        $this->product = Product::create([
            'name'               => 'Test Reliability Item',
            'namear'             => 'عنصر اختبار الموثوقية',
            'price'              => 100,
            'category_id'        => $category->id,
            'second_category_id' => $secondCategory->id,
            'seller_id'          => $seller->id,
        ]);

        $this->customer = User::create([
            'name'     => 'Reliability Customer',
            'email'    => 'rel_cust_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => User::ROLE_USER,
        ]);
        $this->customerToken = auth('api')->tokenById($this->customer->id);

        auth()->forgetGuards();
    }

    /**
     * Requirement 1: GET /api/v1/products/{id} returns clean 503 when inventory service is unavailable.
     */
    public function test_product_detail_returns_503_when_inventory_service_unavailable(): void
    {
        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('getStock')
            ->with($this->product->id)
            ->andThrow(new InventoryServiceUnavailableException('Inventory service temporarily unavailable'));

        $this->app->instance(InventoryClient::class, $mockClient);

        $response = $this->getJson("/api/v1/products/{$this->product->id}");

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
            'message' => 'Inventory service temporarily unavailable',
            'data'    => null,
            'errors'  => null,
        ]);
    }

    /**
     * Requirement 1: GET /api/v1/products/{id} returns 200 with live stock data when inventory is healthy.
     */
    public function test_product_detail_returns_live_stock_when_inventory_is_healthy(): void
    {
        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('getStock')
            ->with($this->product->id)
            ->andReturn([
                'product_id'      => $this->product->id,
                'total_stock'     => 25,
                'reserved_stock'  => 5,
                'available_stock' => 20,
            ]);

        $this->app->instance(InventoryClient::class, $mockClient);

        $response = $this->getJson("/api/v1/products/{$this->product->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $this->product->id);
        $response->assertJsonPath('data.available_stock', 20);
        $response->assertJsonPath('data.stock.total_stock', 25);
    }

    /**
     * Requirement 3: Order creation rejects insufficient inventory with clean 422.
     */
    public function test_order_creation_rejects_insufficient_inventory_with_422(): void
    {
        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('reserveStock')
            ->once()
            ->andReturn([
                'success'        => false,
                'message'        => 'Insufficient stock for one or more items.',
                'failed_items'   => [$this->product->id],
            ]);

        $mockClient->shouldNotReceive('confirmStock');
        $mockClient->shouldNotReceive('releaseStock');

        $this->app->instance(InventoryClient::class, $mockClient);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->postJson('/api/v1/orders', [
                'name'    => 'Buyer',
                'email'   => 'buyer@test.com',
                'address' => 'Test Street',
                'phone'   => '0555555555',
                'items'   => [
                    [
                        'product_id' => $this->product->id,
                        'quantity'   => 50,
                    ]
                ]
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['stock']);
        $this->assertEquals(0, Order::where('email', 'buyer@test.com')->count());
    }

    /**
     * Requirement 3 & 5: Inventory service unavailable on reserve returns clean 503.
     */
    public function test_order_creation_returns_503_when_inventory_service_unavailable_on_reserve(): void
    {
        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('reserveStock')
            ->once()
            ->andThrow(new InventoryServiceUnavailableException('Inventory service temporarily unavailable'));

        $this->app->instance(InventoryClient::class, $mockClient);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->postJson('/api/v1/orders', [
                'name'    => 'Outage Buyer',
                'email'   => 'outage@test.com',
                'address' => 'Outage Ave',
                'phone'   => '0555555555',
                'items'   => [
                    [
                        'product_id' => $this->product->id,
                        'quantity'   => 1,
                    ]
                ]
            ]);

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
            'message' => 'Inventory service temporarily unavailable',
        ]);
        $this->assertEquals(0, Order::where('email', 'outage@test.com')->count());
    }

    /**
     * Requirement 2 & 3: Successful order creation reserves and confirms stock.
     */
    public function test_successful_order_creation_reserves_and_confirms_stock(): void
    {
        $resId = 'res-valid-' . uniqid();

        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('reserveStock')
            ->once()
            ->andReturn([
                'success'        => true,
                'reservation_id' => $resId,
                'message'        => 'Stock reserved.',
            ]);

        $mockClient->shouldReceive('confirmStock')
            ->once()
            ->with($resId, Mockery::type('string'))
            ->andReturn([
                'success' => true,
                'message' => 'Reservation confirmed.',
            ]);

        $mockClient->shouldNotReceive('releaseStock');

        $this->app->instance(InventoryClient::class, $mockClient);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->postJson('/api/v1/orders', [
                'name'    => 'Success Buyer',
                'email'   => 'success@test.com',
                'address' => 'Success Road 1',
                'phone'   => '0555555555',
                'items'   => [
                    [
                        'product_id' => $this->product->id,
                        'quantity'   => 2,
                    ]
                ]
            ]);

        $response->assertStatus(201);
        $order = Order::where('email', 'success@test.com')->first();
        $this->assertNotNull($order);
        $this->assertSame('0', $order->status); // 0 = pending
    }

    /**
     * Requirement 2 & 3: Failed confirmation does NOT return 201 success; cancels order in DB and triggers release.
     */
    public function test_failed_confirmation_cancels_persisted_order_and_triggers_release_compensation(): void
    {
        $resId = 'res-expired-' . uniqid();

        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('reserveStock')
            ->once()
            ->andReturn([
                'success'        => true,
                'reservation_id' => $resId,
                'message'        => 'Stock reserved.',
            ]);

        // Confirmation fails (e.g. reservation expired while DB was saving)
        $mockClient->shouldReceive('confirmStock')
            ->once()
            ->with($resId, Mockery::type('string'))
            ->andReturn([
                'success' => false,
                'message' => 'Cannot confirm an expired reservation.',
            ]);

        // Compensating release MUST be triggered
        $mockClient->shouldReceive('releaseStock')
            ->once()
            ->with($resId, Mockery::type('string'))
            ->andReturn([
                'success' => true,
                'message' => 'Released.',
            ]);

        $this->app->instance(InventoryClient::class, $mockClient);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->postJson('/api/v1/orders', [
                'name'    => 'Expired Buyer',
                'email'   => 'expired_buyer@test.com',
                'address' => 'Expired Lane',
                'phone'   => '0555555555',
                'items'   => [
                    [
                        'product_id' => $this->product->id,
                        'quantity'   => 1,
                    ]
                ]
            ]);

        // Must NOT return 201!
        $this->assertNotEquals(201, $response->getStatusCode());
        $response->assertStatus(422);

        // Verify order in database is marked 'cancelled'
        $order = Order::where('email', 'expired_buyer@test.com')->first();
        $this->assertNotNull($order);
        $this->assertSame('cancelled', $order->status);
    }

    /**
     * Requirement 3 & 5: Confirmation network failure cancels persisted order, releases, and returns 503.
     */
    public function test_confirmation_service_outage_cancels_persisted_order_and_returns_503(): void
    {
        $resId = 'res-outage-' . uniqid();

        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('reserveStock')
            ->once()
            ->andReturn([
                'success'        => true,
                'reservation_id' => $resId,
                'message'        => 'Stock reserved.',
            ]);

        // Confirmation throws 503 service unavailable
        $mockClient->shouldReceive('confirmStock')
            ->once()
            ->with($resId, Mockery::type('string'))
            ->andThrow(new InventoryServiceUnavailableException('Inventory service temporarily unavailable'));

        // Release compensation called
        $mockClient->shouldReceive('releaseStock')
            ->once()
            ->with($resId, Mockery::type('string'))
            ->andReturn(['success' => true]);

        $this->app->instance(InventoryClient::class, $mockClient);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->postJson('/api/v1/orders', [
                'name'    => 'Outage Confirm Buyer',
                'email'   => 'outage_confirm@test.com',
                'address' => 'Crash Road',
                'phone'   => '0555555555',
                'items'   => [
                    [
                        'product_id' => $this->product->id,
                        'quantity'   => 1,
                    ]
                ]
            ]);

        $response->assertStatus(503);
        $order = Order::where('email', 'outage_confirm@test.com')->first();
        $this->assertNotNull($order);
        $this->assertSame('cancelled', $order->status);
    }
}
