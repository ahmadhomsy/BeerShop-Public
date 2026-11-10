<?php

namespace App\Services\Inventory;

use App\Exceptions\InventoryServiceUnavailableException;
use App\Models\InventoryReconciliation;
use App\Models\Order;
use App\Models\OrderPart;
use Illuminate\Support\Facades\Log;

class InventoryReconciliationService
{
    protected InventoryClient $inventoryClient;

    public function __construct(InventoryClient $inventoryClient)
    {
        $this->inventoryClient = $inventoryClient;
    }

    /**
     * Compute a deterministic SHA-256 hash for an order request payload.
     */
    public static function hashPayload(array $payload): string
    {
        // Extract relevant payload fields for deterministic hashing
        $normalized = [
            'name'    => (string) ($payload['name'] ?? ''),
            'email'   => (string) ($payload['email'] ?? ''),
            'address' => (string) ($payload['address'] ?? ''),
            'phone'   => (string) ($payload['phone'] ?? ''),
            'note'    => (string) ($payload['note'] ?? ''),
            'items'   => [],
        ];

        if (!empty($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $item) {
                $normalized['items'][] = [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'quantity'   => (int) ($item['quantity'] ?? 0),
                ];
            }
            // Sort items by product_id for order-independent hashing
            usort($normalized['items'], function ($a, $b) {
                return $a['product_id'] <=> $b['product_id'];
            });
        }

        return hash('sha256', json_encode($normalized));
    }

    /**
     * Reconcile a batch of pending reconciliation records.
     *
     * @param int $limit Maximum records to process
     * @return array Summary of processed items
     */
    public function reconcilePending(int $limit = 50): array
    {
        $records = InventoryReconciliation::whereIn('workflow_state', [
                InventoryReconciliation::STATE_PENDING_CONFIRMATION,
                InventoryReconciliation::STATE_PENDING_RELEASE,
            ])
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                      ->orWhere('next_retry_at', '<=', now());
            })
            ->where('retry_count', '<', 10)
            ->orderBy('next_retry_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $summary = [
            'processed' => 0,
            'confirmed' => 0,
            'released'  => 0,
            'retried'   => 0,
            'failed'    => 0,
        ];

        foreach ($records as $record) {
            $summary['processed']++;
            $result = $this->reconcileRecord($record);

            if ($record->workflow_state === InventoryReconciliation::STATE_CONFIRMED) {
                $summary['confirmed']++;
            } elseif ($record->workflow_state === InventoryReconciliation::STATE_RELEASED) {
                $summary['released']++;
            } elseif ($record->workflow_state === InventoryReconciliation::STATE_FAILED) {
                $summary['failed']++;
            } else {
                $summary['retried']++;
            }
        }

        return $summary;
    }

    /**
     * Reconcile a single inventory reconciliation record.
     */
    public function reconcileRecord(InventoryReconciliation $record): bool
    {
        if ($record->workflow_state === InventoryReconciliation::STATE_PENDING_CONFIRMATION) {
            return $this->reconcileConfirmation($record);
        }

        if ($record->workflow_state === InventoryReconciliation::STATE_PENDING_RELEASE) {
            return $this->compensateRelease($record);
        }

        return true;
    }

    /**
     * Reconcile an ambiguous pending confirmation.
     */
    protected function reconcileConfirmation(InventoryReconciliation $record): bool
    {
        try {
            $confirmResult = $this->inventoryClient->confirmStock(
                $record->reservation_id,
                $record->order_ref
            );

            if ($confirmResult['success']) {
                // Succeeded or already confirmed idempotently in PostgreSQL
                $record->update([
                    'workflow_state' => InventoryReconciliation::STATE_CONFIRMED,
                    'last_error'     => null,
                    'next_retry_at'  => null,
                ]);

                // Ensure MySQL order is active (0 = pending)
                if ($record->order_id) {
                    $order = Order::find($record->order_id);
                    if ($order && $order->status === 'cancelled') {
                        $order->update(['status' => '0']);
                        OrderPart::where('order_id', $order->id)->update(['status' => '0']);
                    }
                }

                Log::info("[Reconciler] Successfully confirmed reservation {$record->reservation_id} for order_ref {$record->order_ref}");
                return true;
            }

            // Definite business rejection from Python inventory service
            // (e.g. "Cannot confirm an expired reservation." or "Reservation not found.")
            Log::warning("[Reconciler] Confirmation rejected for {$record->reservation_id}: " . ($confirmResult['message'] ?? 'Unknown'));

            // Cancel the MySQL order
            if ($record->order_id) {
                $order = Order::find($record->order_id);
                if ($order && $order->status !== 'cancelled') {
                    $order->update(['status' => 'cancelled']);
                    OrderPart::where('order_id', $order->id)->update(['status' => 'cancelled']);
                }
            }

            $record->update([
                'workflow_state' => InventoryReconciliation::STATE_PENDING_RELEASE,
                'last_error'     => 'Confirmation rejected: ' . ($confirmResult['message'] ?? 'Business rejection'),
                'next_retry_at'  => now(),
            ]);

            return $this->compensateRelease($record);

        } catch (InventoryServiceUnavailableException $e) {
            // Transport error: network timeout, service down
            $this->applyRetryBackoff($record, 'Confirmation transport failure: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->applyRetryBackoff($record, 'Confirmation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Perform compensation release for a reservation.
     */
    public function compensateRelease(InventoryReconciliation $record): bool
    {
        try {
            $releaseResult = $this->inventoryClient->releaseStock(
                $record->reservation_id,
                $record->order_ref
            );

            if ($releaseResult['success']) {
                // Succeeded or already released idempotently
                $record->update([
                    'workflow_state' => InventoryReconciliation::STATE_RELEASED,
                    'last_error'     => null,
                    'next_retry_at'  => null,
                ]);

                Log::info("[Reconciler] Successfully released reservation {$record->reservation_id} for order_ref {$record->order_ref}");
                return true;
            }

            $msg = $releaseResult['message'] ?? 'Release failed';

            // CRITICAL: Check if inventory rejected because reservation was confirmed
            // "Cannot release a confirmed reservation."
            if (str_contains(strtolower($msg), 'cannot release a confirmed reservation')) {
                Log::warning("[Reconciler] Reservation {$record->reservation_id} is already confirmed in PostgreSQL. Reconciling to confirmed.");
                $record->update([
                    'workflow_state' => InventoryReconciliation::STATE_CONFIRMED,
                    'last_error'     => 'Reservation was confirmed in inventory service. Reconciled state to confirmed.',
                    'next_retry_at'  => null,
                ]);

                // Ensure MySQL order is active
                if ($record->order_id) {
                    $order = Order::find($record->order_id);
                    if ($order && $order->status === 'cancelled') {
                        $order->update(['status' => '0']);
                        OrderPart::where('order_id', $order->id)->update(['status' => '0']);
                    }
                }
                return true;
            }

            // Normal release failure (e.g. transport timeout or lock wait)
            $this->applyRetryBackoff($record, 'Release failure: ' . $msg);
            return false;

        } catch (\Throwable $e) {
            $this->applyRetryBackoff($record, 'Release exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply exponential backoff and update retry count.
     */
    protected function applyRetryBackoff(InventoryReconciliation $record, string $errorMessage): void
    {
        $newRetryCount = $record->retry_count + 1;
        $maxRetries = $record->max_retries ?: 5;

        if ($newRetryCount >= $maxRetries) {
            $record->update([
                'workflow_state' => InventoryReconciliation::STATE_FAILED,
                'retry_count'    => $newRetryCount,
                'last_error'     => $errorMessage,
                'next_retry_at'  => null,
            ]);
            Log::error("[Reconciler] Record {$record->id} (order_ref {$record->order_ref}) exceeded max retries ({$maxRetries}). State marked FAILED.");
        } else {
            // Exponential backoff: 2s, 4s, 8s, 16s...
            $backoffSeconds = min(60, (int) pow(2, $newRetryCount));
            $record->update([
                'retry_count'   => $newRetryCount,
                'next_retry_at' => now()->addSeconds($backoffSeconds),
                'last_error'    => $errorMessage,
            ]);
            Log::warning("[Reconciler] Record {$record->id} retry {$newRetryCount}/{$maxRetries} scheduled in {$backoffSeconds}s. Error: {$errorMessage}");
        }
    }
}
