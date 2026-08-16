<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // True when a line item's batch was manually overridden away from the
            // FIFO-default batch — the order then requires approver sign-off
            // before it can auto-close (cash) or before it's treated as finalized.
            $table->boolean('requires_batch_approval')->default(false)->after('cancellation_remarks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('requires_batch_approval');
        });
    }
};
