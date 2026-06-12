<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('gl_account_id')->nullable()->after('expense_type');
            $table->string('payment_method', 30)->nullable()->after('gl_account_id'); // cash, check, bank_transfer
            $table->unsignedInteger('bank_account_id')->nullable()->after('payment_method');
            $table->string('reference_no', 60)->nullable()->after('bank_account_id');
            $table->unsignedInteger('submitted_by_id')->nullable()->after('added_by_id');
            $table->unsignedInteger('approved_by_id')->nullable()->after('submitted_by_id');
            $table->unsignedInteger('released_by_id')->nullable()->after('approved_by_id');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->foreign('gl_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
            $table->foreign('submitted_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('released_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['gl_account_id']);
            $table->dropForeign(['bank_account_id']);
            $table->dropForeign(['submitted_by_id']);
            $table->dropForeign(['approved_by_id']);
            $table->dropForeign(['released_by_id']);
            $table->dropColumn([
                'gl_account_id', 'payment_method', 'bank_account_id', 'reference_no',
                'submitted_by_id', 'approved_by_id', 'released_by_id',
                'submitted_at', 'approved_at', 'released_at',
            ]);
        });
    }
};
