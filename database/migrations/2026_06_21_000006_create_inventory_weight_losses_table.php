<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_weight_losses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('inventory_stock_id');
            $table->decimal('loss_kg', 8, 2);
            $table->string('reason')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedInteger('recorded_by_id')->nullable();
            $table->date('recorded_at');
            $table->timestamps();

            $table->foreign('inventory_stock_id')->references('id')->on('inventory_stocks')->cascadeOnDelete();
            $table->foreign('recorded_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_weight_losses');
    }
};
