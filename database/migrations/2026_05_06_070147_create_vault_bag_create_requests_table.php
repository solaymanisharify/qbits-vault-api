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
        Schema::create('vault_bag_create_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bag_creator_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('vault_id')->constrained('vaults')->onDelete('cascade');
            $table->foreignId('bag_id')->nullable()->constrained('vault_bags')->onDelete('cascade');
            $table->dateTime('bag_create_at')->nullable();
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_bag_create_requests');
    }
};
