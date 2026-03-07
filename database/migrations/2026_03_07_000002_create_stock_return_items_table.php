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
        Schema::create('stock_return_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');

            $table->unsignedInteger('stock_return_id');
            $table->foreign('stock_return_id')->references('id')->on('stock_returns')->onDelete('cascade');

            $table->unsignedInteger('po_item_id');
            $table->foreign('po_item_id')->references('id')->on('purchase_order_items')->onDelete('cascade');

            $table->integer('quantity');
            $table->integer('returned_quantity')->default(0);
            $table->text('remarks')->nullable();

            $table->unsignedInteger('status_id');
            $table->foreign('status_id')->references('id')->on('list_statuses')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_return_items');
    }
};

