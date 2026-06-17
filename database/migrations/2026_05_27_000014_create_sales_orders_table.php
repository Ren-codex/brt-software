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
            $table->unsignedInteger('bank_account_id')->nullable();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
            $table->date('order_date');
            $table->date('transferred_at')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->unsignedInteger('added_by_id');
            $table->foreign('added_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedInteger('updated_by_id')->nullable();
            $table->foreign('updated_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedInteger('approved_by_id')->nullable();
            $table->foreign('approved_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->date('approved_at')->nullable();
            $table->unsignedInteger('transferred_to')->nullable();
            $table->foreign('transferred_to')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedInteger('status_id');
            $table->foreign('status_id')->references('id')->on('list_statuses')->onDelete('cascade');
            $table->unsignedInteger('sub_status_id')->nullable();
            $table->foreign('sub_status_id')->references('id')->on('list_statuses')->onDelete('set null');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->foreign('location_id')->references('id')->on('list_locations')->onDelete('set null');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('employees')->onDelete('set null');
            $table->unsignedInteger('sales_rep_id')->nullable();
            $table->foreign('sales_rep_id')->references('id')->on('employees')->onDelete('set null');
            $table->unsignedInteger('driver_id')->nullable();
            $table->foreign('driver_id')->references('id')->on('employees')->onDelete('set null');
            $table->string('billing_account')->nullable();
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
