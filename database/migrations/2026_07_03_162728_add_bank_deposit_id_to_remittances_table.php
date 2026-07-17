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
        Schema::table('remittances', function (Blueprint $table) {
            $table->unsignedInteger('bank_deposit_id')->nullable()->after('status_id');
            $table->foreign('bank_deposit_id')->references('id')->on('bank_deposits')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('remittances', function (Blueprint $table) {
            $table->dropForeign(['bank_deposit_id']);
            $table->dropColumn('bank_deposit_id');
        });
    }
};
