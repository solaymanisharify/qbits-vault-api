<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE cash_ins MODIFY approver_status ENUM('pending', 'approved', 'completed', 'cancelled', 'rejected') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cash_ins MODIFY approver_status ENUM('pending', 'approved', 'completed', 'cancelled') DEFAULT 'pending'");
    }
};
