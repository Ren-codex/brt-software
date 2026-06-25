<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_weight_losses', function (Blueprint $table) {
            $table->unsignedInteger('affected_sacks')->nullable()->after('loss_kg');
            $table->decimal('loss_per_sack', 8, 2)->nullable()->after('affected_sacks');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_weight_losses', function (Blueprint $table) {
            $table->dropColumn(['affected_sacks', 'loss_per_sack']);
        });
    }
};
