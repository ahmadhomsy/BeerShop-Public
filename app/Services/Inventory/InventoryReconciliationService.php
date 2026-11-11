<?php

namespace App\Services\Inventory;

use App\Exceptions\InventoryServiceUnavailableException;
use App\Models\InventoryReconciliation;
use App\Models\Order;
use App\Models\OrderPart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            usort($normalized['items'], function ($a, $b) {
                return $a['product_id'] <=> $b['product_id'];
            });
        }

        return hash('sha256', json_encode($normalized));
    }

    /**
     * Reconcile a batch of pending reconciliation records with concurrency-safe atomic lease.
     *
     * @param int $limit Maximum records to process
     * @return array Summary of processed items
     */
    public function reconcilePending(int $limit = 50): array
    {
        $workerId = gethostname() . ':' . getmypid() . ':' . Str::random(12);
        $now = now();
        $driver = DB::connection()->getDriverName();

        // 1. Atomically claim a batch of eligible records in a fast database transaction
        $claimedRecords = DB::transaction(function () use ($limit, $workerId, $now, $driver) {
            $query = InventoryReconciliation::whereIn('workflow_state', [
                    InventoryReconciliation::STATE_PENDING_RESERVATION,
                    InventoryReconciliation::STATE_PENDING_CONFIRMATION,
                    InventoryReconciliation::STATE_PENDING_RELEASE,
                ])
                ->where(function ($query) use ($now) {
                    $query->whereNull('next_retry_at')
                          ->orWhere('next_retry_at', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('locked_until')
                          ->orWhere('locked_until', '<=', $now);
                })
                ->whereColumn('retry_count', '<', 'max_retries')
                ->orderBy('next_retry_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit($limit);

            // Use non-blocking row locks where supported (MySQL 8.0 & PostgreSQL)
            if ($driver === 'mysql' || $driver === 'pgsql') {
                $candidates = $query->lock('FOR UPDATE SKIP LOCKED')->get();
            } else {
                $candidates = $query->get();
            }

            $ids = $candidates->pluck('id')->all();
            if (!empty($ids)) {
                // Conditional atomic compare-and-set: ensure lease is still null or expired
                InventoryReconciliation::whereIn('id', $ids)
                    ->where(function ($query) use ($now) {
                        $query->whereNull('locked_until')
                              ->orWhere('locked_until', '<=', $now);
                    })
                    ->update([
                        'locked_until' => $now->copy()->addMinutes(2),
                        'locked_by'    => $workerId,
                    ]);

                // Return ONLY records that were successfully leased to this workerId
                return InventoryReconciliation::whereIn('id', $ids)
                    ->where('locked_by', $workerId)
                    ->get();
            }

            return collect();
        });

        $summary = [
            'processed' => 0,
            'confirmed' => 0,
            'released'  => 0,
            'retried'   => 0,
            'failed'    => 0,
        ];

        // 2. Process each claimed record OUTSIDE of any database transaction (no MySQL locks held during gRPC)
        foreach ($claimedRecords as $record) {
            $summary['processed']++;

            try {
                $this->reconcileRecord($record);
            } catch (\Throwable $e) {
                Log::error("[Reconciler] Unexpected error reconciling record {$record->id}: " . $e->getMessage());
            } finally {
                // Clear the worker lease lock ONLY if this worker still owns it
                try {
                    InventoryReconciliation::where('id', $record->id)
                        ->where('locked_by', $workerId)
                        ->update([
                            'locked_until' => null,
                            'locked_by'    => null,
                        ]);
                } catch (\Throwable $ignored) {
                }
            }

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
        if ($record->workflow_state === InventoryReconciliation::STATE_PENDING_RESERVATION) {
            return $this->reconcileReservation($record);
        }

        if ($record->workflow_state === InventoryReconciliation::STATE_PENDING_CONFIRMATION) {
            return $this->reconcileConfirmation($record);
        }

        if ($record->workflow_state === InventoryReconciliation::STATE_PENDING_RELEASE) {
            return $this->compensateRelease($record);
        }

        return true;
    }

    /**
     * Reconcile an ambiguous or abandoned pending reservation.
     */
    protected function reconcileReservation(InventoryReconciliation $record): bool
    {
        // Case 1: If reservation_id is already set
        if (!empty($record->reservation_id)) {
            if ($record->order_id) {
                // MySQL order exists: transition to pending_confirmation and proceed
                $record->update([
                    'workflow_state' => InventoryReconciliation::STATE_PENDING_CONFIRMATION,
                ]);
                return $this->reconcileConfirmation($record);
            }

            // If order was never created and intent is older than 5 minutes, release reservation
            if ($record->created_at && $record->created_at->diffInMinutes(now()) >= 5) {
                Log::info("[Reconciler] Releasing abandoned reservation intent {$record->reservation_id} for order_ref {$record->order_ref}");
                $record->update([
                    'workflow_state' => InventoryReconciliation::STATE_PENDING_RELEASE,
                ]);
                return $this->compensateRelease($record);
            }

            return false;
        }

        // Case 2: reservation_id is null (ReserveStock response was lost or never succeeded)
        // Check if items payload is available to retry reservation
        if (empty($record->items_payload) || !is_array($record->items_payload)) {
            $record->update([
                'workflow_state' => InventoryReconciliation::STATE_FAILED,
                'last_error'     => 'Cannot retry reservation: missing items payload.',
                'next_retry_at'  => null,
            ]);
            return false;
        }

        try {
            $grpcItems = array_map(function ($it) {
                return [
                    'product_id' => (int) ($it['product_id'] ?? 0),
                    'quantity'   => (int) ($it['quantity'] ?? 0),
                ];
            }, $record->items_payload);

            // Re-call ReserveStock with the exact same order_ref (idempotent in PostgreSQL)
            $res = $this->inventoryClient->reserveStock($record->order_ref, $grpcItems, 300);

            if ($res['success']) {
                $record->update([
                    'reservation_id' => $res['reservation_id'],
                    'last_error'     => null,
                ]);

                if ($record->order_id) {
                    $record->update([
                        'workflow_state' => InventoryReconciliation::STATE_PENDING_CONFIRMATION,
                    ]);
                    return $this->reconcileConfirmation($record);
                }

                return true;
            }

            // Definite rejection (e.g. out of stock)
            $record->update([
                'workflow_state' => InventoryReconciliation::STATE_FAILED,
                'last_error'     => $res['message'] ?? 'Stock reservation failed on retry.',
                'next_retry_at'  => null,
            ]);
            return false;

        } catch (InventoryServiceUnavailableException $e) {
            $this->applyRetryBackoff($record, 'Reservation retry transport failure: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->applyRetryBackoff($record, 'Reservation retry error: ' . $e->getMessage());
            return false;
        }
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
                $record->update([
                    'workflow_state' => InventoryReconciliation::STATE_CONFIRMED,
                    'last_error'     => null,
                    'next_retry_at'  => null,
                ]);

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

            Log::warning("[Reconciler] Confirmation rejected for {$record->reservation_id}: " . ($confirmResult['message'] ?? 'Unknown'));

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
                $record->update([
                    'workflow_state' => InventoryReconciliation::STATE_RELEASED,
                    'last_error'     => null,
                    'next_retry_at'  => null,
                ]);

                Log::info("[Reconciler] Successfully released reservation {$record->reservation_id} for order_ref {$record->order_ref}");
                return true;
            }

            $msg = $releaseResult['message'] ?? 'Release failed';

            // CRITICAL: A confirmed reservation must NEVER be released as compensation!
            if (str_contains(strtolower($msg), 'cannot release a confirmed reservation')) {
                Log::warning("[Reconciler] Reservation {$record->reservation_id} is already confirmed in PostgreSQL. Reconciling to confirmed.");
                $record->update([
                    'workflow_state' => InventoryReconciliation::STATE_CONFIRMED,
                    'last_error'     => 'Reservation was confirmed in inventory service. Reconciled state to confirmed.',
                    'next_retry_at'  => null,
                ]);

                if ($record->order_id) {
                    $order = Order::find($record->order_id);
                    if ($order && $order->status === 'cancelled') {
                        $order->update(['status' => '0']);
                        OrderPart::where('order_id', $order->id)->update(['status' => '0']);
                    }
                }
                return true;
            }

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
