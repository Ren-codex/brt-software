<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punch-list item #3: a loan/cash-advance withdrawal can be disbursed as
 * cash or check — record which.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->enum('payment_type', ['cash', 'check'])->default('cash')->after('loan_type');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
    }
};
