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
use Illuminate\Database\QueryException;
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
     * Create an order with gRPC inventory reservation, user-scoped idempotency, and durable reconciliation.
     */
    public function create(User $user, array $data, ?string $idempotencyKey = null): Order
    {
        $items = $data['items'];
        $productIds = collect($items)->pluck('product_id')->all();
        $products = $this->productCatalog->getProductsByIds($productIds);

        // 1. Length validation on Idempotency-Key (max 255 chars)
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            if (strlen($idempotencyKey) > 255) {
                throw ValidationException::withMessages([
                    'idempotency_key' => ['The Idempotency-Key must not exceed 255 characters.'],
                ]);
            }
        }

        $payloadHash = InventoryReconciliationService::hashPayload($data);

        // Stable order_ref scoped to authenticated user ID
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $faultTag = '';
            $allowFaultInjection = filter_var(config('services.inventory.enable_fault_injection', false), FILTER_VALIDATE_BOOLEAN);
            if ($allowFaultInjection && str_contains($idempotencyKey, 'FAULT_TIMEOUT_')) {
                preg_match('/FAULT_TIMEOUT_[A-Z]+/', $idempotencyKey, $m);
                if (!empty($m[0])) {
                    $faultTag = $m[0] . '-';
                }
            }
            $orderRef = 'ORD-U' . $user->id . '-' . $faultTag . substr(hash('sha256', $idempotencyKey), 0, 16);
        } else {
            $orderRef = 'ORD-U' . $user->id . '-' . strtoupper(Str::random(10)) . '-' . time();
        }

        $grpcItems = array_map(function ($it) {
            return [
                'product_id' => (int) $it['product_id'],
                'quantity'   => (int) $it['quantity'],
            ];
        }, $items);

        $reconciliation = null;
        $processToken = Str::random(32);
        $leaseDurationSeconds = 30;

        // 2. User-Scoped Atomic Idempotency Claim
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = InventoryReconciliation::where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (!$existing) {
                // Attempt atomic creation of the intent record
                try {
                    $reconciliation = InventoryReconciliation::create([
                        'user_id'         => $user->id,
                        'order_ref'       => $orderRef,
                        'idempotency_key' => $idempotencyKey,
                        'payload_hash'    => $payloadHash,
                        'workflow_state'  => InventoryReconciliation::STATE_PENDING_RESERVATION,
                        'items_payload'   => $items,
                        'retry_count'     => 0,
                        'max_retries'     => 5,
                        'locked_until'    => now()->addSeconds($leaseDurationSeconds),
                        'locked_by'       => $processToken,
                    ]);
                } catch (QueryException $e) {
                    // Unique constraint race: another concurrent request created the record first
                    $existing = InventoryReconciliation::where('user_id', $user->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();
                    if (!$existing) {
                        throw $e;
                    }
                }
            }

            if ($existing) {
                // 1. Verify payload hash before processing
                if ($existing->payload_hash !== $payloadHash) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['The provided Idempotency-Key was already used with a different request payload.'],
                    ]);
                }

                // 2. If already confirmed with order, return existing order idempotently (scoped to user)
                if ($existing->order_id && $existing->workflow_state === InventoryReconciliation::STATE_CONFIRMED) {
                    $existingOrder = Order::where('user_id', $user->id)
                        ->with(['orderParts.orderDetails.product', 'user:id,name,email'])
                        ->find($existing->order_id);
                    if ($existingOrder) {
                        return $existingOrder;
                    }
                }

                // 3. Atomic Conditional Compare-And-Set Claim with bounded spin-wait
                $maxWaitMs = 3500;
                $intervalMs = 100;
                $elapsedMs = 0;
                $acquired = false;

                while ($elapsedMs <= $maxWaitMs) {
                    $existing->refresh();
                    if ($existing->order_id && $existing->workflow_state === InventoryReconciliation::STATE_CONFIRMED) {
                        $existingOrder = Order::where('user_id', $user->id)
                            ->with(['orderParts.orderDetails.product', 'user:id,name,email'])
                            ->find($existing->order_id);
                        if ($existingOrder) {
                            return $existingOrder;
                        }
                    }

                    // Attempt atomic claim: ONLY if locked_until is null or expired
                    $now = now();
                    $affected = InventoryReconciliation::where('id', $existing->id)
                        ->where('user_id', $user->id)
                        ->where(function ($q) use ($now) {
                            $q->whereNull('locked_until')->orWhere('locked_until', '<', $now);
                        })
                        ->where('workflow_state', '!=', InventoryReconciliation::STATE_CONFIRMED)
                        ->update([
                            'locked_until' => now()->addSeconds($leaseDurationSeconds),
                            'locked_by'    => $processToken,
                        ]);

                    if ($affected > 0) {
                        $acquired = true;
                        $existing->refresh();
                        $reconciliation = $existing;
                        break;
                    }

                    // Lease still held by another active process: wait without stealing
                    usleep($intervalMs * 1000);
                    $elapsedMs += $intervalMs;
                }

                if (!$acquired) {
                    $existing->refresh();
                    if ($existing->order_id && $existing->workflow_state === InventoryReconciliation::STATE_CONFIRMED) {
                        $existingOrder = Order::where('user_id', $user->id)
                            ->with(['orderParts.orderDetails.product', 'user:id,name,email'])
                            ->find($existing->order_id);
                        if ($existingOrder) {
                            return $existingOrder;
                        }
                    }

                    // Lease is unexpired and owned by another request: do NOT steal lease and do NOT duplicate reservation
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['An identical request with this Idempotency-Key is currently in progress. Please retry shortly.'],
                    ]);
                }
            }
        }

        // If no idempotency key was supplied, create an intent record with idempotency_key = null
        if (!$reconciliation) {
            $reconciliation = InventoryReconciliation::create([
                'user_id'         => $user->id,
                'order_ref'       => $orderRef,
                'idempotency_key' => null,
                'payload_hash'    => $payloadHash,
                'workflow_state'  => InventoryReconciliation::STATE_PENDING_RESERVATION,
                'items_payload'   => $items,
                'retry_count'     => 0,
                'max_retries'     => 5,
                'locked_until'    => now()->addSeconds(15),
                'locked_by'       => $processToken,
            ]);
        }

        try {
            // 3. Reserve Stock via gRPC (All-or-Nothing, Idempotent by order_ref in PostgreSQL)
            $reservationId = $reconciliation->reservation_id;

            if (!$reservationId) {
                try {
                    $reservation = $this->inventoryClient->reserveStock($orderRef, $grpcItems, 300);
                } catch (InventoryServiceUnavailableException $transportException) {
                    $reconciliation->update([
                        'workflow_state' => InventoryReconciliation::STATE_PENDING_RESERVATION,
                        'retry_count'    => $reconciliation->retry_count + 1,
                        'next_retry_at'  => now()->addSeconds(5),
                        'last_error'     => 'ReserveStock transport failure: ' . $transportException->getMessage(),
                    ]);
                    throw $transportException;
                }

                if (!$reservation['success']) {
                    $reconciliation->update([
                        'workflow_state' => InventoryReconciliation::STATE_FAILED,
                        'last_error'     => $reservation['message'] ?? 'Stock reservation failed.',
                    ]);

                    throw ValidationException::withMessages([
                        'stock'        => [$reservation['message'] ?? 'Stock reservation failed.'],
                        'failed_items' => $reservation['failed_items'] ?? [],
                    ]);
                }

                $reservationId = $reservation['reservation_id'];
                $reconciliation->update([
                    'reservation_id' => $reservationId,
                    'last_error'     => null,
                ]);
            }

            // 4. Short isolated MySQL transaction to persist order and link outbox record
            $order = null;

            try {
                $order = DB::transaction(function () use (
                    $user, $data, $items, $products, $orderRef, $reservationId, $reconciliation
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

                    $reconciliation->update([
                        'order_id'       => $createdOrder->id,
                        'workflow_state' => InventoryReconciliation::STATE_PENDING_CONFIRMATION,
                        'last_error'     => null,
                    ]);

                    event(new OrderCreatedEvent($createdOrder));

                    return $createdOrder->load(['orderParts.orderDetails.product']);
                });
            } catch (\Throwable $txException) {
                // MySQL transaction failed: order was never persisted.
                $releaseRes = $this->inventoryClient->releaseStock($reservationId, $orderRef);

                if ($releaseRes['success']) {
                    $reconciliation->update([
                        'workflow_state' => InventoryReconciliation::STATE_RELEASED,
                        'last_error'     => null,
                    ]);
                } else {
                    $reconciliation->update([
                        'workflow_state' => InventoryReconciliation::STATE_PENDING_RELEASE,
                        'retry_count'    => $reconciliation->retry_count + 1,
                        'next_retry_at'  => now()->addSeconds(5),
                        'last_error'     => 'MySQL transaction aborted; immediate compensation failed: ' . ($releaseRes['message'] ?? 'Unknown'),
                    ]);
                }

                throw $txException;
            }

            // 5. Confirm reservation in inventory service OUTSIDE of MySQL transaction
            try {
                $confirmResult = $this->inventoryClient->confirmStock($reservationId, $orderRef);
            } catch (InventoryServiceUnavailableException $transportException) {
                $reconciliation->update([
                    'workflow_state' => InventoryReconciliation::STATE_PENDING_CONFIRMATION,
                    'retry_count'    => $reconciliation->retry_count + 1,
                    'next_retry_at'  => now()->addSeconds(5),
                    'last_error'     => 'Transport timeout/failure during ConfirmStock: ' . $transportException->getMessage(),
                ]);

                throw $transportException;
            }

            // 6. Evaluate Confirmation Outcome
            if ($confirmResult['success']) {
                $reconciliation->update([
                    'workflow_state' => InventoryReconciliation::STATE_CONFIRMED,
                    'last_error'     => null,
                    'next_retry_at'  => null,
                ]);

                return $order;
            }

            // Definite business rejection from Python inventory service
            $order->update(['status' => 'cancelled']);
            OrderPart::where('order_id', $order->id)->update(['status' => 'cancelled']);

            $reconciliation->update([
                'workflow_state' => InventoryReconciliation::STATE_PENDING_RELEASE,
                'last_error'     => 'Confirmation rejected: ' . ($confirmResult['message'] ?? 'Unknown'),
                'next_retry_at'  => now()->addSeconds(5),
            ]);

            $releaseResult = $this->inventoryClient->releaseStock($reservationId, $orderRef);

            if ($releaseResult['success']) {
                $reconciliation->update([
                    'workflow_state' => InventoryReconciliation::STATE_RELEASED,
                    'last_error'     => null,
                    'next_retry_at'  => null,
                ]);
            } else {
                $reconciliation->update([
                    'workflow_state' => InventoryReconciliation::STATE_PENDING_RELEASE,
                    'retry_count'    => $reconciliation->retry_count + 1,
                    'next_retry_at'  => now()->addSeconds(5),
                    'last_error'     => 'Compensation release failed: ' . ($releaseResult['message'] ?? 'Unknown error'),
                ]);
            }

            throw ValidationException::withMessages([
                'stock' => [$confirmResult['message'] ?? 'Inventory reservation could not be confirmed.'],
            ]);

        } finally {
            // Clear the in-flight process lock ONLY if this process token still owns it
            if ($reconciliation && $reconciliation->id) {
                try {
                    InventoryReconciliation::where('id', $reconciliation->id)
                        ->where('user_id', $user->id)
                        ->where('locked_by', $processToken)
                        ->update([
                            'locked_until' => null,
                            'locked_by'    => null,
                        ]);
                } catch (\Throwable $ignored) {
                }
            }
        }
    }

    /**
     * Update order status and notify user.
     */
    public function updateStatus(Order $order, string $newStatus): Order
    {
        $order->status = $newStatus;
        $order->save();

        OrderPart::where('order_id', $order->id)->update(['status' => $newStatus]);

        event(new OrderStatusUpdatedEvent($order, $newStatus));

        return $order->fresh(['orderParts.orderDetails.product', 'user:id,name,email']);
    }
}
