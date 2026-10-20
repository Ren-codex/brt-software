<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which cheque a deposit is carrying.
 *
 * A deposit used to find its cheque by matching the number and the amount,
 * because nothing recorded the link. Cheque numbers are only unique per
 * issuing bank, so two customers' cheques can share one; the clearing code
 * knows this and refuses to post at all when more than one deposit matches,
 * rather than risk moving the wrong customer's money.
 *
 * Deposits are made by picking cheques off a list now, so the link is known at
 * the moment it is made and there is nothing left to guess. Nullable, and the
 * old matching stays as the fallback: rows written before this have no id to
 * offer, and inventing one for them would be the same guess in a new place.
 *
 * unsignedInteger, not foreignId: checks.id is a plain int, and a bigint
 * column cannot carry a foreign key to it. MySQL adds the column, then refuses
 * the constraint, and leaves the migration half-applied with nothing recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_deposits', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_deposits', 'check_id')) {
                $table->unsignedInteger('check_id')->nullable()->after('deposit_type');
            }
        });

        Schema::table('bank_deposits', function (Blueprint $table) {
            $table->foreign('check_id')->references('id')->on('checks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_deposits', function (Blueprint $table) {
            $table->dropForeign(['check_id']);
            $table->dropColumn('check_id');
        });
    }
};
