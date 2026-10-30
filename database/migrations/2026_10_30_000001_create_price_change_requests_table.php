<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A proposed change to a batch's retail/wholesale price, waiting on an approver.
 *
 * The rep who wants a new price records it here; nothing on the product moves
 * until someone with approver access confirms it. Keeping the previous prices
 * alongside the proposed ones lets the approver see exactly what changes, and
 * leaves a record even after the stock's own prices move on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_change_requests', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('inventory_stocks_id');
            $table->foreign('inventory_stocks_id')->references('id')->on('inventory_stocks')->onDelete('cascade');

            $table->unsignedInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();

            $table->decimal('retail_price', 15, 2);
            $table->decimal('wholesale_price', 15, 2);
            $table->decimal('previous_retail_price', 15, 2)->nullable();
            $table->decimal('previous_wholesale_price', 15, 2)->nullable();

            $table->text('reason')->nullable();

            $table->unsignedInteger('status_id');
            $table->foreign('status_id')->references('id')->on('list_statuses')->onDelete('cascade');

            $table->unsignedInteger('requested_by_id');
            $table->foreign('requested_by_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('approved_by_id')->nullable();
            $table->foreign('approved_by_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_change_requests');
    }
};
