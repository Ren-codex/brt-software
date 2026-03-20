<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->string('return_condition')->nullable()->after('return_quantity');
        });

        DB::table('sales_return_items')
            ->whereNull('return_condition')
            ->update(['return_condition' => 'restockable']);
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropColumn('return_condition');
        });
    }
};
