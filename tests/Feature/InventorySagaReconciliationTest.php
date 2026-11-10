<?php

namespace Tests\Feature;

use App\Exceptions\InventoryServiceUnavailableException;
use App\Models\Category;
use App\Models\InventoryReconciliation;
use App\Models\Order;
use App\Models\Product;
use App\Models\SecondCategory;
use App\Models\User;
use App\Services\Inventory\InventoryClient;
use App\Services\Inventory\InventoryReconciliationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class InventorySagaReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $customer;
    protected string $customerToken;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name'     => 'Saga Tester',
            'email'    => 'saga_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => 'user',
        ]);

        $this->customerToken = auth('api')->tokenById($this->customer->id);
        auth()->forgetGuards();

        $cat = Category::firstOrCreate(['id' => 1], ['name' => 'General']);
        $subCat = SecondCategory::firstOrCreate(['id' => 1], ['name' => 'Standard', 'category_id' => $cat->id]);

        $this->product = Product::firstOrCreate(
            ['id' => 1],
            [
                'name'               => 'Saga Test Product',
                'namear'             => 'منتج تجريبي',
                'price'              => 20.0,
                'category_id'        => $cat->id,
                'second_category_id' => $subCat->id,
                'seller_id'          => $this->customer->id,
                'description'        => 'Test Description',
                'imgpath'            => 'test.jpg',
            ]
        );
    }

    /**
     * Scenario 1: Successful reservation and confirmation (happy path).
     * Outbox record transitions to 'confirmed', order remains active ('0').
     */
    public function test_scenario_1_happy_path_reservation_and_confirmation(): void
    {
        $resId = 'res-happy-' . uniqid();
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
                'name'    => 'Happy Buyer',
                'email'   => 'happy@example.com',
                'address' => 'Happy Street 1',
                'phone'   => '0555555555',
                'items'   => [
                    ['product_id' => $this->product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertStatus(201);
        $order = Order::where('email', 'happy@example.com')->first();
        $this->assertNotNull($order);
        $this->assertSame('0', $order->status);

        $rec = InventoryReconciliation::where('order_id', $order->id)->first();
        $this->assertNotNull($rec);
        $this->assertSame(InventoryReconciliation::STATE_CONFIRMED, $rec->workflow_state);
        $this->assertSame($resId, $rec->reservation_id);
        $this->assertNull($rec->last_error);
    }

    /**
     * Scenario 2: gRPC transport timeout during ConfirmStock.
     * Order remains active ('0'), releaseStock is NOT called, outbox is 'pending_confirmation', returns 503.
     */
    public function test_scenario_2_confirmation_timeout_preserves_order_and_sets_pending_confirmation(): void
    {
        $resId = 'res-timeout-' . uniqid();
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
            ->andThrow(new InventoryServiceUnavailableException('Deadline exceeded during confirm'));

        // ReleaseStock must NEVER be called because confirmation status is ambiguous!
        $mockClient->shouldNotReceive('releaseStock');

        $this->app->instance(InventoryClient::class, $mockClient);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->postJson('/api/v1/orders', [
                'name'    => 'Timeout Buyer',
                'email'   => 'timeout@example.com',
                'address' => 'Timeout Street 2',
                'phone'   => '0555555555',
                'items'   => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(503);
        $order = Order::where('email', 'timeout@example.com')->first();
        $this->assertNotNull($order, 'Order must be preserved in MySQL.');
        $this->assertSame('0', $order->status, 'Order must remain active (0) rather than cancelled.');

        $rec = InventoryReconciliation::where('order_id', $order->id)->first();
        $this->assertNotNull($rec);
        $this->assertSame(InventoryReconciliation::STATE_PENDING_CONFIRMATION, $rec->workflow_state);
        $this->assertSame(1, $rec->retry_count);
        $this->assertNotNull($rec->next_retry_at);
        $this->assertStringContainsString('Deadline exceeded', $rec->last_error);
    }

    /**
     * Scenario 3: Running inventory:reconcile resolves pending_confirmation to confirmed without releasing inventory.
     */
    public function test_scenario_3_reconciliation_worker_resolves_pending_confirmation(): void
    {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'name'    => 'Reconcile Buyer',
            'email'   => 'recon@example.com',
            'address' => 'Recon Street 3',
            'phone'   => '0555555555',
            'note'    => 'Reconcile note',
            'total'   => 35.0,
            'status'  => '0',
        ]);

        $resId = 'res-recon-' . uniqid();
        $orderRef = 'ORD-RECON-' . uniqid();

        $rec = InventoryReconciliation::create([
            'order_id'        => $order->id,
            'order_ref'       => $orderRef,
            'reservation_id'  => $resId,
            'workflow_state'  => InventoryReconciliation::STATE_PENDING_CONFIRMATION,
            'retry_count'     => 1,
            'max_retries'     => 5,
            'next_retry_at'   => now()->subMinute(), // eligible for retry
            'last_error'      => 'Initial gRPC timeout',
            'items_payload'   => [['product_id' => $this->product->id, 'quantity' => 1]],
        ]);

        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('confirmStock')
            ->once()
            ->with($resId, $orderRef)
            ->andReturn([
                'success' => true,
                'message' => 'Already confirmed (idempotent).',
            ]);

        $mockClient->shouldNotReceive('releaseStock');

        $this->app->instance(InventoryClient::class, $mockClient);

        // Run Artisan reconciliation
        $exitCode = Artisan::call('inventory:reconcile', ['--limit' => 10]);
        $this->assertSame(0, $exitCode);

        $rec->refresh();
        $this->assertSame(InventoryReconciliation::STATE_CONFIRMED, $rec->workflow_state);
        $this->assertNull($rec->next_retry_at);
        $this->assertNull($rec->last_error);

        $order->refresh();
        $this->assertSame('0', $order->status);
    }

    /**
     * Scenario 4: Definite business failure on ConfirmStock (e.g. expired reservation).
     * Order is cancelled, compensation release is executed, outbox becomes 'released'.
     */
    public function test_scenario_4_definite_business_failure_cancels_order_and_releases_stock(): void
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

        // Definite business rejection: reservation expired
        $mockClient->shouldReceive('confirmStock')
            ->once()
            ->with($resId, Mockery::type('string'))
            ->andReturn([
                'success' => false,
                'message' => 'Cannot confirm an expired reservation.',
            ]);

        // Compensation releaseStock must be executed immediately
        $mockClient->shouldReceive('releaseStock')
            ->once()
            ->with($resId, Mockery::type('string'))
            ->andReturn([
                'success' => true,
                'message' => 'Reserved stock released.',
            ]);

        $this->app->instance(InventoryClient::class, $mockClient);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->postJson('/api/v1/orders', [
                'name'    => 'Expired Buyer',
                'email'   => 'expired@example.com',
                'address' => 'Expired Ave 4',
                'phone'   => '0555555555',
                'items'   => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);

        $order = Order::where('email', 'expired@example.com')->first();
        $this->assertNotNull($order);
        $this->assertSame('cancelled', $order->status);

        $rec = InventoryReconciliation::where('order_id', $order->id)->first();
        $this->assertNotNull($rec);
        $this->assertSame(InventoryReconciliation::STATE_RELEASED, $rec->workflow_state);
        $this->assertNull($rec->next_retry_at);
    }

    /**
     * Scenario 5: Failed compensation (ReleaseStock network error) leaves record in pending_release.
     */
    public function test_scenario_5_failed_compensation_persists_in_pending_release(): void
    {
        $resId = 'res-relfail-' . uniqid();
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
                'success' => false,
                'message' => 'Cannot confirm an expired reservation.',
            ]);

        // ReleaseStock fails (e.g. timeout / network partition)
        $mockClient->shouldReceive('releaseStock')
            ->once()
            ->with($resId, Mockery::type('string'))
            ->andReturn([
                'success' => false,
                'message' => 'gRPC connection timeout during releaseStock.',
            ]);

        $this->app->instance(InventoryClient::class, $mockClient);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->postJson('/api/v1/orders', [
                'name'    => 'Release Fail Buyer',
                'email'   => 'relfail@example.com',
                'address' => 'Fail Street 5',
                'phone'   => '0555555555',
                'items'   => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);

        $order = Order::where('email', 'relfail@example.com')->first();
        $this->assertNotNull($order);
        $this->assertSame('cancelled', $order->status);

        $rec = InventoryReconciliation::where('order_id', $order->id)->first();
        $this->assertNotNull($rec);
        $this->assertSame(InventoryReconciliation::STATE_PENDING_RELEASE, $rec->workflow_state);
        $this->assertGreaterThanOrEqual(1, $rec->retry_count);
        $this->assertNotNull($rec->next_retry_at);
        $this->assertStringContainsString('Compensation release failed', $rec->last_error);
    }

    /**
     * Scenario 6: Reconciler retries pending_release until successful or max retries reached.
     */
    public function test_scenario_6_reconciliation_retries_pending_release_and_enforces_max_retries(): void
    {
        $resId = 'res-retry-rel-' . uniqid();
        $orderRef = 'ORD-REL-' . uniqid();

        // 6A: Recovery success
        $rec1 = InventoryReconciliation::create([
            'order_ref'       => $orderRef . '-A',
            'reservation_id'  => $resId . '-A',
            'workflow_state'  => InventoryReconciliation::STATE_PENDING_RELEASE,
            'retry_count'     => 1,
            'max_retries'     => 5,
            'next_retry_at'   => now()->subMinute(),
            'last_error'      => 'Network timeout on first attempt',
            'items_payload'   => [],
        ]);

        $mockClient = Mockery::mock(InventoryClient::class);
        $mockClient->shouldReceive('releaseStock')
            ->once()
            ->with($resId . '-A', $orderRef . '-A')
            ->andReturn([
                'success' => true,
                'message' => 'Already released (idempotent).',
            ]);

        $this->app->instance(InventoryClient::class, $mockClient);

        Artisan::call('inventory:reconcile', ['--limit' => 10]);

        $rec1->refresh();
        $this->assertSame(InventoryReconciliation::STATE_RELEASED, $rec1->workflow_state);
        $this->assertNull($rec1->next_retry_at);

        // 6B: Max retries exceeded marks FAILED
        $rec2 = InventoryReconciliation::create([
            'order_ref'       => $orderRef . '-B',
            'reservation_id'  => $resId . '-B',
            'workflow_state'  => InventoryReconciliation::STATE_PENDING_RELEASE,
            'retry_count'     => 4,
            'max_retries'     => 5,
            'next_retry_at'   => now()->subMinute(),
            'last_error'      => 'Persistent connection error',
            'items_payload'   => [],
        ]);

        $mockClient2 = Mockery::mock(InventoryClient::class);
        $mockClient2->shouldReceive('releaseStock')
            ->once()
            ->with($resId . '-B', $orderRef . '-B')
            ->andReturn([
                'success' => false,
                'message' => 'Persistent failure',
            ]);

        $this->app->instance(InventoryClient::class, $mockClient2);

        Artisan::call('inventory:reconcile', ['--limit' => 10]);

        $rec2->refresh();
        $this->assertSame(InventoryReconciliation::STATE_FAILED, $rec2->workflow_state);
        $this->assertSame(5, $rec2->retry_count);
    }

    /**
     * Scenario 7: Idempotent client retry with identical Idempotency-Key returns existing order.
     */
    public function test_scenario_7_idempotent_retry_returns_existing_order_without_duplicate(): void
    {
        $idempotencyKey = 'IDEM-KEY-' . uniqid();
        $resId = 'res-idem-' . uniqid();

        $mockClient = Mockery::mock(InventoryClient::class);

        // gRPC reserve & confirm should only be called once!
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

        $this->app->instance(InventoryClient::class, $mockClient);

        $payload = [
            'name'    => 'Idempotent Buyer',
            'email'   => 'idem@example.com',
            'address' => 'Idem Street 7',
            'phone'   => '0555555555',
            'items'   => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ];

        // 1st call: creates order
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', $payload);

        $res1->assertStatus(201);
        $orderId1 = $res1->json('data.id');

        // 2nd call: replayed with same key and payload
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', $payload);

        $res2->assertStatus(201);
        $orderId2 = $res2->json('data.id');

        // Both calls return the exact same order
        $this->assertSame($orderId1, $orderId2);

        // Exactly 1 order exists in database for this email
        $this->assertSame(1, Order::where('email', 'idem@example.com')->count());
    }

    /**
     * Scenario 8: Client retry with identical Idempotency-Key but mismatched payload returns HTTP 422.
     */
    public function test_scenario_8_idempotent_key_with_mismatched_payload_returns_422(): void
    {
        $idempotencyKey = 'IDEM-CONFLICT-' . uniqid();
        $resId = 'res-conflict-' . uniqid();

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

        $this->app->instance(InventoryClient::class, $mockClient);

        // 1st call: payload with quantity 1
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', [
                'name'    => 'Conflict Buyer',
                'email'   => 'conflict@example.com',
                'address' => 'Conflict Street 8',
                'phone'   => '0555555555',
                'items'   => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ]);

        $res1->assertStatus(201);

        // 2nd call: same Idempotency-Key, different payload (quantity 5)
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->customerToken)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', [
                'name'    => 'Conflict Buyer',
                'email'   => 'conflict@example.com',
                'address' => 'Conflict Street 8',
                'phone'   => '0555555555',
                'items'   => [
                    ['product_id' => $this->product->id, 'quantity' => 5],
                ],
            ]);

        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['idempotency_key']);
    }

    /**
     * Real integration test: Live native gRPC microservice and PostgreSQL inventory.
     */
    public function test_live_grpc_integration_full_saga_and_reconciliation_record(): void
    {
        $realClient = app(InventoryClient::class);

        // Pick a test product that exists in PostgreSQL (product 5 has 20 units)
        $product = Product::find(5);
        if (!$product) {
            $product = new Product([
                'name'               => 'Live gRPC Beer',
                'namear'             => 'بيرة حية',
                'price'              => 15.0,
                'category_id'        => 1,
                'second_category_id' => 1,
                'seller_id'          => $this->customer->id,
                'description'        => 'Live gRPC Test',
                'imgpath'            => 'test.jpg',
            ]);
            $product->id = 5;
            $product->save();
        }

        // Query initial live stock from microservice
        $initialStock = $realClient->getStock(5);
        $this->assertGreaterThanOrEqual(1, $initialStock['available_stock']);

        // Create order through real gRPC client
        $orderService = app(\App\Modules\Orders\Services\OrderService::class);
        $idempotencyKey = 'LIVE-TEST-' . uniqid();

        $order = $orderService->create($this->customer, [
            'name'    => 'Live gRPC Buyer',
            'email'   => 'live_' . uniqid() . '@example.com',
            'address' => 'Live gRPC Way 9',
            'phone'   => '0555555555',
            'items'   => [
                ['product_id' => 5, 'quantity' => 1],
            ],
        ], $idempotencyKey);

        $this->assertNotNull($order);
        $this->assertSame('0', $order->status);

        // Verify outbox record created and confirmed
        $rec = InventoryReconciliation::where('order_id', $order->id)->first();
        $this->assertNotNull($rec);
        $this->assertSame(InventoryReconciliation::STATE_CONFIRMED, $rec->workflow_state);
        $this->assertSame($idempotencyKey, $rec->idempotency_key);
        $this->assertNotNull($rec->reservation_id);
    }
}
