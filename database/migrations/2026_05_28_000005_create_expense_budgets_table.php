<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expense_budgets')) {
            return;
        }

        Schema::create('expense_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('expense_type', 50);
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 15, 2)->default(0);
            $table->bigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->unique(['expense_type', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_budgets');
    }
};
