<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_stocks', function (Blueprint $table) {
            $table->unsignedInteger('bank_account_id')->nullable()->after('reference_number');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
        });

        Schema::table('received_stock_payments', function (Blueprint $table) {
            $table->unsignedInteger('bank_account_id')->nullable()->after('reference_number');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedInteger('bank_account_id')->nullable()->after('payment_mode');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->unsignedInteger('bank_account_id')->nullable()->after('payment_mode');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });

        Schema::table('received_stock_payments', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });

        Schema::table('received_stocks', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });
    }
};
