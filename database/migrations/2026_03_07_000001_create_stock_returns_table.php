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
        Schema::create('stock_returns', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');

            $table->unsignedInteger('po_id')->index();
            $table->foreign('po_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->text('reason');

            $table->unsignedInteger('status_id')->index();
            $table->foreign('status_id')->references('id')->on('list_statuses')->onDelete('cascade');

            $table->unsignedInteger('created_by_id')->index();
            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('approved_by_id')->nullable()->index();
            $table->foreign('approved_by_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_returns');
    }
};
