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
        Schema::create('sales_order_incentives', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sales_order_id');
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->integer('sold_quantity');
            $table->decimal('product_total_kg', 10, 2);
            $table->unsignedInteger('payroll_id')->nullable();
            $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_incentives');
    }
};
