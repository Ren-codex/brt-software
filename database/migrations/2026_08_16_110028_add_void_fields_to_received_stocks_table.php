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
            $table->timestamp('voided_at')->nullable()->after('remarks');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->unsignedInteger('voided_by_id')->nullable()->after('void_reason');
            $table->foreign('voided_by_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('received_stocks', function (Blueprint $table) {
            $table->dropForeign(['voided_by_id']);
            $table->dropColumn(['voided_at', 'void_reason', 'voided_by_id']);
        });
    }
};
