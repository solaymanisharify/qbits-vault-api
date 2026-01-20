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
        Schema::create('vault_bags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained('vaults')->onDelete('cascade');
            // Bag identification
            $table->string('barcode'); // e.g., BAG01, BAG123
            $table->string('bag_identifier_barcode'); // e.g., BAG01, BAG123
            $table->string('rack_number')->nullable(); // Which rack the bag is placed in

            // Current status
            $table->decimal('current_amount', 14, 2)->default(0.00); // Current cash inside the bag
            $table->json('denominations')->nullable(); // Current cash inside the bag
            $table->boolean('is_sealed')->default(false); // Is the bag sealed?
            $table->boolean('is_active')->default(true); // Is this bag currently in use?

            // Last cash in/out tracking
            $table->decimal('last_cash_in_amount', 14, 2)->nullable();
            $table->timestamp('last_cash_in_at')->nullable();
            $table->integer('last_cash_in_by')->nullable(); // user_id who did last cash-in
            $table->string('last_cash_in_tran_id')->nullable(); // related transaction/cash-in ID

            $table->string('last_cash_out_tran_id')->nullable();
            $table->decimal('last_cash_out_amount', 14, 2)->nullable();
            $table->timestamp('last_cash_out_at')->nullable();
            $table->integer('last_cash_out_by')->nullable(); // user_id who did last cash-out

            // Attempt/Usage statistics
            $table->integer('total_cash_in_attempts')->default(0); // Total times cash was added
            $table->integer('total_cash_out_attempts')->default(0); // Total times cash was removed
            $table->integer('total_successful_deposits')->default(0);
            $table->integer('total_failed_attempts')->default(0); // e.g., verification failed

            // Optional metadata
            $table->text('notes')->nullable(); // Any remarks about the bag
            $table->json('history')->nullable(); // Optional: store full history log as JSON
            $table->string('status')->nullable(); // Optional: store full history log as JSON
            // Example history entry: [{"action": "cash_in", "amount": 50000, "by": 3, "at": "2025-12-17 10:00:00", "tran_id": "ABC123"}, ...]

            $table->timestamps();

            // Indexes for performance
            $table->index('vault_id');
            $table->index('barcode');
            $table->index('is_active');
            $table->index('last_cash_in_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_bags');
    }
};