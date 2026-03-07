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
        Schema::table('stock_return_items', function (Blueprint $table) {
            $table->integer('replaced_quantity')->default(0)->after('returned_quantity');
            $table->integer('loss_quantity')->default(0)->after('replaced_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_return_items', function (Blueprint $table) {
            $table->dropColumn(['replaced_quantity', 'loss_quantity']);
        });
    }
};
