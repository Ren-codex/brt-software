<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\Modules\ExpenseClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseVoidTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeFund(float $balance = 1000.0): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create([
            'name'    => 'Fund',
            'gl_code' => 'PCF-V-' . (++$seq),
            'balance' => $balance,
        ]);
    }

    private function makeExpense(string $status, ?PettyCashFund $fund = null): Expense
    {
        return Expense::create([
            'fund_id'      => $fund?->id,
            'expense_type' => 'operational',
            'amount'       => 100,
            'expense_date' => now()->toDateString(),
            'status'       => $status,
            'added_by_id'  => $this->user->id,
        ]);
    }

    public function test_recorded_expense_can_be_voided_and_balance_restored(): void
    {
        $fund    = $this->makeFund(900);
        $expense = $this->makeExpense('recorded', $fund);
        $service = app(ExpenseClass::class);

        $result = $service->void($expense->id);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('voided', $expense->fresh()->status);
        $this->assertEquals(1000.0, $fund->fresh()->balance);
    }

    public function test_approved_expense_can_be_voided(): void
    {
        $fund    = $this->makeFund(900);
        $expense = $this->makeExpense('approved', $fund);
        $service = app(ExpenseClass::class);

        $result = $service->void($expense->id);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('voided', $expense->fresh()->status);
        $this->assertEquals(1000.0, $fund->fresh()->balance);
    }

    public function test_released_expense_returns_soft_error(): void
    {
        $expense = $this->makeExpense('released');
        $service = app(ExpenseClass::class);

        $result = $service->void($expense->id);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('released', $expense->fresh()->status);
    }

    public function test_void_without_fund_succeeds_without_error(): void
    {
        $expense = $this->makeExpense('recorded', null);
        $service = app(ExpenseClass::class);

        $result = $service->void($expense->id);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('voided', $expense->fresh()->status);
    }

    public function test_void_endpoint_returns_json_success(): void
    {
        $expense = $this->makeExpense('recorded');

        $this->withoutMiddleware()
            ->patch("/expenses/{$expense->id}/void")
            ->assertJson(['status' => 'success']);

        $this->assertEquals('voided', $expense->fresh()->status);
    }
}
