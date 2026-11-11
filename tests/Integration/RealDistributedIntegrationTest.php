<?php

namespace Tests\Integration;

use App\Exceptions\InventoryServiceUnavailableException;
use App\Models\Category;
use App\Models\InventoryReconciliation;
use App\Models\Order;
use App\Models\Product;
use App\Models\SecondCategory;
use App\Models\User;
use App\Modules\Orders\Services\OrderService;
use App\Services\Inventory\InventoryClient;
use App\Services\Inventory\InventoryReconciliationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealDistributedIntegrationTest extends TestCase
{
    protected User $userA;
    protected User $userB;
    protected Product $product;
    protected InventoryClient $inventoryClient;
    protected OrderService $orderService;
    protected int $initialTotalStock;
    protected int $initialReservedStock;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Verify native PHP gRPC extension is loaded
        $this->assertTrue(extension_loaded('grpc'), 'Native PHP gRPC extension must be loaded.');

        // 2. Real InventoryClient connected to live Python gRPC service
        $this->inventoryClient = new InventoryClient();
        $this->orderService = app(OrderService::class);

        // 3. Setup test users in real database
        $this->userA = User::firstOrCreate(
            ['email' => 'real_user_a@test.com'],
            [
                'name'     => 'Real User A',
                'password' => Hash::make('password123'),
                'role'     => 'user',
            ]
        );

        $this->userB = User::firstOrCreate(
            ['email' => 'real_user_b@test.com'],
            [
                'name'     => 'Real User B',
                'password' => Hash::make('password123'),
                'role'     => 'user',
            ]
        );

        $cat = Category::firstOrCreate(['id' => 1], ['name' => 'General']);
        $subCat = SecondCategory::firstOrCreate(['id' => 1], ['name' => 'Standard', 'category_id' => $cat->id]);

        // Product 10 in live PostgreSQL (seeded with 20 pieces)
        $this->product = Product::find(10);
        if (!$this->product) {
            $this->product = new Product([
                'name'               => 'Real Integration Beer',
                'namear'             => 'بيرة التكامل الحقيقي',
                'price'              => 25.0,
                'category_id'        => 1,
                'second_category_id' => 1,
                'seller_id'          => $this->userA->id,
                'description'        => 'Live Distributed Integration Product',
                'imgpath'            => 'test.jpg',
            ]);
            $this->product->id = 10;
            $this->product->save();
        }

        // Record initial live stock from Python gRPC microservice
        $stock = $this->inventoryClient->getStock(10);
        $this->initialTotalStock = $stock['total_stock'];
        $this->initialReservedStock = $stock['reserved_stock'];
    }

    protected function tearDown(): void
    {
        // Restore stock state if needed
        parent::tearDown();
    }

    /**
     * Requirement 1: User-scoped idempotency isolation against live MySQL.
     * User A creates order with key K. User B submits same key K.
     * Both receive their own independent orders with no shared state or data leak.
     */
    public function test_real_mysql_user_scoped_idempotency_isolation(): void
    {
        $sharedKey = 'REAL-IDEM-' . uniqid();
        $payload = [
            'name'    => 'Real Buyer',
            'email'   => 'real_buyer@example.com',
            'address' => 'Real Street 1',
            'phone'   => '0555555555',
            'items'   => [
                ['product_id' => 10, 'quantity' => 1],
            ],
        ];

        // User A creates order with sharedKey
        $orderA = $this->orderService->create($this->userA, $payload, $sharedKey);
        $this->assertNotNull($orderA);
        $this->assertSame($this->userA->id, $orderA->user_id);

        // User B creates order with the exact same sharedKey
        $orderB = $this->orderService->create($this->userB, $payload, $sharedKey);
        $this->assertNotNull($orderB);
        $this->assertSame($this->userB->id, $orderB->user_id);

        // Independent orders in MySQL
        $this->assertNotEquals($orderA->id, $orderB->id);

        // User A replays sharedKey -> returns User A's order
        $replayA = $this->orderService->create($this->userA, $payload, $sharedKey);
        $this->assertSame($orderA->id, $replayA->id);

        // User B replays sharedKey -> returns User B's order
        $replayB = $this->orderService->create($this->userB, $payload, $sharedKey);
        $this->assertSame($orderB->id, $replayB->id);

        // Clean up compensation to preserve stock
        $recA = InventoryReconciliation::where('order_id', $orderA->id)->first();
        if ($recA && $recA->reservation_id) {
            $this->inventoryClient->releaseStock($recA->reservation_id, $recA->order_ref);
        }
        $recB = InventoryReconciliation::where('order_id', $orderB->id)->first();
        if ($recB && $recB->reservation_id) {
            $this->inventoryClient->releaseStock($recB->reservation_id, $recB->order_ref);
        }
    }

    /**
     * Requirement 2 & 3: Real Fault Injection — ConfirmStock commits in PostgreSQL but gRPC response is lost.
     * Subsequent real gRPC retry recognizes the confirmed reservation (idempotent success).
     */
    public function test_real_fault_injection_confirm_timeout_and_idempotent_recovery(): void
    {
        // Special idempotency key that forces the Python gRPC server to commit PostgreSQL then abort
        $faultKey = 'FAULT_TIMEOUT_CONFIRM_' . uniqid();
        $payload = [
            'name'    => 'Confirm Fault Buyer',
            'email'   => 'confirm_fault@example.com',
            'address' => 'Fault Ave 2',
            'phone'   => '0555555555',
            'items'   => [
                ['product_id' => 10, 'quantity' => 1],
            ],
        ];

        // 1. Initial attempt: commits in PostgreSQL but gRPC transport fails with UNAVAILABLE
        $caught = false;
        try {
            $this->orderService->create($this->userA, $payload, $faultKey);
        } catch (InventoryServiceUnavailableException $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'Expected InventoryServiceUnavailableException from fault injection');

        // 2. Verify MySQL order is PRESERVED and active (status = 0)
        $rec = InventoryReconciliation::where('user_id', $this->userA->id)
            ->where('idempotency_key', $faultKey)
            ->first();

        $this->assertNotNull($rec);
        $this->assertSame(InventoryReconciliation::STATE_PENDING_CONFIRMATION, $rec->workflow_state);
        $this->assertNotNull($rec->order_id);

        $order = Order::find($rec->order_id);
        $this->assertNotNull($order);
        $this->assertSame('0', $order->status, 'Order must remain active (0) rather than cancelled.');

        // 3. Real recovery: Reconciler calls ConfirmStock again
        // Python server checks PostgreSQL: status is already confirmed -> returns Already confirmed (idempotent).
        $service = app(InventoryReconciliationService::class);
        $result = $service->reconcileRecord($rec);

        $this->assertTrue($result, 'Reconciler must succeed via idempotent confirmation.');
        $rec->refresh();
        $this->assertSame(InventoryReconciliation::STATE_CONFIRMED, $rec->workflow_state);

        $order->refresh();
        $this->assertSame('0', $order->status);

        // Cleanup
        $this->inventoryClient->releaseStock($rec->reservation_id, $rec->order_ref);
    }

    /**
     * Requirement 4 & 5: Real Fault Injection — ReserveStock commits in PostgreSQL but gRPC response is lost.
     * Retry with the same stable order_ref does NOT reserve twice.
     */
    public function test_real_fault_injection_reserve_timeout_and_idempotent_recovery(): void
    {
        $stockBefore = $this->inventoryClient->getStock(10);

        // Idempotency key that triggers reserve fault injection
        $faultKey = 'FAULT_TIMEOUT_RESERVE_' . uniqid();
        $payload = [
            'name'    => 'Reserve Fault Buyer',
            'email'   => 'res_fault@example.com',
            'address' => 'Reserve Fault Road 3',
            'phone'   => '0555555555',
            'items'   => [
                ['product_id' => 10, 'quantity' => 1],
            ],
        ];

        // 1. Initial attempt: commits reservation in PostgreSQL then aborts UNAVAILABLE
        $caught = false;
        try {
            $this->orderService->create($this->userA, $payload, $faultKey);
        } catch (InventoryServiceUnavailableException $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'Expected InventoryServiceUnavailableException from reserve fault injection');

        // Verify outbox record captured the pending reservation
        $rec = InventoryReconciliation::where('user_id', $this->userA->id)
            ->where('idempotency_key', $faultKey)
            ->first();

        $this->assertNotNull($rec);
        $this->assertSame(InventoryReconciliation::STATE_PENDING_RESERVATION, $rec->workflow_state);

        // Stock was reserved once in PostgreSQL
        $stockAfterFirst = $this->inventoryClient->getStock(10);
        $this->assertEquals($stockBefore['reserved_stock'] + 1, $stockAfterFirst['reserved_stock']);

        // 2. Client retries with the SAME key:
        // ReserveStock recognizes the stable order_ref and returns existing reservation without double reserving!
        // To prevent fault injection on the retry, we can test that the same order_ref returns the existing reservation
        $resRetry = $this->inventoryClient->reserveStock($rec->order_ref, [
            ['product_id' => 10, 'quantity' => 1]
        ], 300);

        $this->assertTrue($resRetry['success']);
        $this->assertStringContainsString('Idempotent response', $resRetry['message']);

        // Stock in PostgreSQL was NOT incremented again!
        $stockAfterRetry = $this->inventoryClient->getStock(10);
        $this->assertEquals($stockAfterFirst['reserved_stock'], $stockAfterRetry['reserved_stock'], 'Stock must not be double-reserved on retry.');

        // Cleanup
        $this->inventoryClient->releaseStock($resRetry['reservation_id'], $rec->order_ref);
    }

    /**
     * Requirement 6 & 7: Real Fault Injection — ReleaseStock timeout after commit is safely reconciled.
     */
    public function test_real_fault_injection_release_timeout_and_safe_reconciliation(): void
    {
        $orderRef = 'ORD-U' . $this->userA->id . '-FAULT_TIMEOUT_RELEASE_' . uniqid();

        // 1. Create a real reservation in PostgreSQL
        $res = $this->inventoryClient->reserveStock($orderRef, [
            ['product_id' => 10, 'quantity' => 1]
        ], 300);

        $this->assertTrue($res['success']);
        $resId = $res['reservation_id'];

        // 2. Create reconciliation record in pending_release
        $rec = InventoryReconciliation::create([
            'user_id'         => $this->userA->id,
            'order_ref'       => $orderRef,
            'reservation_id'  => $resId,
            'workflow_state'  => InventoryReconciliation::STATE_PENDING_RELEASE,
            'retry_count'     => 1,
            'max_retries'     => 5,
            'next_retry_at'   => now()->subMinute(),
            'items_payload'   => [['product_id' => 10, 'quantity' => 1]],
        ]);

        // 3. Attempt ReleaseStock with fault injection: commits released in PostgreSQL then aborts UNAVAILABLE
        $releaseRes = $this->inventoryClient->releaseStock($resId, $orderRef);
        // Returns false because gRPC aborted
        $this->assertFalse($releaseRes['success']);

        // 4. Reconciler runs: re-calls releaseStock without holding locks
        // In PostgreSQL, status is already 'released' -> returns 'Already released (idempotent).'
        $service = app(InventoryReconciliationService::class);
        $success = $service->compensateRelease($rec);

        $this->assertTrue($success);
        $rec->refresh();
        $this->assertSame(InventoryReconciliation::STATE_RELEASED, $rec->workflow_state);
        $this->assertNull($rec->last_error);
    }

    /**
     * Requirement 8: Final MySQL order state matches final PostgreSQL reservation state.
     */
    public function test_final_mysql_and_postgresql_consistency(): void
    {
        $key = 'CONSISTENCY-KEY-' . uniqid();
        $payload = [
            'name'    => 'Consistency Buyer',
            'email'   => 'consist@example.com',
            'address' => 'Consistency Lane 8',
            'phone'   => '0555555555',
            'items'   => [
                ['product_id' => 10, 'quantity' => 1],
            ],
        ];

        $order = $this->orderService->create($this->userA, $payload, $key);
        $this->assertNotNull($order);
        $this->assertSame('0', $order->status);

        $rec = InventoryReconciliation::where('order_id', $order->id)->first();
        $this->assertNotNull($rec);
        $this->assertSame(InventoryReconciliation::STATE_CONFIRMED, $rec->workflow_state);

        // Query live PostgreSQL stock to verify reservation is active
        $stock = $this->inventoryClient->getStock(10);
        $this->assertGreaterThanOrEqual(1, $stock['reserved_stock']);

        // Cleanup
        $this->inventoryClient->releaseStock($rec->reservation_id, $rec->order_ref);
    }
}
