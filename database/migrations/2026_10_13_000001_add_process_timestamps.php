<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three moments in an order's life that nothing was keeping.
 *
 * - closed_at: when the business finished with the order. The status said
 *   Closed but no column said when, so the last step of the timeline was the
 *   only one with no date on it.
 *
 * - delivery_encoded_at: when the delivery was typed in. It lived inside the
 *   delivery_submission JSON, which is wiped the moment an approver confirms
 *   it — so the encoder's time was erased by the approval that depended on it.
 *
 * - handed_over_at: when the driver actually handed the money in. The
 *   timeline was showing the remittance date instead, which can be days
 *   later, and the handover itself survived only in the activity log.
 *
 * Nothing is backfilled. These are moments nobody recorded, and the nearest
 * guess — updated_at — moves with every later edit, so a filled-in date would
 * read as fact while being wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->timestamp('delivery_encoded_at')->nullable()->after('delivery_submission');
            $table->timestamp('closed_at')->nullable()->after('delivered_by_id');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->timestamp('handed_over_at')->nullable()->after('held_by_employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_encoded_at', 'closed_at']);
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('handed_over_at');
        });
    }
};
