<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->unsignedInteger('received_item_id')->nullable()->change();
            $table->unsignedInteger('product_id')->nullable()->after('received_item_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->unsignedInteger('conversion_id')->nullable()->after('product_id');
            $table->foreign('conversion_id')->references('id')->on('product_conversions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['conversion_id']);
            $table->dropColumn(['product_id', 'conversion_id']);
            $table->unsignedInteger('received_item_id')->nullable(false)->change();
        });
    }
};
