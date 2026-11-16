<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra indexes for the queries the dashboard and reports run most often.
 * (Foreign keys, unique columns and the main filters were indexed in Phase 2.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Dashboard "purchases this month" / purchase report: WHERE status IN (...) AND purchase_date BETWEEN ...
            $table->index(['status', 'purchase_date'], 'purchases_status_date_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            // Stock out report filtered by reason (sold, damaged, ...)
            $table->index(['reason', 'movement_date'], 'stock_movements_reason_date_index');
            // History filtered by user
            $table->index(['user_id', 'movement_date'], 'stock_movements_user_date_index');
        });

        Schema::table('products', function (Blueprint $table) {
            // Lists of active, non-archived products by category (product report, purchase form)
            $table->index(['category_id', 'is_active', 'deleted_at'], 'products_category_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropIndex('products_category_active_index'));
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_user_date_index');
            $table->dropIndex('stock_movements_reason_date_index');
        });
        Schema::table('purchases', fn (Blueprint $table) => $table->dropIndex('purchases_status_date_index'));
    }
};
