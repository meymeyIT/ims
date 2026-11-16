<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The inventory ledger. Append-only: rows are never updated or deleted
 * (the StockMovement model throws if anyone tries).
 *
 * quantity is SIGNED: positive = stock increased, negative = stock decreased.
 *   => SUM(quantity) per product always equals products.current_stock,
 *      which gives us a simple reconciliation check.
 *
 * type (App\Enums\MovementType): in, out, adjustment, return_in, return_out
 *
 * reference_type / reference_id point to the source document line
 * (e.g. a purchase_receipt_item). The unique index guarantees one source line
 * can never produce two movements (no duplicate receiving).
 *
 * A reversal is a NEW row with reversal_of_id pointing to the original.
 * reversal_of_id is unique, so a movement can only be reversed once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number', 30)->unique();   // SM-20261116-000001
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity', 15, 3);
            $table->decimal('previous_stock', 15, 3);
            $table->decimal('new_stock', 15, 3);
            $table->decimal('unit_cost', 15, 4)->nullable();   // cost used for valuation at that moment
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason', 50)->nullable();          // sold, damaged, expired, purchase_receipt, ...
            $table->text('notes')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->dateTime('movement_date');
            $table->timestamps();

            $table->unique(['reference_type', 'reference_id'], 'stock_movements_reference_unique');
            $table->index(['product_id', 'movement_date']);
            $table->index(['type', 'movement_date']);
            $table->index('movement_date');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE stock_movements
                ADD CONSTRAINT chk_stock_movements_qty_non_zero CHECK (quantity <> 0),
                ADD CONSTRAINT chk_stock_movements_stock_non_negative CHECK (previous_stock >= 0 AND new_stock >= 0),
                ADD CONSTRAINT chk_stock_movements_math CHECK (new_stock = previous_stock + quantity)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
