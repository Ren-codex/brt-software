<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pending cheque can be cancelled — withdrawn because the customer is paying
 * another way (cash, or carrying it as credit). Distinct from a bounce: no bank
 * failure, no rep chase. Mirrors the bounced_* columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 'status' was an enum limited to pending/cleared/bounced. Widen it to a
        // plain string so the new 'cancelled' value is accepted on both MySQL
        // (ENUM) and SQLite (a CHECK constraint); valid values are enforced in
        // the app via Check's constants, not the column. Done on its own so the
        // change is not mixed with the column adds below.
        Schema::table('checks', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        Schema::table('checks', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('bounce_reason');
            $table->unsignedInteger('cancelled_by_id')->nullable()->after('cancelled_at');
            $table->string('cancel_reason')->nullable()->after('cancelled_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancelled_by_id', 'cancel_reason']);
        });

        Schema::table('checks', function (Blueprint $table) {
            $table->enum('status', ['pending', 'cleared', 'bounced'])->default('pending')->change();
        });
    }
};
