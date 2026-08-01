<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite has no ENUM type — Laravel enforces the allowed values via a
            // CHECK constraint generated from Schema::enum(), so change() works
            // the same way it would for any other column type.
            Schema::table('expenses', function (Blueprint $table) {
                $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'released', 'recorded', 'submitted', 'reimbursed', 'voided'])
                    ->default('recorded')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('draft','pending','approved','rejected','released','recorded','submitted','reimbursed','voided') NOT NULL DEFAULT 'recorded'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('expenses', function (Blueprint $table) {
                $table->enum('status', ['pending', 'approved', 'rejected', 'released', 'recorded', 'submitted', 'reimbursed', 'voided'])
                    ->default('recorded')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending','approved','rejected','released','recorded','submitted','reimbursed','voided') NOT NULL DEFAULT 'recorded'");
    }
};
