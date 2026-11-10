<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('order_ref', 255)->unique();
            $table->string('idempotency_key', 255)->nullable()->unique();
            $table->string('payload_hash', 64)->nullable()->index();
            $table->string('reservation_id', 64)->nullable()->index();
            $table->string('workflow_state', 32)->index(); // pending_reservation, pending_confirmation, confirmed, pending_release, released, failed
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('max_retries')->default(5);
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->json('items_payload')->nullable();
            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reconciliations');
    }
};
