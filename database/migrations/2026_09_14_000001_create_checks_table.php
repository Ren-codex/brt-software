<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One register for every check in either direction. A pending check moves no
 * money: it is a record of an instrument, not of a transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->enum('direction', ['received', 'issued']);
            $table->string('check_number', 50);
            $table->date('check_date');
            $table->decimal('amount', 15, 2);
            $table->string('bank_name')->nullable();
            $table->unsignedInteger('bank_account_id')->nullable();
            $table->enum('status', ['pending', 'cleared', 'bounced'])->default('pending');
            $table->string('source_type');
            $table->unsignedInteger('source_id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->unsignedInteger('received_by_id')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->unsignedInteger('cleared_by_id')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->unsignedInteger('bounced_by_id')->nullable();
            $table->string('bounce_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'check_date']);
            $table->index(['direction', 'status']);
            $table->index(['source_type', 'source_id']);

            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
            $table->foreign('received_by_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
