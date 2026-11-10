<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SecondCategory;
use App\Models\User;
use App\Modules\Orders\Services\OrderService;
use App\Services\Inventory\InventoryClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CompensationTransactionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name'     => 'Compensation Tester',
            'email'    => 'comp_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => 'user',
        ]);

        $cat = Category::firstOrCreate(['id' => 1], ['name' => 'General']);
        $subCat = SecondCategory::firstOrCreate(['id' => 1], ['name' => 'Standard', 'category_id' => $cat->id]);

        $this->product = Product::firstOrCreate(
            ['id' => 1],
            [
                'name'               => 'Test Beer',
                'namear'             => 'بيرة تجريبية',
                'price'              => 10.0,
                'category_id'        => $cat->id,
                'second_category_id' => $subCat->id,
                'seller_id'          => $this->customer->id,
                'description'        => 'Test description',
                'image'              => 'test.jpg',
            ]
        );
    }

    /**
     * Scenario 1: Failure due to a missing product.
     * When product lookup fails during transaction after reservation,
     * compensation must release the reserved stock, and no order persists.
     */
    public function test_failure_due_to_missing_product_triggers_compensation(): void
    {
        $fakeReservationId = 'res-missing-' . uniqid();
        $capturedReleaseId = null;
        $capturedOrderRef = null;

        $mockInventory = Mockery::mock(InventoryClient::class);

        // ReserveStock succeeds
        $mockInventory->shouldReceive('reserveStock')
            ->once()
            ->andReturn([
                'success'        => true,
                'reservation_id' => $fakeReservationId,
                'message'        => 'Stock reserved.',
            ]);

        // ReleaseStock must be called once as compensation
        $mockInventory->shouldReceive('releaseStock')
            ->once()
            ->andReturnUsing(function ($resId, $ref) use (&$capturedReleaseId, &$capturedOrderRef) {
                $capturedReleaseId = $resId;
                $capturedOrderRef = $ref;
                return ['success' => true, 'message' => 'Stock reverted successfully.'];
            });

        $mockInventory->shouldNotReceive('confirmStock');

        $orderService = new OrderService($mockInventory);

        $invalidOrderData = [
            'name'    => 'Missing Product Buyer',
            'email'   => 'buyer@example.com',
            'address' => 'Test Address 1',
            'phone'   => '1234567890',
            'items'   => [
                [
                    'product_id' => 999999, // Non-existent product ID
                    'quantity'   => 2,
                ]
            ],
        ];

        $caught = false;
        try {
            $orderService->create($this->customer, $invalidOrderData);
        } catch (\Throwable $e) {
            $caught = true;
            $this->assertStringContainsString('999999', $e->getMessage());
        }

        $this->assertTrue($caught, 'Expected missing product exception.');
        $this->assertEquals($fakeReservationId, $capturedReleaseId, 'Compensating releaseStock called with reservation ID.');
        $this->assertNotNull($capturedOrderRef, 'Compensating releaseStock called with order_ref.');
        $this->assertEquals(0, Order::where('email', 'buyer@example.com')->count(), 'No order persisted in MySQL.');
    }

    /**
     * Scenario 2: Failure during order persistence in MySQL.
     * When saving the Order fails after reservation, compensation must release reserved stock.
     */
    public function test_failure_during_order_persistence_triggers_compensation(): void
    {
        $fakeReservationId = 'res-persist-' . uniqid();
        $capturedReleaseId = null;

        $mockInventory = Mockery::mock(InventoryClient::class);

        $mockInventory->shouldReceive('reserveStock')
            ->once()
            ->andReturn([
                'success'        => true,
                'reservation_id' => $fakeReservationId,
                'message'        => 'Stock reserved.',
            ]);

        $mockInventory->shouldReceive('releaseStock')
            ->once()
            ->andReturnUsing(function ($resId, $ref) use (&$capturedReleaseId) {
                $capturedReleaseId = $resId;
                return ['success' => true, 'message' => 'Stock reverted.'];
            });

        $mockInventory->shouldNotReceive('confirmStock');

        $orderService = new OrderService($mockInventory);

        // Force Order model to fail during insertion
        Order::creating(function () {
            throw new \RuntimeException('Simulated database write failure during order creation.');
        });

        $caught = false;
        try {
            $orderService->create($this->customer, [
                'name'    => 'Persist Fail Buyer',
                'email'   => 'persist_fail@example.com',
                'address' => 'Test Address 2',
                'phone'   => '1234567890',
                'items'   => [
                    [
                        'product_id' => $this->product->id,
                        'quantity'   => 1,
                    ]
                ],
            ]);
        } catch (\RuntimeException $e) {
            $caught = true;
        } finally {
            Order::flushEventListeners();
        }

        $this->assertTrue($caught, 'Forced DB error caught.');
        $this->assertEquals($fakeReservationId, $capturedReleaseId, 'Compensating releaseStock was executed.');
        $this->assertEquals(0, Order::where('email', 'persist_fail@example.com')->count(), 'No order persisted.');
    }

    /**
     * Scenario 3: Failure during inventory reservation.
     * When reservation fails (e.g., out of stock), no stock was reserved,
     * so compensation releaseStock MUST NOT be invoked.
     */
    public function test_failure_during_inventory_reservation_does_not_trigger_compensation(): void
    {
        $mockInventory = Mockery::mock(InventoryClient::class);

        // Reservation fails due to insufficient stock
        $mockInventory->shouldReceive('reserveStock')
            ->once()
            ->andReturn([
                'success'        => false,
                'reservation_id' => '',
                'message'        => 'Insufficient stock for product 1',
                'failed_items'   => [1],
            ]);

        // ReleaseStock must NEVER be called because nothing was reserved
        $mockInventory->shouldNotReceive('releaseStock');
        $mockInventory->shouldNotReceive('confirmStock');

        $orderService = new OrderService($mockInventory);

        $this->expectException(ValidationException::class);

        $orderService->create($this->customer, [
            'name'    => 'Out of Stock Buyer',
            'email'   => 'outofstock@example.com',
            'address' => 'Test Address 3',
            'phone'   => '1234567890',
            'items'   => [
                [
                    'product_id' => $this->product->id,
                    'quantity'   => 9999,
                ]
            ],
        ]);
    }

    /**
     * Scenario 4: Failure during reservation confirmation.
     * When Order is persisted in MySQL but gRPC confirmStock fails (e.g. expired TTL),
     * the persisted MySQL order must be transitioned to 'cancelled',
     * and compensating releaseStock must be executed.
     */
    public function test_failure_during_reservation_confirmation_cancels_order_and_triggers_compensation(): void
    {
        $fakeReservationId = 'res-confirm-fail-' . uniqid();
        $capturedReleaseId = null;

        $mockInventory = Mockery::mock(InventoryClient::class);

        $mockInventory->shouldReceive('reserveStock')
            ->once()
            ->andReturn([
                'success'        => true,
                'reservation_id' => $fakeReservationId,
                'message'        => 'Stock reserved.',
            ]);

        // Confirmation fails (e.g., reservation expired during high load)
        $mockInventory->shouldReceive('confirmStock')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Cannot confirm an expired reservation.',
            ]);

        // Compensation releaseStock must be called once
        $mockInventory->shouldReceive('releaseStock')
            ->once()
            ->andReturnUsing(function ($resId, $ref) use (&$capturedReleaseId) {
                $capturedReleaseId = $resId;
                return ['success' => true, 'message' => 'Released.'];
            });

        $orderService = new OrderService($mockInventory);

        $caught = false;
        try {
            $orderService->create($this->customer, [
                'name'    => 'Confirm Fail Buyer',
                'email'   => 'confirm_fail@example.com',
                'address' => 'Test Address 4',
                'phone'   => '1234567890',
                'items'   => [
                    [
                        'product_id' => $this->product->id,
                        'quantity'   => 1,
                    ]
                ],
            ]);
        } catch (ValidationException $e) {
            $caught = true;
            $this->assertArrayHasKey('stock', $e->errors());
        }

        $this->assertTrue($caught, 'ValidationException was thrown on confirmation failure.');
        $this->assertEquals($fakeReservationId, $capturedReleaseId, 'Compensating releaseStock was called.');

        // Verify the persisted MySQL order was transitioned to cancelled
        $persistedOrder = Order::where('email', 'confirm_fail@example.com')->first();
        $this->assertNotNull($persistedOrder, 'Order was saved in MySQL before confirmation.');
        $this->assertEquals('cancelled', $persistedOrder->status, 'Order status was explicitly updated to cancelled.');
    }

    /**
     * Proof of Live End-to-End Compensation:
     * When running in Docker environment with live gRPC:
     * 1. Check initial available stock.
     * 2. Attempt to create order with intentional DB failure.
     * 3. Verify stock in PostgreSQL is completely reverted (reserved_stock = 0).
     */
    public function test_live_microservice_compensation_reverts_stock(): void
    {
        if (!class_exists(\Grpc\ChannelCredentials::class)) {
            $this->markTestSkipped('Native PHP gRPC extension not installed in current CLI environment. Runs inside Docker.');
        }

        $testProduct = Product::firstOrCreate(
            ['id' => 5],
            [
                'name'               => 'Test Beer Live',
                'namear'             => 'بيرة حية',
                'price'              => 15.0,
                'category_id'        => 1,
                'second_category_id' => 1,
                'seller_id'          => $this->customer->id,
                'description'        => 'Live compensation test',
                'image'              => 'test.jpg',
            ]
        );

        $client = new InventoryClient();
        $productId = $testProduct->id;
        $stockBefore = $client->getStock($productId);

        $orderService = new OrderService($client);

        // Force Order model to fail during insertion inside DB::transaction
        Order::creating(function () {
            throw new \RuntimeException('Simulated database error during Order persistence.');
        });

        $caught = false;
        try {
            $orderService->create($this->customer, [
                'name'    => 'Test Fail Order',
                'email'   => 'fail@example.com',
                'address' => 'Test Street 1',
                'phone'   => '0999999999',
                'items'   => [
                    [
                        'product_id' => $productId,
                        'quantity'   => 1,
                    ]
                ]
            ]);
        } catch (\RuntimeException $e) {
            $caught = true;
        } finally {
            Order::flushEventListeners();
        }

        $this->assertTrue($caught, 'Forced DB error was caught.');

        // Verify stock in live PostgreSQL microservice is completely reverted
        $stockAfter = $client->getStock($productId);

        $this->assertEquals(0, $stockAfter['reserved_stock'], 'Reserved stock must return to 0 after compensation.');
        $this->assertEquals($stockBefore['available_stock'], $stockAfter['available_stock'], 'Available stock must be restored to original.');
    }
}
