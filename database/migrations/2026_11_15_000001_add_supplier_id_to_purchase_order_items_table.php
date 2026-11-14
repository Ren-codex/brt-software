<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A purchase order used to carry a single supplier for the whole order. Each
 * product can now come from a different supplier, so the supplier moves onto
 * the line item. The order-level purchase_orders.supplier_id is kept as a
 * representative supplier (the first line's) so existing reads — the printed
 * PO's Bill-To, lists, the receiving default — keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Nullable at the DB level because existing rows have no value yet;
            // the backfill below fills them, and the form request requires one
            // on every new line.
            $table->unsignedInteger('supplier_id')->nullable()->after('product_id');
            $table->foreign('supplier_id')->references('id')->on('list_suppliers')->nullOnDelete();
        });

        // Every existing line inherits its order's supplier, so orders created
        // before this change stay complete. Written as a correlated subquery so
        // it runs identically on MySQL (production) and SQLite (the test suite).
        DB::table('purchase_order_items')
            ->whereNull('supplier_id')
            ->update([
                'supplier_id' => DB::raw('(select supplier_id from purchase_orders where purchase_orders.id = purchase_order_items.po_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
