<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remittances', function (Blueprint $table) {
            $table->string('received_via')->nullable()->after('remarks');
            $table->string('reference_no')->nullable()->after('received_via');
        });
    }

    public function down(): void
    {
        Schema::table('remittances', function (Blueprint $table) {
            $table->dropColumn(['received_via', 'reference_no']);
        });
    }
};
