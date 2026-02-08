<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LoanSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing loans
        DB::table('loans')->truncate();

        // Get first employee and user
        $employee = DB::table('employees')->first();
        $user = DB::table('users')->first();

        if (!$employee || !$user) {
            $this->command->error('No employees or users found. Please seed those first.');
            return;
        }

        $loans = [
            [
                'employee_id' => $employee->id,
                'loan_type' => 'personal',
                'amount' => 50000.00,
                'interest_rate' => 10.50,
                'term_months' => 24,
                'status' => 'approved',
                'purpose' => 'Home renovation',
                'added_by_id' => $user->id,
                'created_at' => Carbon::now()->subMonths(3),
                'updated_at' => Carbon::now()->subMonths(3),
            ],
            [
                'employee_id' => $employee->id,
                'loan_type' => 'salary',
                'amount' => 25000.00,
                'interest_rate' => 6.75,
                'term_months' => 12,
                'status' => 'active',
                'purpose' => 'Emergency medical expenses',
                'added_by_id' => $user->id,
                'created_at' => Carbon::now()->subMonths(1),
                'updated_at' => Carbon::now()->subMonths(1),
            ],
            [
                'employee_id' => $employee->id,
                'loan_type' => 'emergency',
                'amount' => 15000.00,
                'interest_rate' => 5.25,
                'term_months' => 6,
                'status' => 'pending',
                'purpose' => 'Car repairs',
                'added_by_id' => $user->id,
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(5),
            ],
            [
                'employee_id' => $employee->id,
                'loan_type' => 'housing',
                'amount' => 250000.00,
                'interest_rate' => 8.90,
                'term_months' => 120,
                'status' => 'approved',
                'purpose' => 'Down payment for house',
                'added_by_id' => $user->id,
                'created_at' => Carbon::now()->subMonths(6),
                'updated_at' => Carbon::now()->subMonths(6),
            ],
            [
                'employee_id' => $employee->id,
                'loan_type' => 'personal',
                'amount' => 35000.00,
                'interest_rate' => 12.00,
                'term_months' => 18,
                'status' => 'completed',
                'purpose' => 'Children\'s education',
                'added_by_id' => $user->id,
                'created_at' => Carbon::now()->subMonths(18),
                'updated_at' => Carbon::now()->subMonths(2),
            ],
        ];

        DB::table('loans')->insert($loans);
        $this->command->info('✅ Successfully seeded 5 sample loans.');
    }
}