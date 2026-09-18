<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the goods reached the customer, and who recorded it. The delivery date
 * beside it is a plan typed at order entry; this is what actually happened.
 * Nullable, so every order encoded before this simply has neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('delivery_date');
            $table->unsignedInteger('delivered_by_id')->nullable()->after('delivered_at');
            $table->foreign('delivered_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['delivered_by_id']);
            $table->dropColumn(['delivered_at', 'delivered_by_id']);
        });
    }
};
