<?php

namespace Tests\Feature\Libraries;

use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\User;
use App\Services\Libraries\FundClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FundManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeFund(array $overrides = []): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create(array_merge([
            'name'          => 'Test Fund',
            'gl_code'       => 'PCF-' . (++$seq),
            'balance'       => 500.00,
            'weekly_budget' => 0,
            'is_active'     => true,
            'created_by_id' => $this->user->id,
        ], $overrides));
    }

    // --- create ---

    public function test_fund_can_be_created(): void
    {
        $service = app(FundClass::class);
        $request = \Illuminate\Http\Request::create('/libraries/funds', 'POST', [
            'name'          => 'Main Fund',
            'gl_code'       => 'PCF-001',
            'weekly_budget' => 1000,
        ]);

        $result = $service->save($request, $this->user->id);

        $this->assertEquals('Main Fund', $result['data']->name);
        $this->assertEquals(0.0, $result['data']->balance);
        $this->assertDatabaseHas('petty_cash_funds', ['gl_code' => 'PCF-001', 'balance' => 0]);
    }

    // --- top-up ---

    public function test_top_up_increments_balance_and_records_transaction(): void
    {
        $fund    = $this->makeFund(['balance' => 100]);
        $service = app(FundClass::class);
        $request = \Illuminate\Http\Request::create('/libraries/funds/1/top-up', 'POST', [
            'amount'      => 500,
            'date'        => now()->toDateString(),
            'description' => 'Monthly replenishment',
        ]);

        $service->topUp($fund->id, $request);

        $this->assertEquals(600.0, $fund->fresh()->balance);
        $this->assertDatabaseHas('petty_cash_transactions', [
            'fund_id' => $fund->id,
            'type'    => 'replenishment',
            'amount'  => 500,
        ]);
    }

    // --- adjust balance ---

    public function test_adjust_balance_sets_balance_directly_without_transaction(): void
    {
        $fund    = $this->makeFund(['balance' => 100]);
        $service = app(FundClass::class);
        $request = \Illuminate\Http\Request::create('/libraries/funds/1/balance', 'PATCH', [
            'balance' => 250,
            'reason'  => 'Cash count correction',
        ]);

        $service->adjustBalance($fund->id, $request);

        $this->assertEquals(250.0, $fund->fresh()->balance);
        $this->assertEquals(0, PettyCashTransaction::where('fund_id', $fund->id)->count());
    }

    // --- toggle active ---

    public function test_toggle_active_deactivates_fund(): void
    {
        $fund    = $this->makeFund(['is_active' => true]);
        $service = app(FundClass::class);

        $service->toggleActive($fund->id, false);

        $this->assertFalse($fund->fresh()->is_active);
    }

    // --- inactive fund guard in ExpenseClass ---

    public function test_recording_expense_on_inactive_fund_throws_validation_exception(): void
    {
        $fund = $this->makeFund(['balance' => 1000, 'is_active' => false]);

        $request = \Illuminate\Http\Request::create('/expenses', 'POST', [
            'fund_id'      => $fund->id,
            'expense_type' => 'operational',
            'amount'       => '100',
            'expense_date' => now()->toDateString(),
            'status'       => 'pending',
        ]);

        $this->expectException(ValidationException::class);

        app(\App\Services\Modules\ExpenseClass::class)->save($request, $this->user->id);
    }
}
