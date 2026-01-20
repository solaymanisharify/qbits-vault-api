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
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();

            // What is being reconciled?
            $table->string('reconcile_tran_id');
            $table->enum('scope_type', ['vault', 'rack', 'bag'])->nullable(false);
            $table->unsignedBigInteger('scope_id')->nullable(false); // vault_id / rack_id / bag_id

            // Status management - very important for locking
            $table->enum('status', [
                'pending',          // just created, waiting for counter
                'in_progress',      // counting in progress
                'verification',     // waiting for verifiers
                'approval',         // waiting for final approver
                'completed',        // successfully finished
                'rejected',         // rejected - needs to be restarted
                'cancelled',        // manually cancelled
                'failed',           // system/technical error
            ])->default('pending');

            // Lock management
            $table->boolean('is_locked')->default(false);           // whether this reconcile is currently locking the scope
            $table->timestamp('locked_until')->nullable();         // optional timeout for lock

            // Financial summary
            $table->decimal('expected_balance', 15, 2)->nullable();   // what system expected
            $table->decimal('counted_balance', 15, 2)->nullable();    // final physical count total
            $table->decimal('variance', 15, 2)->nullable();           // counted - expected (can be negative)
            $table->enum('variance_type', ['shortage', 'surplus', 'matched', 'unknown'])->nullable();

            // People involved
            $table->unsignedBigInteger('started_by')->nullable();     // auditor who started
            $table->unsignedBigInteger('completed_by')->nullable();   // who finally approved/closed

            // Timing
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expected_completion_at')->nullable();  // deadline

            // Additional control
            $table->text('notes')->nullable();                        // general remarks
            $table->text('resolution_reason')->nullable();            // why surplus/shortage happened
            $table->boolean('requires_escalation')->default(false);   // big variance → needs higher attention

            // Foreign keys
            $table->foreign('started_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();

            // Important indexes
            $table->index(['scope_type', 'scope_id']);
            $table->index('status');
            $table->index('started_at');

            $table->timestamps();
            $table->softDeletes();   // ← very useful for reconciliation history
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
    }
};
