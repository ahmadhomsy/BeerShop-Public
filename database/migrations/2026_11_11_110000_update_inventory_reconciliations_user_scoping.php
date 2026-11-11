<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_reconciliations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            $table->timestamp('locked_until')->nullable()->after('next_retry_at')->index();
            $table->string('locked_by', 64)->nullable()->after('locked_until');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        // Safely backfill user_id for existing records from orders table
        if (Schema::hasTable('orders')) {
            try {
                DB::table('inventory_reconciliations')
                    ->join('orders', 'inventory_reconciliations.order_id', '=', 'orders.id')
                    ->whereNotNull('inventory_reconciliations.order_id')
                    ->update([
                        'inventory_reconciliations.user_id' => DB::raw('orders.user_id')
                    ]);
            } catch (\Throwable $e) {
                // Ignore backfill error on empty tables or unsupported engines
            }
        }

        // Drop old global unique index on idempotency_key and create user-scoped composite index
        Schema::table('inventory_reconciliations', function (Blueprint $table) {
            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                // SQLite index drop by name if exists
                try {
                    $table->dropUnique('inventory_reconciliations_idempotency_key_unique');
                } catch (\Throwable $ignored) {
                }
            } else {
                $table->dropUnique('inventory_reconciliations_idempotency_key_unique');
            }

            $table->unique(['user_id', 'idempotency_key'], 'inv_rec_user_idempotency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_reconciliations', function (Blueprint $table) {
            $table->dropUnique('inv_rec_user_idempotency_unique');
            $table->unique('idempotency_key', 'inventory_reconciliations_idempotency_key_unique');
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'locked_until', 'locked_by']);
        });
    }
};
