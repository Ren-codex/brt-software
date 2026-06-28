<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_stock_payments', function (Blueprint $table) {
            $table->unsignedInteger('petty_cash_fund_id')->nullable()->after('bank_account_id');
            $table->foreign('petty_cash_fund_id')->references('id')->on('petty_cash_funds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('received_stock_payments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\PettyCashFund::class);
            $table->dropColumn('petty_cash_fund_id');
        });
    }
};
