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
        Schema::create('reconciliation_bag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('reconciliations')->onDelete('cascade');
            $table->foreignId('bag_id')->constrained('vault_bags')->onDelete('cascade');
            $table->json('counted_denominations')->nullable();
            $table->decimal('expected_amount', 10, 2)->nullable();
            $table->decimal('counted_amount', 10, 2)->nullable();
            $table->decimal('difference', 15, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            // Optional: Unique constraint to prevent duplicates
            $table->unique(['reconciliation_id', 'bag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_bag');
    }
};
