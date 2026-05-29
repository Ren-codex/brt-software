<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\Modules\ExpenseClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeFund(): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create([
            'name'    => 'Fund',
            'gl_code' => 'PCF-AP-' . (++$seq),
            'balance' => 1000,
        ]);
    }

    private function makeExpense(string $status = 'recorded'): Expense
    {
        return Expense::create([
            'fund_id'      => $this->makeFund()->id,
            'expense_type' => 'operational',
            'amount'       => 100,
            'expense_date' => now()->toDateString(),
            'status'       => $status,
            'added_by_id'  => $this->user->id,
        ]);
    }

    public function test_recorded_expense_can_be_approved(): void
    {
        $expense = $this->makeExpense('recorded');
        $service = app(ExpenseClass::class);

        $result = $service->approve($expense->id);

        $this->assertEquals('approved', $expense->fresh()->status);
        $this->assertEquals('success', $result['status']);
    }

    public function test_non_recorded_expense_returns_soft_error(): void
    {
        $expense = $this->makeExpense('submitted');
        $service = app(ExpenseClass::class);

        $result = $service->approve($expense->id);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('submitted', $expense->fresh()->status);
    }

    public function test_approve_endpoint_returns_json_success(): void
    {
        $expense = $this->makeExpense('recorded');

        $this->withoutMiddleware()
            ->patch("/expenses/{$expense->id}/approve")
            ->assertJson(['status' => 'success']);

        $this->assertEquals('approved', $expense->fresh()->status);
    }
}
