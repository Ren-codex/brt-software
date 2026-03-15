<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'paid', 'released') NOT NULL DEFAULT 'pending'");

        DB::table('expenses')
            ->where('status', 'paid')
            ->update(['status' => 'released']);

        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'released') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'paid', 'released') NOT NULL DEFAULT 'pending'");

        DB::table('expenses')
            ->where('status', 'released')
            ->update(['status' => 'paid']);

        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'paid') NOT NULL DEFAULT 'pending'");
    }
};
