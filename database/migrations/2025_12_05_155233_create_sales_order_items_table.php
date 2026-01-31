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
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('quantity');
            $table->decimal('price', 15, 2);
            $table->decimal('discount_per_unit', 15, 2);
            $table->unsignedInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unsignedInteger('sales_order_id');
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            $table->unsignedInteger('sales_rep_id');
            $table->foreign('sales_rep_id')->references('id')->on('employees');
            $table->unsignedInteger('driver_id');
            $table->foreign('driver_id')->references('id')->on('employees');
            $table->string('batch_code');
            $table->foreign('batch_code')->references('batch_code')->on('inventory_stocks')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
