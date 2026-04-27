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
        Schema::create('vaults', function (Blueprint $table) {
            $table->id();
            $table->string('vault_code')->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('bag_balance_limit', 15, 2)->default(200000.00);
            $table->string('total_racks')->nullable();
            $table->json('total_bags')->nullable();
            $table->json('last_cash_in')->nullable();
            $table->json('last_cash_out')->nullable();
            $table->json('verifiers')->nullable();
            $table->json('status')->nullable();
            $table->timestamps();

            $table->index(['name', 'vault_code', 'address'], 'idx_vaults_list');
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaults');
    }
};
