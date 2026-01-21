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
        Schema::create('reconcile_required_verifiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reconcile_id');

            // 2. Then add the foreign key constraint
            $table->foreign('reconcile_id')
                ->references('id')
                ->on('reconciliations')
                ->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconcile_required_verifiers');
    }
};
