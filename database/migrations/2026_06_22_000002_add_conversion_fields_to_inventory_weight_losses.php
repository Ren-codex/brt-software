<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_weight_losses', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_weight_losses', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('recorded_at');
            }
            if (!Schema::hasColumn('inventory_weight_losses', 'converted_by_id')) {
                $table->unsignedInteger('converted_by_id')->nullable()->after('converted_at');
                $table->foreign('converted_by_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('inventory_weight_losses', 'conversion_id')) {
                $table->unsignedInteger('conversion_id')->nullable()->after('converted_by_id');
                $table->foreign('conversion_id')->references('id')->on('product_conversions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_weight_losses', function (Blueprint $table) {
            $table->dropForeign(['converted_by_id']);
            $table->dropForeign(['conversion_id']);
            $table->dropColumn(['converted_at', 'converted_by_id', 'conversion_id']);
        });
    }
};
