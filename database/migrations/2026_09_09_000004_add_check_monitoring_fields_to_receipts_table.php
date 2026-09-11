<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punch-list items #5, #10, #11, #12, #13: turns "check" from an implicit
 * payment_mode string into a tracked lifecycle.
 *
 * - check_date / check_status / released_at: on-hand → released → matured
 *   (matured flips automatically 7 days after release — see
 *   MarkMaturedChecks). Purely a custody/monitoring axis, doesn't gate money.
 * - bank_name / confirmed_at / confirmed_by_id: the manual bank-confirmation
 *   step. Setting confirmed_at is what releases this receipt's amount into
 *   the AR invoice's balance_due (see ArInvoiceClass::confirmCheck()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->date('check_date')->nullable()->after('reference_number');
            $table->enum('check_status', ['on_hand', 'released', 'matured'])->nullable()->after('check_date');
            $table->timestamp('released_at')->nullable()->after('check_status');
            $table->string('bank_name')->nullable()->after('released_at');
            $table->timestamp('confirmed_at')->nullable()->after('bank_name');
            $table->unsignedInteger('confirmed_by_id')->nullable()->after('confirmed_at');
            $table->foreign('confirmed_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by_id']);
            $table->dropColumn(['check_date', 'check_status', 'released_at', 'bank_name', 'confirmed_at', 'confirmed_by_id']);
        });
    }
};
