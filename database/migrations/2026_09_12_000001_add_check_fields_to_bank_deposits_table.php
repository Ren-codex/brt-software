<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the cash/check axis to bank deposits.
 *
 * A check deposit moves no money when it is recorded: no journal entry is
 * written until the check date arrives, at which point deposits:post-due-checks
 * posts DR Bank / CR Cash. Every row that existed before this migration was a
 * posted cash deposit, which is exactly what the defaults describe.
 *
 * Purely additive — every column is nullable or defaulted, so no existing row is
 * rewritten. Guarded with hasColumn so a half-applied run can be repeated safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_deposits', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_deposits', 'deposit_type')) {
                $table->enum('deposit_type', ['cash', 'check'])->default('cash')->after('bank_account_id');
            }
            if (!Schema::hasColumn('bank_deposits', 'check_date')) {
                $table->date('check_date')->nullable()->after('deposit_date');
            }
            if (!Schema::hasColumn('bank_deposits', 'check_number')) {
                $table->string('check_number', 50)->nullable()->after('check_date');
            }
            if (!Schema::hasColumn('bank_deposits', 'status')) {
                $table->enum('status', ['pending', 'posted'])->default('posted')->after('check_number');
            }
            if (!Schema::hasColumn('bank_deposits', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_deposits', function (Blueprint $table) {
            $table->dropColumn(['deposit_type', 'check_date', 'check_number', 'status', 'posted_at']);
        });
    }
};
