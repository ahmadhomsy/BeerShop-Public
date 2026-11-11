<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use Symfony\Component\Process\Process;

class BootstrapReplicaCommand extends Command
{
    protected $signature = 'replica:bootstrap
                            {--timeout=60 : Maximum seconds to wait for replication to become healthy}
                            {--force : Force re-initialization even if replica is already active}';

    protected $description = 'Safely bootstrap and verify MySQL read replica using GTID replication';

    public function handle(): int
    {
        $this->info("=== MySQL Read Replica Bootstrap & Verification ===");

        $mainHost = env('DB_HOST', 'db_main');
        $mainPort = (int) env('DB_PORT', 3306);
        $replicaHost = env('DB_REPLICA_HOST', env('DB_READ_HOST', 'db_replica'));
        $replicaPort = (int) env('DB_REPLICA_PORT', env('DB_PORT', 3306));
        $database = env('DB_DATABASE', 'aboodm4_beershop');
        $rootPass = env('DB_ROOT_PASSWORD', 'root_password');

        $replUser = env('MYSQL_REPLICATION_USER', 'repl_user');
        $replPass = env('MYSQL_REPLICATION_PASSWORD', 'repl_password');

        $timeout = (int) $this->option('timeout');
        $force = (bool) $this->option('force');

        // 1. Verify required client binaries
        $this->info("1. Verifying database client binaries...");
        if (!$this->verifyClientBinaries()) {
            $this->error("Missing required client binaries 'mysqldump' or 'mysql'. Ensure default-mysql-client is installed.");
            return 1;
        }
        $this->info("   Client binaries 'mysqldump' and 'mysql' verified.");

        // 2. Idempotency Check: inspect if replica is already active and healthy
        $this->info("2. Checking existing replication status on {$replicaHost}...");
        $replicaPdo = $this->createPdo($replicaHost, $replicaPort, null, 'root', $rootPass);
        if (!$replicaPdo) {
            $this->error("Failed to connect to replica database at {$replicaHost}:{$replicaPort} as root.");
            return 1;
        }

        if (!$force && $this->isReplicationAlreadyHealthy($replicaPdo)) {
            $this->info("   Replica is already active, synchronized, and healthy. Skipping re-initialization.");
            return 0;
        }

        // 3. Connect to Primary as root to provision dedicated replication account
        $this->info("3. Provisioning replication user '{$replUser}' on primary ({$mainHost})...");
        $primaryPdo = $this->createPdo($mainHost, $mainPort, null, 'root', $rootPass);
        if (!$primaryPdo) {
            $this->error("Failed to connect to primary database at {$mainHost}:{$mainPort} as root.");
            return 1;
        }

        $appUser = env('DB_USERNAME', 'beershop_user');
        try {
            $stmt = $primaryPdo->prepare("CREATE USER IF NOT EXISTS :user@'%' IDENTIFIED WITH mysql_native_password BY :pass;");
            $stmt->execute(['user' => $replUser, 'pass' => $replPass]);

            $stmt = $primaryPdo->prepare("ALTER USER :user@'%' IDENTIFIED WITH mysql_native_password BY :pass;");
            $stmt->execute(['user' => $replUser, 'pass' => $replPass]);

            $primaryPdo->exec("GRANT REPLICATION SLAVE, REPLICATION CLIENT, RELOAD ON *.* TO '{$replUser}'@'%';");
            $primaryPdo->exec("GRANT REPLICATION CLIENT ON *.* TO '{$appUser}'@'%';");
            $primaryPdo->exec("FLUSH PRIVILEGES;");
            $this->info("   Dedicated replication account verified on primary.");
        } catch (\Throwable $e) {
            $this->error("   Failed to provision replication account: " . $e->getMessage());
            return 1;
        }

        // 4. Prepare Replica: temporarily unlock to import initial dump
        $this->info("4. Preparing replica for snapshot synchronization...");
        try {
            $replicaPdo->exec("SET GLOBAL super_read_only = OFF;");
            $replicaPdo->exec("SET GLOBAL read_only = OFF;");
            $replicaPdo->exec("STOP REPLICA;");
            $replicaPdo->exec("RESET REPLICA ALL;");
            $replicaPdo->exec("GRANT REPLICATION CLIENT ON *.* TO '{$appUser}'@'%';");
            $replicaPdo->exec("FLUSH PRIVILEGES;");
            $this->info("   Replica unlocked and reset for clean snapshot.");
        } catch (\Throwable $e) {
            $this->error("   Failed to prepare replica: " . $e->getMessage());
            return 1;
        }

        // 5. Transfer consistent GTID snapshot from Primary to Replica
        $this->info("5. Transferring consistent GTID snapshot from {$mainHost} to {$replicaHost}...");
        $pipeScript = 'mysqldump -h "$MAIN_HOST" -P "$MAIN_PORT" -u root --single-transaction --set-gtid-purged=ON --databases "$DB_NAME" | mysql -h "$REPLICA_HOST" -P "$REPLICA_PORT" -u root "$DB_NAME"';
        
        $process = Process::fromShellCommandline($pipeScript, null, [
            'MYSQL_PWD'     => $rootPass,
            'MAIN_HOST'     => $mainHost,
            'MAIN_PORT'     => (string) $mainPort,
            'REPLICA_HOST'  => $replicaHost,
            'REPLICA_PORT'  => (string) $replicaPort,
            'DB_NAME'       => $database,
        ]);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error("   Snapshot transfer failed: " . $process->getErrorOutput());
            return 1;
        }
        $this->info("   Snapshot transferred and GTID state synchronized successfully.");

        // 6. Configure GTID Auto-Positioning Replication Source on Replica
        $this->info("6. Configuring GTID replication source on replica...");
        try {
            $changeSourceSql = "
                CHANGE REPLICATION SOURCE TO
                    SOURCE_HOST = '{$mainHost}',
                    SOURCE_PORT = {$mainPort},
                    SOURCE_USER = '{$replUser}',
                    SOURCE_PASSWORD = '{$replPass}',
                    SOURCE_AUTO_POSITION = 1,
                    GET_SOURCE_PUBLIC_KEY = 1;
            ";
            $replicaPdo->exec($changeSourceSql);
            $replicaPdo->exec("START REPLICA;");

            // Enforce read_only and super_read_only on replica
            $replicaPdo->exec("SET GLOBAL read_only = ON;");
            $replicaPdo->exec("SET GLOBAL super_read_only = ON;");
            $this->info("   Replication started. Enforced read_only=ON and super_read_only=ON.");
        } catch (\Throwable $e) {
            $this->error("   Failed to start GTID replication: " . $e->getMessage());
            return 1;
        }

        // 7. Verification Loop with timeout
        $this->info("7. Verifying replication health and thread synchronization (timeout: {$timeout}s)...");
        $startTime = time();
        while (time() - $startTime < $timeout) {
            $status = $this->fetchReplicaStatus($replicaPdo);
            if ($status) {
                $io = $status['Replica_IO_Running'] ?? 'No';
                $sql = $status['Replica_SQL_Running'] ?? 'No';
                $lag = $status['Seconds_Behind_Source'] ?? 'Unknown';

                if ($io === 'Yes' && $sql === 'Yes') {
                    $this->info("   - Replica IO Running:  {$io}");
                    $this->info("   - Replica SQL Running: {$sql}");
                    $this->info("   - Replication Lag (s): {$lag}");
                    $this->info("=== MySQL Read Replica Successfully Operational! ===");
                    return 0;
                }
            }
            sleep(1);
        }

        // Timeout elapsed without healthy status
        $finalStatus = $this->fetchReplicaStatus($replicaPdo);
        $io = $finalStatus['Replica_IO_Running'] ?? 'No';
        $sql = $finalStatus['Replica_SQL_Running'] ?? 'No';
        $this->error("Replication failed to achieve healthy state within {$timeout} seconds.");
        $this->error("Final State: IO={$io}, SQL={$sql}");
        if (!empty($finalStatus['Last_IO_Error'])) {
            $this->error("Last IO Error: " . $finalStatus['Last_IO_Error']);
        }
        if (!empty($finalStatus['Last_SQL_Error'])) {
            $this->error("Last SQL Error: " . $finalStatus['Last_SQL_Error']);
        }

        return 1;
    }

    protected function verifyClientBinaries(): bool
    {
        $dump = Process::fromShellCommandline('command -v mysqldump || which mysqldump');
        $dump->run();
        $mysql = Process::fromShellCommandline('command -v mysql || which mysql');
        $mysql->run();

        return $dump->isSuccessful() && $mysql->isSuccessful();
    }

    protected function createPdo(string $host, int $port, ?string $database, string $user, string $password): ?PDO
    {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        if ($database) {
            $dsn .= ";dbname={$database}";
        }

        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 5,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function fetchReplicaStatus(PDO $pdo): ?array
    {
        try {
            $stmt = $pdo->query("SHOW REPLICA STATUS;");
            $rows = $stmt->fetchAll();
            return $rows[0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function isReplicationAlreadyHealthy(PDO $pdo): bool
    {
        $status = $this->fetchReplicaStatus($pdo);
        if (!$status) {
            return false;
        }

        $io = $status['Replica_IO_Running'] ?? 'No';
        $sql = $status['Replica_SQL_Running'] ?? 'No';

        return $io === 'Yes' && $sql === 'Yes';
    }
}
