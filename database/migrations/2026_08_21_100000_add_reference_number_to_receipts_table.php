<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A receipt paid by bank transfer or check needs the transfer reference / check
 * number to be reconcilable against the bank statement. The point-of-sale screen
 * already asked the cashier for it — there was simply nowhere to put it, so the
 * value was collected and dropped on submit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->after('payment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('reference_number');
        });
    }
};
