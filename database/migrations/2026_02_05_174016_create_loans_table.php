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
    Schema::create('loans', function (Blueprint $table) {
        $table->increments('id');  // Changed from id() to increments()

        $table->string('loan_no')->nullable()->unique();

        $table->unsignedInteger('employee_id');
        $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');

        $table->enum('loan_type', ['personal', 'salary', 'emergency', 'housing']);
        $table->decimal('amount', 15, 2);
        $table->decimal('interest_rate', 5, 2);
        $table->integer('term_months');
        $table->enum('status', ['pending', 'approved', 'rejected', 'active', 'completed'])->default('pending');
        $table->text('purpose')->nullable();

        $table->unsignedInteger('added_by_id');  // Changed from foreignId()
        $table->foreign('added_by_id')->references('id')->on('users')->onDelete('cascade');

        $table->timestamps();

        $table->decimal('amtpaid', 15, 2)->nullable()->default(0);
        $table->decimal('remaining_balance', 15, 2)->nullable()->default(0);
        $table->decimal('remaining_term_to_pay', 15, 2)->nullable()->default(0);

        $table->unsignedInteger('approved_by_id')->nullable();
        $table->foreign('approved_by_id')->references('id')->on('users')->onDelete('cascade');
        $table->timestamp('approved_at')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
