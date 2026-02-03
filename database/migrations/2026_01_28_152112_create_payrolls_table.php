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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('payroll_no')->unique();
            $table->date('pay_period_start');
            $table->date('pay_period_end');
            $table->enum('status', ['draft', 'pending', 'approved', 'paid'])->default('pending');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedInteger('payroll_template_id')->nullable();
            $table->foreign('payroll_template_id')->references('id')->on('payroll_templates')->onDelete('set null');
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
