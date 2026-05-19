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
        Schema::create('cash_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cash_in_id')->nullable()->constrained('cash_ins')->onDelete('set null');
            $table->foreignId('vault_id')->nullable()->constrained('vaults')->onDelete('set null');
            $table->string('tran_id')->nullable();
            $table->decimal('cash_out_amount', 16, 2)->default(0);
            $table->decimal('request_amount', 16, 2)->default(0);
            $table->enum('verifier_status', ['pending', 'approved', 'rejected', 'verified'])
                ->default('pending');
            $table->enum('approver_status', ['pending', 'approved', 'completed', 'cancelled'])
                ->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('verifier_status');
            $table->index('approver_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_outs');
    }
};
