<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two optional per-item fields captured when stock is received:
 *  - tracking_expense: a peso amount recorded alongside the item for reference
 *    (e.g. hauling or handling). It is informational only — it does not change
 *    the item's cost, the payable, or any journal entry.
 *  - remarks: a short free-text note about that received line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_items', function (Blueprint $table) {
            $table->decimal('tracking_expense', 15, 4)->nullable()->after('total_cost');
            $table->text('remarks')->nullable()->after('tracking_expense');
        });
    }

    public function down(): void
    {
        Schema::table('received_items', function (Blueprint $table) {
            $table->dropColumn(['tracking_expense', 'remarks']);
        });
    }
};
