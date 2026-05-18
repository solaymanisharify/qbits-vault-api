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
        Schema::create('custodian_cash_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custodian_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vault_id')->nullable()->constrained('vaults')->onDelete('cascade');
            $table->foreignId('cash_out_id')->nullable()->constrained('cash_outs')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'verified'])->default('pending');
            $table->date('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custodian_cash_histories');
    }
};
