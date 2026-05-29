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
        Schema::create('received_stock_payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('received_stock_id');
            $table->foreign('received_stock_id')->references('id')->on('received_stocks')->onDelete('cascade');
            $table->date('payment_date');
            $table->string('payment_mode', 50);
            $table->decimal('amount_paid', 15, 2);
            $table->string('bank_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->unsignedInteger('created_by_id')->nullable();
            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('received_stock_payments');
    }
};
