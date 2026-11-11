<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\SecondCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SeedPerformanceDatasetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:seed-dataset
                            {--count=200000 : Total number of orders to generate}
                            {--chunk=2500 : Bulk insert chunk size}
                            {--fresh : Reset previously generated synthetic performance orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate deterministic large-scale synthetic dataset (200,000+ orders) for performance benchmarks';

    public const SYNTHETIC_NOTE_TAG = 'PERF_SYNTHETIC_DATA';
    public const BENCHMARK_BUYER_EMAIL = 'benchmark_customer@example.com';
    public const BENCHMARK_BUYER_PASSWORD = 'password123';
    public const ALLOWED_ENVIRONMENTS = ['local', 'testing', 'development', 'staging'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        // 1. Mandatory Production Safeguard: Never execute in production
        $currentEnv = app()->environment();
        if (!in_array($currentEnv, self::ALLOWED_ENVIRONMENTS, true) || app()->isProduction()) {
            $this->error("CRITICAL SAFEGUARD: 'performance:seed-dataset' is strictly forbidden in the '{$currentEnv}' environment.");
            $this->error("Permitted environments: " . implode(', ', self::ALLOWED_ENVIRONMENTS));
            return 1;
        }

        $count = (int) $this->option('count');
        $chunkSize = (int) $this->option('chunk');
        $fresh = (bool) $this->option('fresh');

        $this->info("=== BeerShop Performance Dataset Generator ===");
        $this->info("Environment: {$currentEnv} | Target orders: {$count} (chunk size: {$chunkSize})");

        // 2. Reset synthetic data if requested: delete in strict referential order using parameterized queries
        if ($fresh) {
            $this->warn("Cleaning previously generated synthetic performance orders...");
            
            // Delete child order_details first using ANSI-compliant subqueries
            DB::delete("
                DELETE FROM order_details
                WHERE orderpart_id IN (
                    SELECT op.id FROM order_parts op
                    INNER JOIN orders o ON op.order_id = o.id
                    WHERE o.note = ?
                )
            ", [self::SYNTHETIC_NOTE_TAG]);

            // Delete order_parts next
            DB::delete("
                DELETE FROM order_parts
                WHERE order_id IN (
                    SELECT o.id FROM orders o
                    WHERE o.note = ?
                )
            ", [self::SYNTHETIC_NOTE_TAG]);

            // Delete parent orders
            DB::delete("DELETE FROM orders WHERE note = ?", [self::SYNTHETIC_NOTE_TAG]);

            // Clean synthetic secondary user pool
            DB::delete("DELETE FROM users WHERE email LIKE 'synthetic_user_%@test.com'");

            $this->info("Previous synthetic dataset cleaned successfully without affecting real production records.");
        }

        // 3. Ensure Categories and Products exist
        $productCount = DB::table('products')->count();
        if ($productCount === 0) {
            $this->warn("No products found in database. Running database seed...");
            $this->call('db:seed', ['--force' => true]);
        }

        $productIds = DB::table('products')->pluck('id')->all();
        if (empty($productIds)) {
            $this->error("Cannot seed orders without products. Aborting.");
            return 1;
        }

        // 4. Ensure Benchmark Test Customer exists
        $benchmarkUser = User::firstOrCreate(
            ['email' => self::BENCHMARK_BUYER_EMAIL],
            [
                'name'     => 'Benchmark Customer',
                'password' => Hash::make(self::BENCHMARK_BUYER_PASSWORD),
                'role'     => User::ROLE_USER,
            ]
        );
        $this->info("Benchmark test customer ready: " . self::BENCHMARK_BUYER_EMAIL . " (ID: {$benchmarkUser->id})");

        // 5. Ensure pool of secondary users for distribution
        $userIds = DB::table('users')->where('role', User::ROLE_USER)->pluck('id')->all();
        if (count($userIds) < 10) {
            $newUsers = [];
            for ($u = 1; $u <= 20; $u++) {
                $newUsers[] = [
                    'name'       => "Synthetic User {$u}",
                    'email'      => "synthetic_user_{$u}@test.com",
                    'password'   => Hash::make('password123'),
                    'role'       => User::ROLE_USER,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('users')->insert($newUsers);
            $userIds = DB::table('users')->where('role', User::ROLE_USER)->pluck('id')->all();
        }

        // 6. Deterministic Randomness
        mt_srand(42);

        $startOrderId = (DB::table('orders')->max('id') ?? 0) + 1;
        $startPartId = (DB::table('order_parts')->max('id') ?? 0) + 1;
        $startDetailId = (DB::table('order_details')->max('id') ?? 0) + 1;

        $statuses = ['0', '1', '2', '3']; // 0=pending, 1=confirmed, 2=delivered, 3=cancelled
        $benchmarkOrdersTarget = min(2000, (int) ($count * 0.01));

        $this->info("Starting bulk insertion from order ID {$startOrderId}...");
        $startTime = microtime(true);

        DB::disableQueryLog();

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $currentOrderId = $startOrderId;
        $currentPartId = $startPartId;
        $currentDetailId = $startDetailId;

        $now = Carbon::now();
        $daysInYear = 365;

        for ($inserted = 0; $inserted < $count; $inserted += $chunkSize) {
            $thisBatchSize = min($chunkSize, $count - $inserted);
            $ordersBatch = [];
            $partsBatch = [];
            $detailsBatch = [];

            for ($i = 0; $i < $thisBatchSize; $i++) {
                $orderId = $currentOrderId++;
                $partId = $currentPartId++;
                $detailId = $currentDetailId++;

                if ($orderId - $startOrderId < $benchmarkOrdersTarget) {
                    $userId = $benchmarkUser->id;
                    $userName = $benchmarkUser->name;
                    $userEmail = $benchmarkUser->email;
                } else {
                    $userId = $userIds[mt_rand(0, count($userIds) - 1)];
                    $userName = "Customer {$userId}";
                    $userEmail = "user_{$userId}@example.com";
                }

                $daysAgo = mt_rand(0, $daysInYear);
                $secondsAgo = mt_rand(0, 86400);
                $createdAt = $now->copy()->subDays($daysAgo)->subSeconds($secondsAgo);
                $status = $statuses[mt_rand(0, count($statuses) - 1)];

                $productId = $productIds[mt_rand(0, count($productIds) - 1)];
                $qty = mt_rand(1, 4);
                $unitPrice = mt_rand(15, 250);
                $totalPrice = $qty * $unitPrice;

                $ordersBatch[] = [
                    'id'         => $orderId,
                    'name'       => $userName,
                    'email'      => $userEmail,
                    'address'    => "Street " . mt_rand(1, 999) . ", District " . mt_rand(1, 50),
                    'phone'      => "05" . sprintf('%08d', mt_rand(10000000, 99999999)),
                    'note'       => self::SYNTHETIC_NOTE_TAG,
                    'user_id'    => $userId,
                    'total'      => $totalPrice,
                    'status'     => $status,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                $partsBatch[] = [
                    'id'         => $partId,
                    'seller_id'  => 2,
                    'total'      => $totalPrice,
                    'quantity'   => $qty,
                    'order_id'   => $orderId,
                    'status'     => $status,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                $detailsBatch[] = [
                    'id'           => $detailId,
                    'product_id'   => $productId,
                    'price'        => $unitPrice,
                    'quantity'     => $qty,
                    'orderpart_id' => $partId,
                    'created_at'   => $createdAt,
                    'updated_at'   => $createdAt,
                ];
            }

            // Insert in parent-to-child order to satisfy referential integrity
            DB::transaction(function () use ($ordersBatch, $partsBatch, $detailsBatch) {
                DB::table('orders')->insert($ordersBatch);
                DB::table('order_parts')->insert($partsBatch);
                DB::table('order_details')->insert($detailsBatch);
            });

            unset($ordersBatch, $partsBatch, $detailsBatch);
            if ($inserted % 10000 === 0) {
                gc_collect_cycles();
            }

            $bar->advance($thisBatchSize);
        }

        $bar->finish();
        $this->newLine(2);

        $elapsed = round(microtime(true) - $startTime, 2);
        $totalOrdersInDb = DB::table('orders')->count();
        $benchmarkCustomerOrders = DB::table('orders')->where('user_id', $benchmarkUser->id)->count();

        $this->info("=== Synthetic Seeding Completed in {$elapsed} seconds ===");
        $this->info("Total orders in database: {$totalOrdersInDb}");
        $this->info("Benchmark test customer ({$benchmarkUser->email}) orders: {$benchmarkCustomerOrders}");

        return 0;
    }
}
