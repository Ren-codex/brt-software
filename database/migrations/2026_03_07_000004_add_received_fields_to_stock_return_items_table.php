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
        Schema::table('stock_return_items', function (Blueprint $table) {
            $table->unsignedInteger('received_by_id')->nullable()->after('status_id')->index();
            $table->foreign('received_by_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('received_at')->nullable()->after('received_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_return_items', function (Blueprint $table) {
            $table->dropForeign(['received_by_id']);
            $table->dropColumn(['received_by_id', 'received_at']);
        });
    }
};
