<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckReplicaStatusCommand extends Command
{
    protected $signature = 'replica:status';
    protected $description = 'Inspect MySQL read replica health, replication threads, and replication lag';

    public function handle(): int
    {
        $this->info("=== MySQL Read Replica Health Check ===");

        try {
            $readOnlyRes = DB::connection('mysql_replica')->select("SELECT @@global.read_only as ro, @@global.super_read_only as sro;");
            $isReadOnly = $readOnlyRes[0]->ro ?? 0;
            $isSuperReadOnly = $readOnlyRes[0]->sro ?? 0;
            $this->line("Replica Read-Only Mode: " . ($isReadOnly ? "YES (Enforced)" : "NO"));
            $this->line("Replica Super-Read-Only: " . ($isSuperReadOnly ? "YES (Enforced)" : "NO"));

            $status = DB::connection('mysql_replica')->select("SHOW REPLICA STATUS;");
            if (empty($status)) {
                $this->warn("No active replication configured on replica.");
                return 1;
            }

            $row = (array) $status[0];
            $sourceHost = $row['Source_Host'] ?? 'Unknown';
            $ioRunning = $row['Replica_IO_Running'] ?? 'No';
            $sqlRunning = $row['Replica_SQL_Running'] ?? 'No';
            $lag = $row['Seconds_Behind_Source'] ?? 'Unknown';

            $this->table(
                ['Metric', 'Status'],
                [
                    ['Source Host', $sourceHost],
                    ['Replica IO Running', $ioRunning],
                    ['Replica SQL Running', $sqlRunning],
                    ['Replication Lag (seconds)', $lag],
                    ['Read-Only Mode', $isReadOnly ? 'Active' : 'Inactive'],
                ]
            );

            if ($ioRunning === 'Yes' && $sqlRunning === 'Yes') {
                $this->info("Replication status is HEALTHY (0 lag).");
                return 0;
            }

            $this->error("Replication threads are not healthy: IO={$ioRunning}, SQL={$sqlRunning}");
            if (!empty($row['Last_IO_Error'])) {
                $this->error("Last IO Error: " . $row['Last_IO_Error']);
            }
            if (!empty($row['Last_SQL_Error'])) {
                $this->error("Last SQL Error: " . $row['Last_SQL_Error']);
            }

            return 1;
        } catch (\Throwable $e) {
            $this->error("Failed to connect to replica database: " . $e->getMessage());
            return 1;
        }
    }
}
