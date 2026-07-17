<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_withdrawals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('withdrawal_no', 30)->unique();
            $table->unsignedInteger('bank_account_id');
            $table->unsignedBigInteger('cash_account_id');
            $table->decimal('amount', 15, 2);
            $table->date('withdrawal_date');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by_id')->nullable();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts');
            $table->foreign('cash_account_id')->references('id')->on('accounts');
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('series')->insert([
            ['name' => 'Bank Withdrawal', 'slug' => 'bank_withdrawal_no', 'prefix' => 'BW', 'max_digit' => 6, 'starting_value' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_withdrawals');
        DB::table('series')->where('slug', 'bank_withdrawal_no')->delete();
    }
};
