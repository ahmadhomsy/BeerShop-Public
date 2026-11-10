<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryReconciliationService;
use Illuminate\Console\Command;

class ReconcileInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:reconcile'
        . ' {--daemon : Run continuously as a background daemon}'
        . ' {--interval=5 : Sleep interval in seconds between runs in daemon mode}'
        . ' {--limit=50 : Maximum number of records per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending inventory reservations, ambiguous confirmations, and compensation releases.';

    /**
     * Execute the console command.
     */
    public function handle(InventoryReconciliationService $service): int
    {
        $isDaemon = (bool) $this->option('daemon');
        $interval = max(1, (int) $this->option('interval'));
        $limit = max(1, (int) $this->option('limit'));

        if (!$isDaemon) {
            $this->info("Running single inventory reconciliation batch (limit: {$limit})...");
            $summary = $service->reconcilePending($limit);
            $this->info(sprintf(
                "Completed: %d processed (%d confirmed, %d released, %d retried, %d failed).",
                $summary['processed'],
                $summary['confirmed'],
                $summary['released'],
                $summary['retried'],
                $summary['failed']
            ));
            return Command::SUCCESS;
        }

        $this->info("Starting inventory reconciliation daemon (interval: {$interval}s, limit: {$limit})...");

        // Handle termination signals if available
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () {
                $this->info("Received SIGTERM. Shutting down gracefully...");
                exit(0);
            });
            pcntl_signal(SIGINT, function () {
                $this->info("Received SIGINT. Shutting down gracefully...");
                exit(0);
            });
        }

        while (true) {
            try {
                $summary = $service->reconcilePending($limit);
                if ($summary['processed'] > 0) {
                    $this->info(sprintf(
                        "[%s] Processed %d records: %d confirmed, %d released, %d retried, %d failed.",
                        now()->toDateTimeString(),
                        $summary['processed'],
                        $summary['confirmed'],
                        $summary['released'],
                        $summary['retried'],
                        $summary['failed']
                    ));
                }
            } catch (\Throwable $e) {
                $this->error("[Reconciliation Error] " . $e->getMessage());
            }

            sleep($interval);
        }

        return Command::SUCCESS;
    }
}
