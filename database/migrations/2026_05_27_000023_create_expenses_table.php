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
        Schema::create('expenses', function (Blueprint $table) {
            $table->increments('id');

            $table->string('voucher_no', 30)->nullable()->unique();
            $table->unsignedInteger('fund_id')->nullable();
            $table->unsignedBigInteger('replenishment_request_id')->nullable();

            $table->enum('expense_type', ['operational', 'utilities', 'supplies', 'transportation', 'maintenance', 'others']);
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->foreign('gl_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->string('payment_method', 30)->nullable();
            $table->unsignedInteger('bank_account_id')->nullable();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
            $table->string('reference_no', 60)->nullable();

            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('payee', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('receipt_path', 500)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'released', 'recorded', 'submitted', 'reimbursed', 'voided'])->default('recorded');

            $table->unsignedInteger('added_by_id');
            $table->foreign('added_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedInteger('submitted_by_id')->nullable();
            $table->foreign('submitted_by_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('approved_by_id')->nullable();
            $table->foreign('approved_by_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('released_by_id')->nullable();
            $table->foreign('released_by_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
