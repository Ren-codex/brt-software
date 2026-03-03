<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        $expenses = [
            [
                'expense_type' => 'operational',
                'amount' => 5000.00,
                'expense_date' => now()->subDays(5),
                'description' => 'Office supplies and stationery',
                'status' => 'paid',
                'added_by_id' => $user->id,
            ],
            [
                'expense_type' => 'utilities',
                'amount' => 15000.00,
                'expense_date' => now()->subDays(10),
                'description' => 'Electricity and water bills',
                'status' => 'approved',
                'added_by_id' => $user->id,
            ],
            [
                'expense_type' => 'transportation',
                'amount' => 3000.00,
                'expense_date' => now()->subDays(3),
                'description' => 'Fuel and vehicle maintenance',
                'status' => 'pending',
                'added_by_id' => $user->id,
            ],
            [
                'expense_type' => 'maintenance',
                'amount' => 8000.00,
                'expense_date' => now()->subDays(7),
                'description' => 'Equipment repair and maintenance',
                'status' => 'approved',
                'added_by_id' => $user->id,
            ],
            [
                'expense_type' => 'others',
                'amount' => 2500.00,
                'expense_date' => now()->subDays(2),
                'description' => 'Miscellaneous expenses',
                'status' => 'pending',
                'added_by_id' => $user->id,
            ],
        ];

        foreach ($expenses as $expense) {
            Expense::create($expense);
        }
    }
}
