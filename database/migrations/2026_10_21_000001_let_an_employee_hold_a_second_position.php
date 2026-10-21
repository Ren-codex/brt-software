<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The other jobs an employee covers.
 *
 * People here do more than one: a sales rep takes the wheel when a driver is
 * out, a driver covers a second truck's route. One position per employee meant
 * the driver list hid anybody whose main job was something else.
 *
 * employees.position_id stays exactly as it was, and stays the one that pays —
 * the daily rate lives on the position, and payroll reads that column alone.
 * This table only says who else can be picked for what.
 *
 * unsignedInteger, not foreignId: employees.id and list_positions.id are both
 * plain ints, and a bigint column cannot carry a foreign key to one. MySQL
 * adds the column, refuses the constraint, and leaves the migration
 * half-applied with nothing recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('position_id');
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('position_id')->references('id')->on('list_positions')->cascadeOnDelete();
            // One row per pair: holding the same position twice says nothing.
            $table->unique(['employee_id', 'position_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_positions');
    }
};
