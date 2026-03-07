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
        Schema::table('stock_returns', function (Blueprint $table) {
            $table->string('stock_return_no')->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_returns', function (Blueprint $table) {
            $table->dropUnique(['stock_return_no']);
            $table->dropColumn('stock_return_no');
        });
    }
};
