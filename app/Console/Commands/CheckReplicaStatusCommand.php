<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckReplicaStatusCommand extends Command
{
    protected $signature = 'replica:status
                            {--max-lag=30 : Maximum allowed replication lag in seconds}';

    protected $description = 'Inspect MySQL read replica health, replication threads, and replication lag';

    public function handle(): int
    {
        $this->info("=== MySQL Read Replica Health Check ===");
        $maxLag = (int) $this->option('max-lag');

        try {
            $readOnlyRes = DB::connection('mysql_replica')->select("SELECT @@global.read_only as ro, @@global.super_read_only as sro;");
            $isReadOnly = (int) ($readOnlyRes[0]->ro ?? 0);
            $isSuperReadOnly = (int) ($readOnlyRes[0]->sro ?? 0);

            $this->line("Replica Read-Only Mode: " . ($isReadOnly ? "YES (Enforced)" : "NO (UNPROTECTED)"));
            $this->line("Replica Super-Read-Only: " . ($isSuperReadOnly ? "YES (Enforced)" : "NO (UNPROTECTED)"));

            $status = DB::connection('mysql_replica')->select("SHOW REPLICA STATUS;");
            if (empty($status)) {
                $this->error("CRITICAL: No active replication configured on replica.");
                return 1;
            }

            $row = (array) $status[0];
            $sourceHost = $row['Source_Host'] ?? 'Unknown';
            $ioRunning = $row['Replica_IO_Running'] ?? 'No';
            $sqlRunning = $row['Replica_SQL_Running'] ?? 'No';
            $rawLag = $row['Seconds_Behind_Source'] ?? null;
            $lagText = ($rawLag === null) ? 'NULL (Disconnected/Broken)' : "{$rawLag}s";

            $this->table(
                ['Metric', 'Status'],
                [
                    ['Source Host', $sourceHost],
                    ['Replica IO Running', $ioRunning],
                    ['Replica SQL Running', $sqlRunning],
                    ['Replication Lag', $lagText],
                    ['Read-Only Mode', $isReadOnly ? 'Active (1)' : 'Inactive (0)'],
                    ['Super-Read-Only Mode', $isSuperReadOnly ? 'Active (1)' : 'Inactive (0)'],
                ]
            );

            $isHealthy = true;

            if ($ioRunning !== 'Yes' || $sqlRunning !== 'Yes') {
                $this->error("Replication threads are NOT healthy: IO={$ioRunning}, SQL={$sqlRunning}");
                $isHealthy = false;
            }

            if (!empty($row['Last_IO_Error'])) {
                $this->error("Last IO Error: " . $row['Last_IO_Error']);
                $isHealthy = false;
            }

            if (!empty($row['Last_SQL_Error'])) {
                $this->error("Last SQL Error: " . $row['Last_SQL_Error']);
                $isHealthy = false;
            }

            if ($rawLag === null) {
                $this->error("Replication lag is NULL (replication thread not actively keeping up with source).");
                $isHealthy = false;
            } elseif ($rawLag > $maxLag) {
                $this->error("Replication lag ({$rawLag}s) exceeds maximum allowed threshold ({$maxLag}s).");
                $isHealthy = false;
            }

            if (!$isReadOnly || !$isSuperReadOnly) {
                $this->warn("Warning: Replica is not enforcing read_only or super_read_only.");
            }

            if ($isHealthy) {
                $this->info("Replication status is HEALTHY (IO=Yes, SQL=Yes, Lag={$lagText}).");
                return 0;
            }

            return 1;
        } catch (\Throwable $e) {
            $this->error("Failed to connect to replica database: " . $e->getMessage());
            return 1;
        }
    }
}
