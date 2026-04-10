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

        DB::table('sales_return_items')
            ->whereNull('return_quantity')
            ->update([
                'return_quantity' => DB::raw('(SELECT quantity FROM sales_order_items WHERE sales_order_items.id = sales_return_items.sales_order_item_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropColumn('return_quantity');
        });
    }
};
