<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->unsignedInteger('return_quantity')->nullable()->after('sales_order_item_id');
        });

        DB::statement('
            UPDATE sales_return_items sri
            INNER JOIN sales_order_items soi ON soi.id = sri.sales_order_item_id
            SET sri.return_quantity = soi.quantity
            WHERE sri.return_quantity IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropColumn('return_quantity');
        });
    }
};
