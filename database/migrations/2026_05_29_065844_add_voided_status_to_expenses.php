<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (\DB::connection()->getDriverName() !== 'sqlite') {
            \DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending','approved','rejected','released','recorded','submitted','reimbursed','voided') DEFAULT 'recorded'");
        }
    }

    public function down(): void
    {
        if (\DB::connection()->getDriverName() !== 'sqlite') {
            \DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending','approved','rejected','released','recorded','submitted','reimbursed') DEFAULT 'recorded'");
        }
    }
};
