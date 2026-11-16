<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counters for document numbers (PO-20261116-0001, GRN-..., SM-...).
 * One row per prefix per day; the row is locked while a number is taken,
 * so two users can never get the same number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name', 50)->primary();     // e.g. "PO-20261116"
            $table->unsignedBigInteger('value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
