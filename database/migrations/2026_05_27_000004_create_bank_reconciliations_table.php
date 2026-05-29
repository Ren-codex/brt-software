<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('bank_account_id');
            $table->date('period_end');
            $table->decimal('statement_balance', 15, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'finalized'])->default('open');
            $table->unsignedInteger('created_by_id')->nullable();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts');
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_reconciliation_clears', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('reconciliation_id');
            $table->unsignedBigInteger('journal_entry_line_id');
            $table->foreign('reconciliation_id')->references('id')->on('bank_reconciliations')->cascadeOnDelete();
            $table->foreign('journal_entry_line_id')->references('id')->on('journal_entry_lines')->cascadeOnDelete();
            $table->unique(['reconciliation_id', 'journal_entry_line_id'], 'recon_clears_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_clears');
        Schema::dropIfExists('bank_reconciliations');
    }
};
