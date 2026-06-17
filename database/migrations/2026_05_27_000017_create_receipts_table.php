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
        Schema::create('receipts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('ar_invoice_id');
            $table->foreign('ar_invoice_id')->references('id')->on('ar_invoices')->onDelete('cascade');
            $table->unsignedInteger('source_receipt_id')->nullable();
            $table->foreign('source_receipt_id')->references('id')->on('receipts')->onDelete('set null');
            $table->unsignedInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedInteger('status_id');
            $table->foreign('status_id')->references('id')->on('list_statuses')->onDelete('cascade');
            $table->string('receipt_number')->unique();
            $table->string('receipt_type')->default('payment');
            $table->unsignedInteger('remittance_id')->nullable();
            $table->foreign('remittance_id')->references('id')->on('remittances')->onDelete('set null');
            $table->date('receipt_date');
            $table->decimal('amount_paid' , 15,2);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('payment_mode')->nullable();
            $table->unsignedInteger('bank_account_id')->nullable();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
