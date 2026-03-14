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
         Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Who did it
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();   // denormalized for history safety

            // What was affected
            $table->string('subject_type')->nullable(); // e.g. App\Models\VaultBag
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable(); // e.g. "SM001" (human readable)

            // The action
            $table->string('event');                   // created | updated | deleted | cash_in | cash_out | custom
            $table->string('module')->nullable();      // e.g. vault, bag, transaction, user
            $table->string('description')->nullable(); // human-readable sentence

            // Diff / metadata
            $table->json('old_values')->nullable();    // snapshot before change
            $table->json('new_values')->nullable();    // snapshot after change
            $table->json('meta')->nullable();          // any extra context

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // Indexes for fast filtering
            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
            $table->index('event');
            $table->index('module');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
