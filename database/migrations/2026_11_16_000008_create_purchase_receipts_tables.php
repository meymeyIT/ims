<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Goods receiving (supports partial receiving).
 *
 * Each "Receive products" submission creates ONE purchase_receipt with one
 * line per product received. Each receipt line creates exactly ONE stock
 * movement (enforced by a unique index on stock_movements.reference_*).
 *
 * receipt_token is a one-time UUID put in the receive form. Its unique index
 * stops a double-click or browser refresh from receiving the same goods twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();    // GRN-20261116-0001
            $table->uuid('receipt_token')->unique();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('received_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();

            $table->unique(['purchase_receipt_id', 'purchase_item_id'], 'receipt_items_receipt_item_unique');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE purchase_receipt_items
                ADD CONSTRAINT chk_receipt_items_qty_positive CHECK (quantity > 0),
                ADD CONSTRAINT chk_receipt_items_cost_non_negative CHECK (unit_cost >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
    }
};