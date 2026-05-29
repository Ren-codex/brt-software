<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_deposits', function (Blueprint $table) {
            $table->increments('id');
            $table->string('deposit_no', 30)->unique();
            $table->unsignedBigInteger('cash_account_id');
            $table->unsignedInteger('bank_account_id');
            $table->decimal('amount', 15, 2);
            $table->date('deposit_date');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by_id')->nullable();
            $table->foreign('cash_account_id')->references('id')->on('accounts');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts');
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('series')->insert([
            ['name' => 'Bank Deposit', 'slug' => 'bank_deposit_no', 'prefix' => 'BD', 'max_digit' => 6, 'starting_value' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_deposits');
        DB::table('series')->where('slug', 'bank_deposit_no')->delete();
    }
};
