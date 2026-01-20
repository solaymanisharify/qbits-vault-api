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
        Schema::create('cash_in_required_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_in_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // the verifier who must verify
            $table->boolean('approved')->default(false); // has this person verified?
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Prevent duplicates
            $table->unique(['cash_in_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_in_required_approvers');
    }
};
