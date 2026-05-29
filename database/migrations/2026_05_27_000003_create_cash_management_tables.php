<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_transfers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('transfer_no')->unique();
            $table->date('transfer_date');
            $table->unsignedInteger('from_bank_account_id');
            $table->unsignedInteger('to_bank_account_id');
            $table->decimal('amount', 15, 2);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by_id')->nullable();
            $table->foreign('from_bank_account_id')->references('id')->on('bank_accounts');
            $table->foreign('to_bank_account_id')->references('id')->on('bank_accounts');
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('petty_cash_funds', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('gl_code')->unique();
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by_id')->nullable();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('transaction_no')->unique();
            $table->unsignedInteger('fund_id');
            $table->enum('type', ['replenishment', 'disbursement']);
            $table->decimal('amount', 15, 2);
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->date('transaction_date');
            $table->string('reference_number')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedInteger('bank_account_id')->nullable();
            $table->unsignedInteger('created_by_id')->nullable();
            $table->foreign('fund_id')->references('id')->on('petty_cash_funds');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        // Series entries for document numbering
        $now = now();
        DB::table('series')->upsert([
            ['name' => 'Fund Transfer', 'slug' => 'fund_transfer_no', 'prefix' => 'FT', 'max_digit' => 6, 'starting_value' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Petty Cash Transaction', 'slug' => 'petty_cash_txn_no', 'prefix' => 'PCT', 'max_digit' => 6, 'starting_value' => 1, 'created_at' => $now, 'updated_at' => $now],
        ], ['slug'], ['name', 'prefix', 'max_digit', 'updated_at']);
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_transactions');
        Schema::dropIfExists('petty_cash_funds');
        Schema::dropIfExists('fund_transfers');
    }
};
