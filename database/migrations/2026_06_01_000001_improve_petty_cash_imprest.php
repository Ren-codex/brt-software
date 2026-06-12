<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add fixed_amount (original imprest amount) and custodian to funds
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            $table->decimal('fixed_amount', 15, 2)->default(0)->after('balance');
            $table->unsignedInteger('custodian_id')->nullable()->after('fixed_amount');
            $table->foreign('custodian_id')->references('id')->on('users')->nullOnDelete();
        });

        // Sync existing fund fixed_amount = current balance
        DB::statement('UPDATE petty_cash_funds SET fixed_amount = balance WHERE fixed_amount = 0');

        // Add voucher number to expenses for PCV tracking
        if (Schema::hasTable('expenses') && !Schema::hasColumn('expenses', 'voucher_no')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('voucher_no', 30)->nullable()->unique()->after('id');
            });
        }

        // Register PCV series for voucher numbering
        DB::table('series')->upsert([
            [
                'name'            => 'Petty Cash Voucher',
                'slug'            => 'pcv_no',
                'prefix'          => 'PCV',
                'max_digit'       => 6,
                'starting_value'  => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ], ['slug'], ['name', 'prefix', 'max_digit', 'updated_at']);
    }

    public function down(): void
    {
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            $table->dropForeign(['custodian_id']);
            $table->dropColumn(['fixed_amount', 'custodian_id']);
        });

        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'voucher_no')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('voucher_no');
            });
        }

        DB::table('series')->where('slug', 'pcv_no')->delete();
    }
};
