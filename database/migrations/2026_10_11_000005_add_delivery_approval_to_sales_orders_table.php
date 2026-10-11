<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A delivery is encoded first and recorded only once an approver confirms it.
 *
 * `delivery_submission` holds what the encoder entered — quantities accepted,
 * money collected at the door — until then. Nothing in it is a delivery or a
 * payment yet; approving is what turns it into both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_orders', 'delivery_submission')) {
                $table->json('delivery_submission')->nullable()->after('delivered_by_id');
            }
            if (! Schema::hasColumn('sales_orders', 'delivery_rejection')) {
                $table->string('delivery_rejection', 500)->nullable()->after('delivery_submission');
            }
            if (! Schema::hasColumn('sales_orders', 'delivery_approved_by_id')) {
                $table->unsignedBigInteger('delivery_approved_by_id')->nullable()->after('delivery_rejection');
            }
            if (! Schema::hasColumn('sales_orders', 'delivery_approved_at')) {
                $table->timestamp('delivery_approved_at')->nullable()->after('delivery_approved_by_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            foreach (['delivery_approved_at', 'delivery_approved_by_id', 'delivery_rejection', 'delivery_submission'] as $column) {
                if (Schema::hasColumn('sales_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
