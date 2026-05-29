<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite does not support ALTER COLUMN, so we recreate using ->change()
            // which Doctrine/DBAL handles by recreating the table
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('status')->default('recorded')->change();
            });
        }
        // For MySQL/MariaDB the replenishment migration already ran MODIFY COLUMN
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
        }
    }
};
