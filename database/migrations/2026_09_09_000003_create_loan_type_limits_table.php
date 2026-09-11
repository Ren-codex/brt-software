<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Punch-list item #1: a loan/cash-advance withdrawal amount must not exceed
 * a limit. There was no limit concept anywhere before this (no employee
 * salary field, no config), so this introduces one admin-editable cap per
 * loan_type. Defaults below are placeholders — tune them from the Payroll
 * Settings screen (or directly in this table) to match actual policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_type_limits', function (Blueprint $table) {
            $table->id();
            $table->string('loan_type')->unique();
            $table->decimal('max_amount', 15, 2);
            $table->timestamps();
        });

        $now = now();

        DB::table('loan_type_limits')->insert([
            ['loan_type' => 'personal', 'max_amount' => 50000, 'created_at' => $now, 'updated_at' => $now],
            ['loan_type' => 'salary', 'max_amount' => 30000, 'created_at' => $now, 'updated_at' => $now],
            ['loan_type' => 'emergency', 'max_amount' => 20000, 'created_at' => $now, 'updated_at' => $now],
            ['loan_type' => 'cash_advance', 'max_amount' => 10000, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_type_limits');
    }
};
