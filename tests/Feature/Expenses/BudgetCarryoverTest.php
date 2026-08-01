<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCarryoverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeBudget(string $type, int $month, int $year, float $amount): ExpenseBudget
    {
        return ExpenseBudget::create([
            'expense_type'  => $type,
            'month'         => $month,
            'year'          => $year,
            'amount'        => $amount,
            'created_by_id' => $this->user->id,
        ]);
    }

    private function makeReleasedExpense(string $type, int $month, int $year, float $amount): Expense
    {
        return Expense::create([
            'expense_type' => $type,
            'amount'       => $amount,
            'expense_date' => date('Y-m-d', mktime(0, 0, 0, $month, 15, $year)),
            'status'       => 'released',
            'added_by_id'  => $this->user->id,
        ]);
    }

    public function test_unused_budget_carries_over_to_next_month(): void
    {
        $this->makeBudget('operational', 4, 2026, 10000);
        $this->makeReleasedExpense('operational', 4, 2026, 7000);

        $this->artisan('expense:carry-budget', ['--month' => 5, '--year' => 2026])
             ->assertExitCode(0);

        $this->assertDatabaseHas('expense_budgets', [
            'expense_type' => 'operational',
            'month'        => 5,
            'year'         => 2026,
            'amount'       => 3000,
        ]);
    }

    public function test_no_carryover_when_budget_fully_used(): void
    {
        $this->makeBudget('utilities', 4, 2026, 5000);
        $this->makeReleasedExpense('utilities', 4, 2026, 5000);

        $this->artisan('expense:carry-budget', ['--month' => 5, '--year' => 2026])
             ->assertExitCode(0);

        $this->assertDatabaseMissing('expense_budgets', [
            'expense_type' => 'utilities',
            'month'        => 5,
            'year'         => 2026,
        ]);
    }

    public function test_carryover_skips_existing_current_month_budget(): void
    {
        $this->makeBudget('supplies', 4, 2026, 8000);
        $this->makeBudget('supplies', 5, 2026, 2000);

        $this->artisan('expense:carry-budget', ['--month' => 5, '--year' => 2026])
             ->assertExitCode(0);

        $this->assertDatabaseHas('expense_budgets', [
            'expense_type' => 'supplies',
            'month'        => 5,
            'year'         => 2026,
            'amount'       => 2000,
        ]);
        $this->assertEquals(1, ExpenseBudget::where('expense_type', 'supplies')->where('month', 5)->where('year', 2026)->count());
    }

    public function test_carryover_skips_type_with_no_prior_budget(): void
    {
        $this->artisan('expense:carry-budget', ['--month' => 5, '--year' => 2026])
             ->assertExitCode(0);

        $this->assertDatabaseMissing('expense_budgets', [
            'expense_type' => 'transportation',
            'month'        => 5,
            'year'         => 2026,
        ]);
    }

    public function test_command_handles_january_by_targeting_december_prior_year(): void
    {
        $this->makeBudget('maintenance', 12, 2025, 6000);
        $this->makeReleasedExpense('maintenance', 12, 2025, 1000);

        $this->artisan('expense:carry-budget', ['--month' => 1, '--year' => 2026])
             ->assertExitCode(0);

        $this->assertDatabaseHas('expense_budgets', [
            'expense_type' => 'maintenance',
            'month'        => 1,
            'year'         => 2026,
            'amount'       => 5000,
        ]);
    }
}
