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
        Schema::create('vault_audit_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->onDelete('cascade');
            $table->enum('interval', ['daily', 'weekly', 'bi-weekly', 'monthly', 'quarterly'])->nullable();
            $table->enum('day', ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'])->nullable();
            $table->time('time')->nullable();
            $table->date('last_audit_date')->nullable();
            $table->timestamps('next_audit_date')->nullable();
            $table->integer('failed_audits')->default(0);
            $table->foreignId('config_by')->nullable()->constrained('users')->onDelete('cascade');
            $table->enum('status', ['not_configured', 'configured'])->default('not_configured');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_audit_configs');
    }
};
