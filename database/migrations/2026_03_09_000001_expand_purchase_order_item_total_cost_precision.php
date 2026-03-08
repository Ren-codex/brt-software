<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `purchase_order_items` MODIFY `total_cost` DECIMAL(15,2) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `purchase_order_items` MODIFY `total_cost` DECIMAL(10,2) NOT NULL');
    }
};
