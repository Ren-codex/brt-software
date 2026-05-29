<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\Modules\ExpenseClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpenseModuleTest extends TestCase
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
            'name'    => 'Test Fund',
            'gl_code' => 'PCF-TEST-' . (++$seq),
            'balance' => $balance,
        ]);
    }

    private function makeExpense(PettyCashFund $fund, float $amount = 100.0, string $status = 'approved'): Expense
    {
        return Expense::create([
            'fund_id'      => $fund->id,
            'expense_type' => 'operational',
            'amount'       => $amount,
            'expense_date' => now()->toDateString(),
            'status'       => $status,
            'added_by_id'  => $this->user->id,
        ]);
    }

    // --- Bug 1: ValidationException not imported in ExpenseClass ---

    public function test_release_throws_validation_exception_when_budget_exceeded(): void
    {
        $fund    = $this->makeFund(1000);
        $expense = $this->makeExpense($fund, 200, 'approved');

        ExpenseBudget::create([
            'expense_type'  => 'operational',
            'month'         => now()->month,
            'year'          => now()->year,
            'amount'        => 50,
            'created_by_id' => $this->user->id,
        ]);

        $this->expectException(ValidationException::class);

        app(ExpenseClass::class)->release($expense->id);
    }

    // --- Bug 2: status validation weakened to nullable|string ---

    public function test_update_request_rejects_unrecognised_status(): void
    {
        $fund    = $this->makeFund(1000);
        $expense = $this->makeExpense($fund);

        $this->withoutMiddleware()
            ->patch("/expenses/{$expense->id}", [
                'expense_type' => 'operational',
                'amount'       => 100,
                'expense_date' => now()->toDateString(),
                'status'       => 'totally_made_up',
            ])
            ->assertSessionHasErrors('status');
    }

    // --- Bug 3: fund balance not checked before decrement ---

    public function test_save_throws_validation_exception_when_amount_exceeds_fund_balance(): void
    {
        $fund = $this->makeFund(100);

        $request = Request::create('/expenses', 'POST', [
            'fund_id'      => $fund->id,
            'expense_type' => 'operational',
            'amount'       => '500',
            'expense_date' => now()->toDateString(),
            'status'       => 'pending',
        ]);

        $this->expectException(ValidationException::class);

        app(ExpenseClass::class)->save($request, $this->user->id);
    }
}
