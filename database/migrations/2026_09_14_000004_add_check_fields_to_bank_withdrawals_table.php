<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A withdrawal made by writing a check does not move money when it is written.
 * It moves when the check is presented, exactly like a check written to a
 * supplier — so it is held until someone confirms it cleared, and it belongs in
 * the register where the forecast can see it.
 *
 * Purely additive; every column is nullable or defaulted, so existing
 * withdrawals stay what they already are: slips that already posted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_withdrawals', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_withdrawals', 'withdrawal_method')) {
                $table->enum('withdrawal_method', ['slip', 'check'])->default('slip')->after('cash_account_id');
            }
            if (!Schema::hasColumn('bank_withdrawals', 'check_number')) {
                $table->string('check_number', 50)->nullable()->after('withdrawal_date');
            }
            if (!Schema::hasColumn('bank_withdrawals', 'check_date')) {
                $table->date('check_date')->nullable()->after('check_number');
            }
            if (!Schema::hasColumn('bank_withdrawals', 'status')) {
                $table->enum('status', ['pending', 'posted'])->default('posted')->after('check_date');
            }
            if (!Schema::hasColumn('bank_withdrawals', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_withdrawals', function (Blueprint $table) {
            $table->dropColumn(['withdrawal_method', 'check_number', 'check_date', 'status', 'posted_at']);
        });
    }
};
