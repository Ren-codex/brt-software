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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('po_number');
            $table->date('po_date');
            $table->decimal('total_amount', 15, 2);
            $table->unsignedInteger('status_id');
            $table->foreign('status_id')->references('id')->on('list_statuses')->onDelete('cascade');
            $table->unsignedInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('list_suppliers')->onDelete('cascade');
            $table->unsignedInteger('created_by_id');
            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
