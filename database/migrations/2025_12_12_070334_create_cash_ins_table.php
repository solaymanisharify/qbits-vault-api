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
        Schema::create('cash_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vault_id')->nullable()->constrained('vaults')->onDelete('set null');
            $table->string('tran_id')->nullable();
            $table->json('orders')->nullable();
            $table->foreignId('bag_id')->nullable()->constrained('vault_bags')->onDelete('set null');
            $table->decimal('cash_in_amount', 16, 2)->default(0);
            $table->json('denominations')->nullable();
            $table->enum('verifier_status', ['pending', 'approved', 'rejected', 'verified'])
                ->default('pending');
            $table->enum('approver_status', ['pending', 'approved', 'completed', 'cancelled'])
                ->default('pending');
            $table->timestamps();

            // Optional indexes for better performance
            $table->index('bag_id');
            $table->index('verifier_status');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_ins');
    }
};
