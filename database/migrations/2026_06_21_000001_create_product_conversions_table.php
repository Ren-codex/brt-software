<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_conversions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('source_stock_id');
            $table->foreign('source_stock_id')->references('id')->on('inventory_stocks')->onDelete('cascade');
            $table->unsignedInteger('output_stock_id')->nullable();
            $table->foreign('output_stock_id')->references('id')->on('inventory_stocks')->onDelete('set null');
            $table->integer('source_qty_used');
            $table->decimal('conversion_ratio', 8, 4);
            $table->integer('output_quantity');
            $table->text('reason')->nullable();
            $table->unsignedInteger('converted_by_id')->nullable();
            $table->foreign('converted_by_id')->references('id')->on('users')->onDelete('set null');
            $table->date('conversion_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_conversions');
    }
};
