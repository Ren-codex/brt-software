<?php

namespace Tests\Feature\Libraries;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
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

    private function fundCashOnHand(float $amount): void
    {
        $cash = Account::firstOrCreate(['slug' => 'cash'], ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'current_asset', 'is_active' => true]);
        $equity = Account::firstOrCreate(['slug' => 'opening-balance-equity'], ['code' => '3900', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'subtype' => 'opening_balance', 'is_active' => true]);

        $entry = JournalEntry::create([
            'journal_number' => 'JE-TEST-FUND-' . uniqid(),
            'entry_date'     => now()->toDateString(),
            'entry_type'     => 'manual',
            'status'         => 'posted',
            'posted_at'      => now(),
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $cash->id, 'line_type' => 'debit', 'amount' => $amount, 'line_order' => 1]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $equity->id, 'line_type' => 'credit', 'amount' => $amount, 'line_order' => 2]);
    }

    private function makeFund(array $overrides = []): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create(array_merge([
            'name'          => 'Test Fund',
            'gl_code'       => 'PCF-' . (++$seq),
            'balance'       => 500.00,
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
        ]);

        $result = $service->save($request, $this->user->id);

        $this->assertEquals('Main Fund', $result['data']->name);
        $this->assertEquals(0.0, $result['data']->balance);
        $this->assertDatabaseHas('petty_cash_funds', ['gl_code' => 'PCF-001', 'balance' => 0]);
    }

    // --- top-up ---

    public function test_top_up_increments_balance_and_records_transaction(): void
    {
        $this->fundCashOnHand(1000);
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

    public function test_adjust_balance_persists_audit_trail_and_journal_entry(): void
    {
        $fund    = $this->makeFund(['balance' => 100]);
        $service = app(FundClass::class);
        $request = \Illuminate\Http\Request::create('/libraries/funds/1/balance', 'PATCH', [
            'balance' => 250,
            'reason'  => 'Cash count correction',
        ]);

        $service->adjustBalance($fund->id, $request);

        $this->assertEquals(250.0, $fund->fresh()->balance);

        $txn = PettyCashTransaction::where('fund_id', $fund->id)->where('category', 'cash_count_adjustment')->first();
        $this->assertNotNull($txn);
        $this->assertEquals('replenishment', $txn->type);
        $this->assertEquals(150.0, $txn->amount);
        $this->assertEquals('Cash count correction', $txn->description);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => PettyCashTransaction::class,
            'source_id'   => $txn->id,
            'entry_type'  => 'petty_cash_adjustment',
        ]);
    }

    public function test_adjust_balance_no_change_creates_no_transaction(): void
    {
        $fund    = $this->makeFund(['balance' => 100]);
        $service = app(FundClass::class);
        $request = \Illuminate\Http\Request::create('/libraries/funds/1/balance', 'PATCH', [
            'balance' => 100,
            'reason'  => 'Recount, no discrepancy',
        ]);

        $service->adjustBalance($fund->id, $request);

        $this->assertEquals(100.0, $fund->fresh()->balance);
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
