<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('unit_cost');
            $table->boolean('is_archived')->default(false)->after('notes');
            $table->boolean('is_expired')->default(false)->after('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table) {
            $table->dropColumn(['notes', 'is_archived', 'is_expired']);
        });
    }
};
