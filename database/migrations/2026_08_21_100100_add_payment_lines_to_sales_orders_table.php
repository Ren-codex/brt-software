<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cash sale can now be settled with several payment methods at once (part
 * cash, part bank transfer, part check). The breakdown is held on the order
 * because an order flagged for batch approval does not turn into receipts at
 * save time — finalizeCashSale() runs later, once an approver signs off, by
 * which point the original request is long gone.
 *
 * Null means "single payment mode", and the old one-receipt behaviour applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->json('payment_lines')->nullable()->after('payment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('payment_lines');
        });
    }
};
