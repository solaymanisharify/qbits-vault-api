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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable(); // e.g. ORD-2025-0001
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->decimal('total', 15, 2)->default(0); // total order amount
            $table->decimal('payable_amount', 15, 2)->default(0); // after discount
            $table->decimal('paid_amount', 15, 2)->default(0); // amount paid so far
            $table->decimal('total_cash_to_deposit', 15, 2)->default(0); // amount paid so far
            // $table->decimal('due_amount', 15, 2)->default(0)->virtualAs('payable_amount - paid_amount'); // optional computed column

            // Status fields
            $table->enum('payment_status', ['pending', 'partial', 'paid', 'received', 'refunded'])->default('pending');
            $table->enum('order_status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->enum('status', ['active', 'inactive', 'deleted'])->default('active'); // soft delete alternative

            $table->text('remarks')->nullable();

            // $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');

            $table->timestamps();

            $table->unique(['order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
