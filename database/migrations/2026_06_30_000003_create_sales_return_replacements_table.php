<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_replacements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('sales_order_id');
            $table->unsignedInteger('product_id');
            $table->integer('quantity');
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->date('replaced_at');
            $table->unsignedInteger('replaced_by_id')->nullable();
            $table->timestamps();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('replaced_by_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_replacements');
    }
};
