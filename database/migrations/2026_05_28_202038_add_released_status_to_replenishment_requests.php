<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (\DB::connection()->getDriverName() !== 'sqlite') {
            \DB::statement("ALTER TABLE replenishment_requests MODIFY COLUMN status ENUM('draft','submitted','approved','rejected','released') DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (\DB::connection()->getDriverName() !== 'sqlite') {
            \DB::statement("ALTER TABLE replenishment_requests MODIFY COLUMN status ENUM('draft','submitted','approved','rejected') DEFAULT 'draft'");
        }
    }
};
