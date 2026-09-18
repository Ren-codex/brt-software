<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is physically holding this collection. Set to the driver who took it at
 * the door, moved on when they hand it over, and cleared by the remittance
 * that carries it into the office.
 *
 * Distinct from a cheque's on-hand/released/matured lifecycle, which tracks
 * what stage the instrument is at rather than whose hands it is in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->unsignedInteger('held_by_employee_id')->nullable()->after('remittance_id');
            $table->foreign('held_by_employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['held_by_employee_id']);
            $table->dropColumn('held_by_employee_id');
        });
    }
};
