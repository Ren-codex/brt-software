<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sale on credit waits for an approver before the goods can go out.
 *
 * `credit_approval` is null on an order that never needed one (cash, COD, and
 * every order placed before this existed), and otherwise 'pending',
 * 'approved' or 'rejected'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_orders', 'credit_approval')) {
                $table->string('credit_approval', 20)->nullable()->index();
            }
            if (! Schema::hasColumn('sales_orders', 'credit_approval_remarks')) {
                $table->string('credit_approval_remarks', 500)->nullable();
            }
            if (! Schema::hasColumn('sales_orders', 'credit_approved_by_id')) {
                $table->unsignedBigInteger('credit_approved_by_id')->nullable();
            }
            if (! Schema::hasColumn('sales_orders', 'credit_approved_at')) {
                $table->timestamp('credit_approved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            foreach (['credit_approved_at', 'credit_approved_by_id', 'credit_approval_remarks', 'credit_approval'] as $column) {
                if (Schema::hasColumn('sales_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
