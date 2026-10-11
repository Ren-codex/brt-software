<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The checker who rides with the driver.
 *
 * A delivery goes out as a pair: the driver carries the goods and the checker
 * takes the customer's money at the door. When an order names a checker, a
 * doorstep collection is recorded as held by them rather than by the driver —
 * see SalesOrder::fieldCollectorId(). The column is optional, so orders sent out
 * with a driver alone behave exactly as before.
 *
 * Who may be chosen as a checker is decided by the Checker user role, added in
 * the migration that follows this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded so a run that stopped part-way can simply be run again: MySQL
        // commits a schema change immediately, so it is not rolled back with
        // the rest of a failed migration.
        if (! Schema::hasColumn('sales_orders', 'checker_id')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->unsignedInteger('checker_id')->nullable()->after('driver_id');
                $table->foreign('checker_id')->references('id')->on('employees')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['checker_id']);
            $table->dropColumn('checker_id');
        });

    }
};
