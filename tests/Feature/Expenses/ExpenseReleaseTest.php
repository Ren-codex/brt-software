<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\Modules\ExpenseClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseReleaseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeFund(float $balance = 1000): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create([
            'name'    => 'Test Fund',
            'gl_code' => 'PCF-RL-' . (++$seq),
            'balance' => $balance,
        ]);
    }

    private function makeExpense(string $status = 'approved', float $amount = 100): Expense
    {
        return Expense::create([
            'fund_id'      => $this->makeFund()->id,
            'expense_type' => 'operational',
            'amount'       => $amount,
            'expense_date' => now()->toDateString(),
            'status'       => $status,
            'added_by_id'  => $this->user->id,
        ]);
    }

    public function test_approved_expense_can_be_released(): void
    {
        $expense = $this->makeExpense('approved');
        $service = app(ExpenseClass::class);

        $result = $service->release($expense->id);

        $this->assertEquals('released', $expense->fresh()->status);
        $this->assertEquals('success', $result['status']);
    }

    public function test_non_approved_expense_returns_soft_error(): void
    {
        $expense = $this->makeExpense('recorded');
        $service = app(ExpenseClass::class);

        $result = $service->release($expense->id);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('recorded', $expense->fresh()->status);
    }

    public function test_release_fails_when_budget_exceeded(): void
    {
        $expense = $this->makeExpense('approved', 300);

        ExpenseBudget::create([
            'expense_type'  => 'operational',
            'month'         => now()->month,
            'year'          => now()->year,
            'amount'        => 200,
            'created_by_id' => $this->user->id,
        ]);

        $service = app(ExpenseClass::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->release($expense->id);
    }

    public function test_release_endpoint_returns_json_success(): void
    {
        $expense = $this->makeExpense('approved');

        $this->withoutMiddleware()
            ->patch("/expenses/{$expense->id}/release")
            ->assertJson(['status' => 'success']);

        $this->assertEquals('released', $expense->fresh()->status);
    }
}
