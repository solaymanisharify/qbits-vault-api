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
            $table->string('vault_id');
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('balance')->default(0);
            $table->string('total_racks')->nullable();
            $table->json('total_bags')->nullable();
            $table->json('last_cash_in')->nullable();
            $table->json('last_cash_out')->nullable();
            $table->json('verifiers')->nullable();
            $table->json('status')->nullable();
            $table->timestamps();
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
