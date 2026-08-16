<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            // bank_accounts.id is unsignedInteger (increments()), not the bigint
            // foreignId() assumes, so the FK must match that type explicitly.
            $table->unsignedInteger('bank_account_id')->nullable()->after('account_id');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });
    }
};
