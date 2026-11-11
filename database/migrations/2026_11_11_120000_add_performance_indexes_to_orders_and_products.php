<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add targeted indexes to eliminate filesorts and full table scans on large tables.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Optimizes customer order history (WHERE user_id = ? ORDER BY created_at DESC)
            $table->index(['user_id', 'created_at'], 'orders_user_id_created_at_index');

            // Optimizes administrator order listing (ORDER BY created_at DESC LIMIT X)
            $table->index(['created_at'], 'orders_created_at_index');

            // Optimizes status filtering
            $table->index(['user_id', 'status'], 'orders_user_id_status_index');
        });

        Schema::table('products', function (Blueprint $table) {
            // Optimizes category and second-category catalog browsing
            $table->index(['category_id', 'second_category_id'], 'products_category_second_category_index');

            // Optimizes category browsing ordered by recency
            $table->index(['category_id', 'created_at'], 'products_category_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_id_created_at_index');
            $table->dropIndex('orders_created_at_index');
            $table->dropIndex('orders_user_id_status_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_second_category_index');
            $table->dropIndex('products_category_created_at_index');
        });
    }
};
