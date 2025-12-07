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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('inventory_stocks_id');
            $table->foreign('inventory_stocks_id')->references('id')->on('inventory_stocks')->onDelete('cascade');
            $table->integer('new_quantity');
            $table->integer('previous_quantity');
            $table->string('reason', 255)->nullable();
            $table->date('adjustment_date');
            $table->unsignedInteger('adjusted_by_id');
            $table->foreign('adjusted_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_managements');
    }
};
