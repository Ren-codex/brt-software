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
        Schema::create('received_stocks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('po_id');
            $table->foreign('po_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->unsignedInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('list_suppliers')->onDelete('cascade');
            $table->date('received_date');
            $table->string('received_no')->unique();
            $table->string('payment_mode')->default('Credit');
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('bank_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->unsignedInteger('bank_account_id')->nullable();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
            $table->unsignedInteger('received_by_id')->nullable();
            $table->foreign('received_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('received_stocks');
    }
};
