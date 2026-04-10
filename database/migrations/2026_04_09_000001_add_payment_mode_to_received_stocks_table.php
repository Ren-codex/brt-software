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
        Schema::table('received_stocks', function (Blueprint $table) {
            $table->string('payment_mode')->default('Credit')->after('received_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('received_stocks', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });
    }
};
