<?php

namespace App\Modules\Orders\Services;

use App\Exceptions\InventoryServiceUnavailableException;
use App\Models\InventoryReconciliation;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderPart;
use App\Models\User;
use App\Modules\Orders\Events\OrderCreatedEvent;
use App\Modules\Orders\Events\OrderStatusUpdatedEvent;
use App\Modules\Products\Contracts\ProductCatalogInterface;
use App\Services\Inventory\InventoryClient;
use App\Services\Inventory\InventoryReconciliationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    protected InventoryClient $inventoryClient;
    protected ProductCatalogInterface $productCatalog;

    public function __construct(InventoryClient $inventoryClient, ?ProductCatalogInterface $productCatalog = null)
    {
        $this->inventoryClient = $inventoryClient;
        $this->productCatalog = $productCatalog ?? app(ProductCatalogInterface::class);
    }

    /**
     * Get paginated orders based on role.
     * Customers see only their own orders. Admin sees all.
     */
    public function listForUser(User $user, int $perPage = 10): LengthAwarePaginator
    {
        $query = Order::with(['orderParts.orderDetails.product', 'user:id,name,email']);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Find single order.
     */
    public function find(int $id): ?Order
    {
        return Order::with(['orderParts.orderDetails.product', 'user:id,name,email'])->find($id);
    }

    /**
     * Create an order with gRPC inventory reservation, idempotency, and durable reconciliation.
     */
    public function create(User $user, array $data, ?string $idempotencyKey = null): Order
    {
        $items = $data['items'];
        $productIds = collect($items)->pluck('product_id')->all();
        $products = $this->productCatalog->getProductsByIds($productIds);

        // 1. Idempotency Check
        $payloadHash = InventoryReconciliationService::hashPayload($data);

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = InventoryReconciliation::where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                // If payload hash differs, reject with HTTP 422
                if ($existing->payload_hash !== $payloadHash) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['The provided Idempotency-Key was already used with a different request payload.'],
                    ]);
                }

                // If already associated with an order, return existing order idempotently
                if ($existing->order_id) {
                    $existingOrder = Order::with(['orderParts.orderDetails.product', 'user:id,name,email'])
                        ->find($existing->order_id);
                    if ($existingOrder) {
                        return $existingOrder;
                    }
                }
            }
        }

        // 2. Generate stable order_ref
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $orderRef = 'ORD-IDEM-' . substr(hash('sha256', $idempotencyKey), 0, 20);
        } else {
            $orderRef = 'ORD-' . strtoupper(Str::random(10)) . '-' . time();
        }

        // 3. Format items for gRPC reservation
        $grpcItems = array_map(function ($it) {
            return [
                'product_id' => (int) $it['product_id'],
                'quantity'   => (int) $it['quantity'],
            ];
        }, $items);

        // 4. Call Inventory Microservice via gRPC to Reserve Stock (Atomic, Idempotent by order_ref)
        $reservation = $this->inventoryClient->reserveStock($orderRef, $grpcItems, 300);

        if (!$reservation['success']) {
            throw ValidationException::withMessages([
                'stock'        => [$reservation['message'] ?? 'Stock reservation failed.'],
                'failed_items' => $reservation['failed_items'] ?? [],
            ]);
        }

        $reservationId = $reservation['reservation_id'];

        // 5. Short, isolated MySQL transaction to persist order and outbox reconciliation record
        $reconciliation = null;
        $order = null;

        try {
            $order = DB::transaction(function () use (
                $user, $data, $items, $products, $orderRef, $idempotencyKey, $payloadHash, $reservationId, &$reconciliation
            ) {
                $sellerGroups = [];
                $subtotal = 0;

                foreach ($items as $item) {
                    $product = $products->get($item['product_id']);
                    if (!$product) {
                        throw new \Exception("Product with ID {$item['product_id']} not found.");
                    }

                    $qty = (int) $item['quantity'];
                    $itemTotal = $product->price * $qty;
                    $subtotal += $itemTotal;

                    $sellerId = $product->seller_id;
                    $sellerGroups[$sellerId][] = [
                        'product_id' => $product->id,
                        'quantity'   => $qty,
                        'price'      => $itemTotal,
                    ];
                }

                $shipping = 15;
                $grandTotal = $subtotal + $shipping;

                $createdOrder = Order::create([
                    'user_id' => $user->id,
                    'name'    => $data['name'],
                    'email'   => $data['email'],
                    'address' => $data['address'],
                    'phone'   => $data['phone'],
                    'note'    => $data['note'] ?? '',
                    'total'   => $grandTotal,
                    'status'  => '0', // 0 = pending
                ]);

                foreach ($sellerGroups as $sellerId => $sellerItems) {
                    $partTotal = collect($sellerItems)->sum('price');
                    $partQty = count($sellerItems);

                    $orderPart = OrderPart::create([
                        'order_id'  => $createdOrder->id,
                        'seller_id' => $sellerId,
                        'total'     => $partTotal,
                        'quantity'  => $partQty,
                        'status'    => '0',
                    ]);

                    foreach ($sellerItems as $sItem) {
                        OrderDetail::create([
                            'orderpart_id' => $orderPart->id,
                            'product_id'   => $sItem['product_id'],
                            'quantity'     => $sItem['quantity'],
                            'price'        => $sItem['price'],
                        ]);
                    }
                }

                // Create outbox entry inside the same database transaction
                $reconciliation = InventoryReconciliation::updateOrCreate(
                    ['order_ref' => $orderRef],
                    [
                        'order_id'        => $createdOrder->id,
                        'idempotency_key' => $idempotencyKey,
                        'payload_hash'    => $payloadHash,
                        'reservation_id'  => $reservationId,
                        'workflow_state'  => InventoryReconciliation::STATE_PENDING_CONFIRMATION,
                        'items_payload'   => $items,
                        'retry_count'     => 0,
                        'max_retries'     => 5,
                        'next_retry_at'   => null,
                        'last_error'      => null,
                    ]
                );

                // Dispatch domain event: OrderCreatedEvent (Decoupled Pub/Sub)
                event(new OrderCreatedEvent($createdOrder));

                return $createdOrder->load(['orderParts.orderDetails.product']);
            });
        } catch (\Throwable $txException) {
            // MySQL transaction failed: order was not saved.
            // Attempt immediate compensation release.
            $releaseRes = $this->inventoryClient->releaseStock($reservationId, $orderRef);

            if (!$releaseRes['success']) {
                // If compensation fails, record durable recovery entry
                try {
                    InventoryReconciliation::create([
                        'order_id'        => null,
                        'order_ref'       => $orderRef,
                        'idempotency_key' => $idempotencyKey,
                        'payload_hash'    => $payloadHash,
                        'reservation_id'  => $reservationId,
                        'workflow_state'  => InventoryReconciliation::STATE_PENDING_RELEASE,
                        'items_payload'   => $items,
                        'retry_count'     => 1,
                        'max_retries'     => 5,
                        'next_retry_at'   => now()->addSeconds(5),
                        'last_error'      => 'MySQL transaction aborted; immediate compensation failed: ' . ($releaseRes['message'] ?? 'Unknown'),
                    ]);
                } catch (\Throwable $ignored) {
                    Log::error("[OrderService] Failed to persist failed compensation outbox: " . $ignored->getMessage());
                }
            }

            throw $txException;
        }

        // 6. Confirm reservation in inventory service OUTSIDE of MySQL transaction
        try {
            $confirmResult = $this->inventoryClient->confirmStock($reservationId, $orderRef);
        } catch (InventoryServiceUnavailableException $transportException) {
            // CRITICAL DISTINCTION: Transport timeout / gRPC UNAVAILABLE.
            // A transport timeout is NOT proof that the remote operation failed!
            // The reservation may already be confirmed in PostgreSQL or can be confirmed upon retry.
            // NEVER cancel the order and NEVER call releaseStock here!
            if ($reconciliation) {
                $reconciliation->update([
                    'workflow_state' => InventoryReconciliation::STATE_PENDING_CONFIRMATION,
                    'retry_count'    => $reconciliation->retry_count + 1,
                    'next_retry_at'  => now()->addSeconds(5),
                    'last_error'     => 'Transport timeout/failure during ConfirmStock: ' . $transportException->getMessage(),
                ]);
            }

            // Re-throw 503 so client is informed of temporary outage while state remains recoverable
            throw $transportException;
        }

        // 7. Evaluate Confirmation Outcome
        if ($confirmResult['success']) {
            // Confirmation succeeded (or was already confirmed idempotently)
            if ($reconciliation) {
                $reconciliation->update([
                    'workflow_state' => InventoryReconciliation::STATE_CONFIRMED,
                    'last_error'     => null,
                    'next_retry_at'  => null,
                ]);
            }

            return $order;
        }

        // Definite business rejection from Python inventory service
        // (e.g. "Cannot confirm an expired reservation." or "Reservation not found.")
        $order->update(['status' => 'cancelled']);
        OrderPart::where('order_id', $order->id)->update(['status' => 'cancelled']);

        if ($reconciliation) {
            $reconciliation->update([
                'workflow_state' => InventoryReconciliation::STATE_PENDING_RELEASE,
                'last_error'     => 'Confirmation rejected: ' . ($confirmResult['message'] ?? 'Unknown'),
                'next_retry_at'  => now()->addSeconds(5),
            ]);
        }

        // Attempt compensation release
        $releaseResult = $this->inventoryClient->releaseStock($reservationId, $orderRef);

        if ($releaseResult['success']) {
            if ($reconciliation) {
                $reconciliation->update([
                    'workflow_state' => InventoryReconciliation::STATE_RELEASED,
                    'last_error'     => null,
                    'next_retry_at'  => null,
                ]);
            }
        } else {
            if ($reconciliation) {
                $reconciliation->update([
                    'workflow_state' => InventoryReconciliation::STATE_PENDING_RELEASE,
                    'retry_count'    => $reconciliation->retry_count + 1,
                    'next_retry_at'  => now()->addSeconds(5),
                    'last_error'     => 'Compensation release failed: ' . ($releaseResult['message'] ?? 'Unknown error'),
                ]);
            }
        }

        throw ValidationException::withMessages([
            'stock' => [$confirmResult['message'] ?? 'Inventory reservation could not be confirmed.'],
        ]);
    }

    /**
     * Update order status and notify user.
     */
    public function updateStatus(Order $order, string $newStatus): Order
    {
        $order->status = $newStatus;
        $order->save();

        OrderPart::where('order_id', $order->id)->update(['status' => $newStatus]);

        // Dispatch domain event: OrderStatusUpdatedEvent (Decoupled Pub/Sub)
        event(new OrderStatusUpdatedEvent($order, $newStatus));

        return $order->fresh(['orderParts.orderDetails.product', 'user:id,name,email']);
    }
}
