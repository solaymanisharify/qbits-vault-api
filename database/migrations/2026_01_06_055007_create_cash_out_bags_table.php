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
        Schema::create('cash_out_bags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_out_id')->constrained('cash_outs')->onDelete('cascade');
            $table->foreignId('bags_id')->nullable()->constrained('vault_bags')->onDelete('set null');
            $table->enum('verifier_status', ['pending', 'approved', 'rejected', 'verified'])
                ->default('pending');
            $table->enum('status', ['pending', 'approved', 'completed', 'cancelled'])
                ->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_out_bags');
    }
};
