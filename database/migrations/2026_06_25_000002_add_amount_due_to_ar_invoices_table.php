<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ar_invoices', 'amount_due')) {
            Schema::table('ar_invoices', function (Blueprint $table) {
                $table->decimal('amount_due', 15, 2)->default(0)->after('invoice_date');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ar_invoices', function (Blueprint $table) {
            $table->dropColumn('amount_due');
        });
    }
};
