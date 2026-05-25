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
            $table->string('reconcile_tran_id');
            $table->unsignedBigInteger('vault_id')->nullable(false);
            $table->enum('status', [
                'pending',
                'counting',
                'counted',
                'verification',
                'approval',
                'completed',
                'rejected',
                'cancelled',
                'expired',
                'failed',
            ])->default('pending');

            // Lock management
            $table->integer('total_bags')->nullable();
            $table->integer('finished_bag_count')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_until')->nullable();

            // Financial summary
            $table->decimal('expected_balance', 15, 2)->nullable();   // what system expected
            $table->decimal('counted_balance', 15, 2)->nullable();    // final physical count total
            $table->decimal('variance', 15, 2)->nullable();           // counted - expected (can be negative)
            $table->json('variances_bags')->nullable();           // counted - expected (can be negative)
            $table->enum('variance_type', ['shortage', 'surplus', 'matched', 'unknown'])->nullable();

            // People involved
            $table->unsignedBigInteger('started_by')->nullable();     // auditor who started
            $table->unsignedBigInteger('completed_by')->nullable();   // who finally approved/closed

            // Timing
            $table->timestamp('from_date')->nullable();
            $table->time('audit_time')->nullable();
            $table->timestamp('expected_completion_at')->nullable();  // deadline

            // Additional control
            $table->text('notes')->nullable();                        // general remarks
            $table->text('resolution_reason')->nullable();            // why surplus/shortage happened
            $table->boolean('requires_escalation')->default(false);
            $table->enum('verifier_status', ['pending', 'approved', 'rejected', 'verified'])
                ->default('pending');
            $table->enum('approver_status', ['pending', 'approved', 'completed', 'cancelled'])
                ->default('pending');

            // Foreign keys
            $table->foreign('started_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();

            // Important indexes
            $table->index('status');

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
