<?php

namespace App\Modules\Orders\Services;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderPart;
use App\Models\User;
use App\Modules\Orders\Events\OrderCreatedEvent;
use App\Modules\Orders\Events\OrderStatusUpdatedEvent;
use App\Modules\Products\Contracts\ProductCatalogInterface;
use App\Services\Inventory\InventoryClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
     * Create an order with gRPC inventory reservation and failure handling.
     */
    public function create(User $user, array $data): Order
    {
        $items = $data['items'];
        $productIds = collect($items)->pluck('product_id')->all();
        $products = $this->productCatalog->getProductsByIds($productIds);

        // 1. Generate Idempotency order_ref
        $orderRef = 'ORD-' . strtoupper(Str::random(10)) . '-' . time();

        // 2. Format items for gRPC reservation
        $grpcItems = array_map(function ($it) {
            return [
                'product_id' => (int) $it['product_id'],
                'quantity'   => (int) $it['quantity'],
            ];
        }, $items);

        // 3. Call Inventory Microservice via gRPC to Reserve Stock (All-or-Nothing)
        $reservation = $this->inventoryClient->reserveStock($orderRef, $grpcItems, 300);

        if (!$reservation['success']) {
            throw ValidationException::withMessages([
                'stock' => [$reservation['message'] ?? 'Stock reservation failed.'],
                'failed_items' => $reservation['failed_items'] ?? [],
            ]);
        }

        $reservationId = $reservation['reservation_id'];

        // 4. Save order in Database with compensating transaction rollback on error
        try {
            $order = DB::transaction(function () use ($user, $data, $items, $products) {
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

                $order = Order::create([
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
                        'order_id'  => $order->id,
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

                // Dispatch domain event: OrderCreatedEvent (Decoupled Pub/Sub)
                event(new OrderCreatedEvent($order));

                return $order->load(['orderParts.orderDetails.product']);
            });

            // 5. Confirm reservation in inventory service
            $confirmResult = $this->inventoryClient->confirmStock($reservationId, $orderRef);

            if (!$confirmResult['success']) {
                // Confirmation was rejected by inventory service (e.g. expired or invalid)
                throw ValidationException::withMessages([
                    'stock' => [$confirmResult['message'] ?? 'Inventory reservation could not be confirmed.'],
                ]);
            }

            return $order;

        } catch (\Throwable $e) {
            // Compensating action: If MySQL transaction or confirmation failed, ensure consistency
            if (isset($order) && $order->exists) {
                $order->update(['status' => 'cancelled']);
                OrderPart::where('order_id', $order->id)->update(['status' => 'cancelled']);
            }
            $this->inventoryClient->releaseStock($reservationId, $orderRef);
            throw $e;
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

        // Dispatch domain event: OrderStatusUpdatedEvent (Decoupled Pub/Sub)
        event(new OrderStatusUpdatedEvent($order, $newStatus));

        return $order->fresh(['orderParts.orderDetails.product', 'user:id,name,email']);
    }
}
