<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase orders and their lines.
 *
 * Creating / approving a purchase does NOT touch stock. Stock only increases
 * when goods are received (see purchase_receipts).
 *
 * status values (App\Enums\PurchaseStatus):
 *   draft, pending, approved, partially_received, received, cancelled
 * Stored as a short string rather than a DB ENUM so new statuses don't need ALTER TABLE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 30)->unique();   // PO-20261116-0001
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->date('purchase_date')->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete(); // only drafts may be deleted
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_ordered', 15, 3);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->unique(['purchase_id', 'product_id']);   // one line per product per PO
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE purchases
                ADD CONSTRAINT chk_purchases_amounts_non_negative
                CHECK (subtotal >= 0 AND discount >= 0 AND tax >= 0 AND total_amount >= 0)');

            // Database-level guarantee: never receive more than was ordered.
            DB::statement('ALTER TABLE purchase_items
                ADD CONSTRAINT chk_purchase_items_qty_ordered_positive CHECK (quantity_ordered > 0),
                ADD CONSTRAINT chk_purchase_items_qty_received_range  CHECK (quantity_received >= 0 AND quantity_received <= quantity_ordered),
                ADD CONSTRAINT chk_purchase_items_unit_cost_non_negative CHECK (unit_cost >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
