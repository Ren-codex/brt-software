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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->date('due_date')->nullable();
            $table->string('so_number',50)->unique();
            $table->string('payment_mode',50);
            $table->date('order_date');
            $table->date('transferred_at')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->unsignedInteger('added_by_id');
            $table->foreign('added_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedInteger('approved_by_id')->nullable();
            $table->foreign('approved_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->date('approved_at')->nullable();
            $table->unsignedInteger('transferred_to')->nullable();
            $table->foreign('transferred_to')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedInteger('status_id');
            $table->foreign('status_id')->references('id')->on('list_statuses')->onDelete('cascade');
            $table->unsignedInteger('sub_status_id')->nullable();
            $table->foreign('sub_status_id')->references('id')->on('list_statuses')->onDelete('set null');
            $table->unsignedInteger('sales_rep_id');
            $table->foreign('sales_rep_id')->references('id')->on('employees');
            $table->unsignedInteger('driver_id');
            $table->foreign('driver_id')->references('id')->on('employees');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
