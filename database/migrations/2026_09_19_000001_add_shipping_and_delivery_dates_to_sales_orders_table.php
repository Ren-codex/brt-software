<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the goods are planned to ship and to arrive. Both are entered with the
 * order and can be corrected by editing it later, so both are nullable —
 * every order encoded before this migration simply has neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->date('shipping_date')->nullable()->after('due_date');
            $table->date('delivery_date')->nullable()->after('shipping_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_date', 'delivery_date']);
        });
    }
};
