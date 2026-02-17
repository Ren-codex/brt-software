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
        Schema::create('payroll_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('payroll_id');
            $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('cascade');
            $table->string('action');
            $table->unsignedInteger('actioned_by_id')->nullable();
            $table->foreign('actioned_by_id')->references('id')->on('users')->onDelete('set null');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_logs');
    }
};
