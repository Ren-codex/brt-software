<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('receipt_type')->default('payment')->after('receipt_number');
            $table->unsignedInteger('source_receipt_id')->nullable()->after('ar_invoice_id')->index();
            $table->foreign('source_receipt_id')->references('id')->on('receipts')->onDelete('set null');
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->unsignedInteger('source_receipt_id')->nullable()->after('sales_order_item_id')->index();
            $table->foreign('source_receipt_id')->references('id')->on('receipts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropForeign(['source_receipt_id']);
            $table->dropColumn('source_receipt_id');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['source_receipt_id']);
            $table->dropColumn(['receipt_type', 'source_receipt_id']);
        });
    }
};
