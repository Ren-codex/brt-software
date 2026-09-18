<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods the customer sent back at the door, before any money changed hands.
 *
 * Kept apart from sales_return_history on purpose: a return reverses a payment
 * and refunds it, while a refusal simply resizes an order nobody has paid for.
 * A report asking "why did stock come back" can read both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_delivery_refusals', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('sales_order_id');
            $table->unsignedInteger('sales_order_item_id')->nullable();
            $table->unsignedInteger('product_id');
            $table->integer('ordered_quantity');
            $table->integer('accepted_quantity');
            $table->integer('refused_quantity');
            $table->string('batch_code')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedInteger('recorded_by_id')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('recorded_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_delivery_refusals');
    }
};
