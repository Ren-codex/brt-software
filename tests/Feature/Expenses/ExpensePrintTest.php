<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\PrintClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ExpensePrintTest extends TestCase
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
            'name'    => 'Test Fund',
            'gl_code' => 'PCF-PR-' . (++$seq),
            'balance' => 5000,
        ]);
    }

    private function makeExpense(array $attrs = []): Expense
    {
        return Expense::create(array_merge([
            'fund_id'      => $this->makeFund()->id,
            'expense_type' => 'operational',
            'amount'       => 100,
            'expense_date' => '2026-05-15',
            'status'       => 'recorded',
            'added_by_id'  => $this->user->id,
        ], $attrs));
    }

    public function test_print_returns_pdf_response(): void
    {
        $this->withoutMiddleware()
            ->get('/expenses/print')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_print_filtered_by_date_range(): void
    {
        $inRange  = $this->makeExpense(['expense_date' => '2026-05-15']);
        $outRange = $this->makeExpense(['expense_date' => '2026-04-15']);

        $request  = Request::create('/expenses/print', 'GET', [
            'date_from' => '2026-05-01',
            'date_to'   => '2026-05-31',
        ]);
        $expenses = app(PrintClass::class)->queryExpensesForPrint($request);

        $this->assertTrue($expenses->contains('id', $inRange->id));
        $this->assertFalse($expenses->contains('id', $outRange->id));
    }

    public function test_print_filtered_by_status(): void
    {
        $released = $this->makeExpense(['status' => 'released']);
        $recorded = $this->makeExpense(['status' => 'recorded']);

        $request  = Request::create('/expenses/print', 'GET', ['status' => 'released']);
        $expenses = app(PrintClass::class)->queryExpensesForPrint($request);

        $this->assertTrue($expenses->contains('id', $released->id));
        $this->assertFalse($expenses->contains('id', $recorded->id));
    }

    public function test_print_filtered_by_fund(): void
    {
        $fund1 = $this->makeFund();
        $fund2 = $this->makeFund();
        $e1    = $this->makeExpense(['fund_id' => $fund1->id]);
        $e2    = $this->makeExpense(['fund_id' => $fund2->id]);

        $request  = Request::create('/expenses/print', 'GET', ['fund_id' => $fund1->id]);
        $expenses = app(PrintClass::class)->queryExpensesForPrint($request);

        $this->assertTrue($expenses->contains('id', $e1->id));
        $this->assertFalse($expenses->contains('id', $e2->id));
    }

    public function test_print_filtered_by_expense_type(): void
    {
        $operational = $this->makeExpense(['expense_type' => 'operational']);
        $utilities   = $this->makeExpense(['expense_type' => 'utilities']);

        $request  = Request::create('/expenses/print', 'GET', ['expense_type' => 'operational']);
        $expenses = app(PrintClass::class)->queryExpensesForPrint($request);

        $this->assertTrue($expenses->contains('id', $operational->id));
        $this->assertFalse($expenses->contains('id', $utilities->id));
    }

    public function test_print_with_no_filters_returns_all_expenses(): void
    {
        $e1 = $this->makeExpense();
        $e2 = $this->makeExpense(['expense_type' => 'utilities']);

        $request  = Request::create('/expenses/print', 'GET', []);
        $expenses = app(PrintClass::class)->queryExpensesForPrint($request);

        $this->assertTrue($expenses->contains('id', $e1->id));
        $this->assertTrue($expenses->contains('id', $e2->id));
    }
}
