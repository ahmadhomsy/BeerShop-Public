<?php

namespace App\Services\Inventory;

use App\Exceptions\InventoryServiceUnavailableException;
use Exception;
use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Log;
use Inventory\ConfirmStockRequest;
use Inventory\GetStockRequest;
use Inventory\InventoryServiceClient;
use Inventory\ReleaseStockRequest;
use Inventory\ReserveStockRequest;
use Inventory\StockItem;

class InventoryClient
{
    protected string $host;
    protected int $port;
    protected float $timeoutSeconds;
    protected ?InventoryServiceClient $client = null;

    public function __construct(?InventoryServiceClient $client = null)
    {
        $this->host = env('INVENTORY_GRPC_HOST', '127.0.0.1');
        $this->port = (int) env('INVENTORY_GRPC_PORT', 50051);
        $this->timeoutSeconds = (float) env('INVENTORY_GRPC_TIMEOUT', 2.0);
        $this->client = $client;
    }

    /**
     * Get or initialize native gRPC Service Client.
     */
    protected function getClient(): InventoryServiceClient
    {
        if ($this->client === null) {
            if (!class_exists(\Grpc\ChannelCredentials::class)) {
                throw new InventoryServiceUnavailableException('gRPC PHP extension is not installed or service is unavailable.');
            }
            $target = "{$this->host}:{$this->port}";
            $this->client = new InventoryServiceClient($target, [
                'credentials' => ChannelCredentials::createInsecure(),
            ]);
        }
        return $this->client;
    }

    /**
     * Helper to get call options with deadline.
     */
    protected function getCallOptions(): array
    {
        return [
            'timeout' => (int) ($this->timeoutSeconds * 1000000), // timeout in microseconds
        ];
    }

    /**
     * Check gRPC call status and map DEADLINE_EXCEEDED / UNAVAILABLE to 503.
     */
    protected function handleStatus($status, string $operation): void
    {
        if (!isset($status->code) || $status->code === \Grpc\STATUS_OK) {
            return;
        }

        Log::error("[Native gRPC] {$operation} failed: target={$this->host}:{$this->port}, code={$status->code}, details={$status->details}");

        throw new InventoryServiceUnavailableException('Inventory service temporarily unavailable');
    }

    /**
     * RPC: GetStock
     */
    public function getStock(int $productId): array
    {
        try {
            $request = new GetStockRequest();
            $request->setProductId($productId);

            $call = $this->getClient()->GetStock($request, [], $this->getCallOptions());
            [$response, $status] = $call->wait();

            $this->handleStatus($status, 'GetStock');

            if (!$response) {
                Log::error("[Native gRPC] GetStock empty response/timeout from {$this->host}:{$this->port}");
                throw new InventoryServiceUnavailableException('Inventory service temporarily unavailable');
            }

            return [
                'product_id'      => $response->getProductId(),
                'total_stock'     => $response->getTotalStock(),
                'reserved_stock'  => $response->getReservedStock(),
                'available_stock' => $response->getAvailableStock(),
            ];
        } catch (InventoryServiceUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("[Native gRPC] GetStock uncaught exception: " . $e->getMessage());
            throw new InventoryServiceUnavailableException('Inventory service temporarily unavailable', 503, $e);
        }
    }

    /**
     * RPC: ReserveStock (All-or-Nothing, Idempotent by order_ref)
     */
    public function reserveStock(string $orderRef, array $items, int $ttlSeconds = 300): array
    {
        try {
            $request = new ReserveStockRequest();
            $request->setOrderRef($orderRef);
            $request->setTtlSeconds($ttlSeconds);

            $protoItems = [];
            foreach ($items as $item) {
                $stockItem = new StockItem();
                $stockItem->setProductId((int) $item['product_id']);
                $stockItem->setQuantity((int) $item['quantity']);
                $protoItems[] = $stockItem;
            }
            $request->setItems($protoItems);

            $call = $this->getClient()->ReserveStock($request, [], $this->getCallOptions());
            [$response, $status] = $call->wait();

            $this->handleStatus($status, 'ReserveStock');

            if (!$response) {
                Log::error("[Native gRPC] ReserveStock empty response/timeout from {$this->host}:{$this->port}");
                throw new InventoryServiceUnavailableException('Inventory service temporarily unavailable');
            }

            $failedItems = [];
            if ($response->getFailedProductIds()) {
                foreach ($response->getFailedProductIds() as $failedId) {
                    $failedItems[] = $failedId;
                }
            }

            return [
                'success'        => $response->getSuccess(),
                'reservation_id' => $response->getReservationId(),
                'message'        => $response->getMessage(),
                'failed_items'   => $failedItems,
            ];
        } catch (InventoryServiceUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("[Native gRPC] ReserveStock uncaught exception: " . $e->getMessage());
            throw new InventoryServiceUnavailableException('Inventory service temporarily unavailable', 503, $e);
        }
    }

    /**
     * RPC: ReleaseStock (Compensating action)
     */
    public function releaseStock(string $reservationId, string $orderRef): array
    {
        try {
            $request = new ReleaseStockRequest();
            $request->setReservationId($reservationId);
            $request->setOrderRef($orderRef);

            $call = $this->getClient()->ReleaseStock($request, [], $this->getCallOptions());
            [$response, $status] = $call->wait();

            if (isset($status->code) && $status->code !== \Grpc\STATUS_OK) {
                Log::warning("[gRPC ReleaseStock] failed: code {$status->code}, details: {$status->details}");
                return ['success' => false, 'message' => $status->details ?? 'Timeout releasing stock.'];
            }

            return [
                'success' => $response ? $response->getSuccess() : false,
                'message' => $response ? $response->getMessage() : 'No response from inventory service.',
            ];
        } catch (\Throwable $e) {
            Log::warning("[gRPC ReleaseStock] Exception during release: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * RPC: ConfirmStock (Confirms reservation after order is saved)
     */
    public function confirmStock(string $reservationId, string $orderRef): array
    {
        try {
            $request = new ConfirmStockRequest();
            $request->setReservationId($reservationId);
            $request->setOrderRef($orderRef);

            $call = $this->getClient()->ConfirmStock($request, [], $this->getCallOptions());
            [$response, $status] = $call->wait();

            $this->handleStatus($status, 'ConfirmStock');

            if (!$response) {
                Log::error("[Native gRPC] ConfirmStock empty response/timeout from {$this->host}:{$this->port}");
                throw new InventoryServiceUnavailableException('Inventory service temporarily unavailable');
            }

            return [
                'success' => $response->getSuccess(),
                'message' => $response->getMessage(),
            ];
        } catch (InventoryServiceUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("[Native gRPC] ConfirmStock uncaught exception: " . $e->getMessage());
            throw new InventoryServiceUnavailableException('Inventory service temporarily unavailable', 503, $e);
        }
    }
}
