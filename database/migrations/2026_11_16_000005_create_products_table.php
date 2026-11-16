<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products.
 *
 * - current_stock is NEVER edited directly by forms. Only StockService changes it,
 *   and every change writes a stock_movements row (the ledger).
 * - average_cost holds the weighted-average unit cost, recalculated on every
 *   stock-in. Inventory value = current_stock * average_cost.
 * - Quantities use DECIMAL(15,3) so units like kg / L can hold fractions.
 * - Soft deletes = "archive": history and reports keep working, and the SKU
 *   stays reserved forever (unique index includes archived rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('name', 150)->index();
            $table->string('sku', 50)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('average_cost', 15, 4)->default(0);
            $table->decimal('current_stock', 15, 3)->default(0);
            $table->decimal('minimum_stock', 15, 3)->default(0);
            $table->timestamp('low_stock_alerted_at')->nullable(); // prevents duplicate low-stock alerts
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Last line of defence if application code ever has a bug.
        // CHECK constraints are enforced by MySQL 8.0.16+ and MariaDB 10.2+.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products
                ADD CONSTRAINT chk_products_stock_non_negative   CHECK (current_stock >= 0),
                ADD CONSTRAINT chk_products_min_stock_non_negative CHECK (minimum_stock >= 0),
                ADD CONSTRAINT chk_products_prices_non_negative  CHECK (purchase_price >= 0 AND selling_price >= 0 AND average_cost >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
