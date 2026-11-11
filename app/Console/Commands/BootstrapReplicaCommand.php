<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class BootstrapReplicaCommand extends Command
{
    protected $signature = 'replica:bootstrap
                            {--timeout=60 : Maximum seconds to wait for replication startup}';

    protected $description = 'Provision replication user, snapshot database, and configure MySQL read replica';

    public function handle(): int
    {
        $this->info("=== Bootstrapping MySQL Read Replica ===");

        $mainHost = env('DB_HOST', 'db_main');
        $replicaHost = env('DB_READ_HOST', 'db_replica');
        $database = env('DB_DATABASE', 'aboodm4_beershop');
        $rootPass = env('DB_ROOT_PASSWORD', 'root_password');

        // 1. Provision replication user on Primary (db_main)
        $this->info("1. Provisioning replication user on primary ({$mainHost})...");
        try {
            DB::connection('mysql')->statement("CREATE USER IF NOT EXISTS 'repl_user'@'%' IDENTIFIED WITH mysql_native_password BY 'repl_password';");
            DB::connection('mysql')->statement("GRANT REPLICATION SLAVE, REPLICATION CLIENT, RELOAD ON *.* TO 'repl_user'@'%';");
            DB::connection('mysql')->statement("FLUSH PRIVILEGES;");
            $this->info("   Replication user 'repl_user' verified on primary.");
        } catch (\Throwable $e) {
            $this->error("   Failed to provision replication user on primary: " . $e->getMessage());
            return 1;
        }

        // 2. Snapshot data from primary to replica
        $this->info("2. Transferring consistent snapshot from {$mainHost} to {$replicaHost}...");
        $dumpCmd = "mysqldump -h {$mainHost} -u root -p{$rootPass} --single-transaction --set-gtid-purged=OFF {$database} | mysql -h {$replicaHost} -u root -p{$rootPass} {$database}";
        $process = Process::fromShellCommandline($dumpCmd);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->warn("   Notice on dump transfer: " . $process->getErrorOutput());
        } else {
            $this->info("   Snapshot transferred successfully.");
        }

        // 3. Configure replication on Replica
        $this->info("3. Configuring replication source on {$replicaHost}...");
        try {
            DB::connection('mysql_replica')->statement("STOP REPLICA;");
            DB::connection('mysql_replica')->statement("
                CHANGE REPLICATION SOURCE TO
                    SOURCE_HOST='{$mainHost}',
                    SOURCE_PORT=3306,
                    SOURCE_USER='repl_user',
                    SOURCE_PASSWORD='repl_password',
                    SOURCE_AUTO_POSITION=0,
                    GET_SOURCE_PUBLIC_KEY=1;
            ");
            DB::connection('mysql_replica')->statement("START REPLICA;");
            $this->info("   Replica started.");
        } catch (\Throwable $e) {
            $this->error("   Failed to start replica: " . $e->getMessage());
            return 1;
        }

        // 4. Verify replication status
        $this->info("4. Verifying replication status...");
        sleep(2);

        $status = DB::connection('mysql_replica')->select("SHOW REPLICA STATUS;");
        if (empty($status)) {
            $this->error("   No replica status returned.");
            return 1;
        }

        $row = (array) $status[0];
        $ioRunning = $row['Replica_IO_Running'] ?? 'No';
        $sqlRunning = $row['Replica_SQL_Running'] ?? 'No';
        $lag = $row['Seconds_Behind_Source'] ?? 'Unknown';

        $this->line("   - Replica IO Running:  {$ioRunning}");
        $this->line("   - Replica SQL Running: {$sqlRunning}");
        $this->line("   - Replication Lag (s): {$lag}");

        if ($ioRunning === 'Yes' && $sqlRunning === 'Yes') {
            $this->info("=== MySQL Read Replica Successfully Operational! ===");
            return 0;
        }

        $this->warn("   Replica is running with warnings: IO={$ioRunning}, SQL={$sqlRunning}");
        if (!empty($row['Last_IO_Error'])) {
            $this->warn("   Last IO Error: " . $row['Last_IO_Error']);
        }
        if (!empty($row['Last_SQL_Error'])) {
            $this->warn("   Last SQL Error: " . $row['Last_SQL_Error']);
        }

        return 0;
    }
}
