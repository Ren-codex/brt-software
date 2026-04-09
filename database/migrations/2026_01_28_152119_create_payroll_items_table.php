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
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->decimal('basic_salary', 15, 2);
            $table->json('deductions')->nullable();
            $table->json('earnings')->nullable();
            $table->decimal('total_earnings', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_days', 8, 2)->default(0);
            $table->decimal('net_salary', 15, 2);
            $table->json('loans')->nullable();
            $table->unsignedInteger('payroll_id')->index();
            $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('cascade');
            $table->unsignedInteger('employee_id')->index();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
