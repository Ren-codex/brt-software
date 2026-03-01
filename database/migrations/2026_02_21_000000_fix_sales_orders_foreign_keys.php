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
        Schema::table('sales_orders', function (Blueprint $table) {
            // Drop existing foreign keys
            $table->dropForeign(['sales_rep_id']);
            $table->dropForeign(['driver_id']);
            
            // Make columns nullable and update type to match other unsignedInteger columns
            $table->unsignedInteger('sales_rep_id')->nullable()->change();
            $table->unsignedInteger('driver_id')->nullable()->change();
            
            // Re-add foreign keys with nullable support
            $table->foreign('sales_rep_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('driver_id')->references('id')->on('employees')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['sales_rep_id']);
            $table->dropForeign(['driver_id']);
            
            $table->unsignedInteger('sales_rep_id')->change();
            $table->unsignedInteger('driver_id')->change();
            
            $table->foreign('sales_rep_id')->references('id')->on('employees');
            $table->foreign('driver_id')->references('id')->on('employees');
        });
    }
};
