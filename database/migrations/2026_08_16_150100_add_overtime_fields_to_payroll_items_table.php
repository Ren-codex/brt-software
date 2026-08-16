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
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('hours_per_day', 5, 2)->nullable()->after('total_days');
            $table->decimal('overtime_hours', 8, 2)->default(0)->after('hours_per_day');
            $table->decimal('overtime_rate', 10, 2)->nullable()->after('overtime_hours');
            $table->decimal('overtime_amount', 15, 2)->default(0)->after('overtime_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['hours_per_day', 'overtime_hours', 'overtime_rate', 'overtime_amount']);
        });
    }
};
