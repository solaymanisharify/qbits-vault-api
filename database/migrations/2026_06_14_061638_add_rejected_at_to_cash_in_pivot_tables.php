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
        Schema::table('cash_in_required_verifiers', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('verified_at');
        });

        Schema::table('cash_in_required_approvers', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('cash_in_required_verifiers', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });

        Schema::table('cash_in_required_approvers', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
