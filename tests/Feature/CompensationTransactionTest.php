<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Modules\Orders\Services\OrderService;
use App\Services\Inventory\InventoryClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class CompensationTransactionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Proof of Compensation Transaction:
     * When saving an order fails after a successful reservation,
     * ReleaseStock is called with the exact reservation_id, and stock is reverted.
     */
    public function test_compensation_releases_stock_when_order_creation_fails(): void
    {
        // 1. Create a customer
        $user = User::create([
            'name'     => 'Compensation Tester',
            'email'    => 'comp_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => 'user',
        ]);

        $fakeReservationId = 'res-test-' . uniqid();
        $capturedReleaseId = null;
        $capturedOrderRef = null;

        // 2. Mock InventoryClient to track the compensation call
        $mockInventory = Mockery::mock(InventoryClient::class);

        // ReserveStock succeeds
        $mockInventory->shouldReceive('reserveStock')
            ->once()
            ->andReturn([
                'success'        => true,
                'reservation_id' => $fakeReservationId,
                'message'        => 'Stock reserved.',
            ]);

        // ReleaseStock MUST be called as a compensating action when order saving fails
        $mockInventory->shouldReceive('releaseStock')
            ->once()
            ->andReturnUsing(function ($resId, $ref) use (&$capturedReleaseId, &$capturedOrderRef) {
                $capturedReleaseId = $resId;
                $capturedOrderRef = $ref;
                return ['success' => true, 'message' => 'Stock reverted successfully.'];
            });

        // ConfirmStock must NEVER be called because the order failed
        $mockInventory->shouldNotReceive('confirmStock');

        $orderService = new OrderService($mockInventory);

        // 3. Intentionally provide invalid data that causes DB error during transaction
        // (missing required product in items causing failure)
        $invalidOrderData = [
            'name'    => 'Test Buyer',
            'email'   => 'test@example.com',
            'address' => 'Test Address',
            'phone'   => '1234567890',
            'items'   => [
                [
                    'product_id' => 999999, // Non-existent product ID triggering failure
                    'quantity'   => 2,
                ]
            ],
        ];

        $exceptionCaught = false;
        try {
            // This will reserve stock, but then fail during order creation
            // and trigger the compensating releaseStock call!
            $orderService->create($user, $invalidOrderData);
        } catch (\Throwable $e) {
            $exceptionCaught = true;
        }

        // 4. Assertions
        $this->assertTrue($exceptionCaught, 'Expected exception during order creation.');
        $this->assertEquals(
            $fakeReservationId,
            $capturedReleaseId,
            'Compensating ReleaseStock MUST be called with the exact reservation_id.'
        );
        $this->assertNotNull(
            $capturedOrderRef,
            'Compensating ReleaseStock MUST be called with the order_ref.'
        );
    }

    /**
     * Proof of Live End-to-End Compensation:
     * Using the real gRPC InventoryClient and PostgreSQL service:
     * 1. Check initial available stock.
     * 2. Attempt to create order, with an intentional DB failure during Order::create.
     * 3. Observe catch block triggering real ReleaseStock on inventory_service.
     * 4. Verify in PostgreSQL that reserved_stock is 0 and available_stock is restored.
     */
    public function test_live_microservice_compensation_reverts_stock(): void
    {
        $user = User::first() ?? User::create([
            'name'     => 'Live Comp Tester',
            'email'    => 'livecomp_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role'     => 'user',
        ]);

        $client = new InventoryClient();
        $productId = 2;
        $stockBefore = $client->getStock($productId);

        $orderService = new OrderService($client);

        // Force Order model to fail during insertion inside DB::transaction
        \App\Models\Order::creating(function () {
            throw new \RuntimeException('Simulated database error during Order persistence.');
        });

        $caught = false;
        try {
            $orderService->create($user, [
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
        }

        // Reset Order event listeners so it doesn't affect subsequent operations
        \App\Models\Order::flushEventListeners();

        $this->assertTrue($caught, 'Forced DB error was caught.');

        // Verify stock in live PostgreSQL microservice is completely reverted
        $stockAfter = $client->getStock($productId);

        $this->assertEquals(
            0,
            $stockAfter['reserved_stock'],
            'Reserved stock must return to 0 after compensation.'
        );
        $this->assertEquals(
            $stockBefore['available_stock'],
            $stockAfter['available_stock'],
            'Available stock must be restored to its exact original quantity.'
        );
    }
}
