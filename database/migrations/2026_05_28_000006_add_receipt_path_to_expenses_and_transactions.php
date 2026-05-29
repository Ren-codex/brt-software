<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('receipt_path', 500)->nullable()->after('description');
        });

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->string('receipt_path', 500)->nullable()->after('reference_number');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });
    }
};
