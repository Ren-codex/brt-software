<?php

namespace Tests\Feature\Checks;

use App\Models\BankAccount;
use App\Models\Check;
use App\Services\Modules\CheckForecastClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The forecast answers one question: will the check I already wrote bounce?
 *
 * It must be per bank account — a healthy total across three banks says nothing
 * about whether the BDO check clears — and it must count only checks actually in
 * hand, never invoices someone hopes will be paid. A projection that
 * over-promises is worse than none.
 */
class CheckForecastTest extends TestCase
{
    use RefreshDatabase;

    private function bank(string $glCode, string $name): BankAccount
    {
        return BankAccount::firstOrCreate(['gl_code' => $glCode], ['bank_name' => $name, 'account_name' => 'BRT']);
    }

    private function issued(BankAccount $bank, string $date, float $amount, string $status = Check::STATUS_PENDING): Check
    {
        return Check::create([
            'direction' => Check::DIRECTION_ISSUED, 'check_number' => 'C' . uniqid(),
            'check_date' => $date, 'amount' => $amount, 'status' => $status,
            'bank_account_id' => $bank->id,
            'source_type' => 'App\Models\ReceivedStockPayment', 'source_id' => 1,
        ]);
    }

    private function received(?BankAccount $bank, string $date, float $amount, string $status = Check::STATUS_PENDING): Check
    {
        return Check::create([
            'direction' => Check::DIRECTION_RECEIVED, 'check_number' => 'C' . uniqid(),
            'check_date' => $date, 'amount' => $amount, 'status' => $status,
            'bank_account_id' => $bank?->id,
            'source_type' => 'App\Models\Receipt', 'source_id' => 1,
        ]);
    }

    public function test_it_projects_a_running_balance_by_check_date(): void
    {
        $bdo = $this->bank('1020', 'BDO');
        $this->received($bdo, now()->addDays(8)->toDateString(), 800000);
        $this->issued($bdo, now()->addDays(9)->toDateString(), 1000000);

        $account = collect(app(CheckForecastClass::class)->build()['accounts'])
            ->firstWhere('bank_account_id', $bdo->id);

        $rows = collect($account['rows']);

        $this->assertSame(800000.0, (float) $rows[0]['balance'], 'Money in lifts the balance on its own date.');
        $this->assertSame(-200000.0, (float) $rows[1]['balance'], 'The check out draws it down the next day.');
    }

    public function test_it_flags_a_date_that_goes_short(): void
    {
        $bdo = $this->bank('1020', 'BDO');
        $this->issued($bdo, now()->addDays(9)->toDateString(), 1000000);

        $rows = collect(collect(app(CheckForecastClass::class)->build()['accounts'])
            ->firstWhere('bank_account_id', $bdo->id)['rows']);

        $this->assertTrue($rows[0]['short'], 'A ₱1M check against a ₱0 balance must be flagged.');
    }

    public function test_each_bank_is_projected_on_its_own(): void
    {
        $bdo = $this->bank('1020', 'BDO');
        $bpi = $this->bank('1021', 'BPI');
        $this->received($bpi, now()->addDays(1)->toDateString(), 5000000);
        $this->issued($bdo, now()->addDays(9)->toDateString(), 1000000);

        $accounts = collect(app(CheckForecastClass::class)->build()['accounts']);

        $bdoRows = collect($accounts->firstWhere('bank_account_id', $bdo->id)['rows']);
        $this->assertTrue($bdoRows[0]['short'], 'Millions sitting in BPI must not make the BDO check look safe.');
    }

    public function test_cleared_and_bounced_checks_are_not_projected(): void
    {
        $bdo = $this->bank('1020', 'BDO');
        $this->issued($bdo, now()->addDays(5)->toDateString(), 900000, Check::STATUS_CLEARED);
        $this->issued($bdo, now()->addDays(6)->toDateString(), 700000, Check::STATUS_BOUNCED);

        $account = collect(app(CheckForecastClass::class)->build()['accounts'])
            ->firstWhere('bank_account_id', $bdo->id);

        $this->assertSame([], $account['rows'], 'Only money still to move belongs in a projection.');
    }

    public function test_depositing_a_check_assigns_it_to_that_bank(): void
    {
        $bdo = $this->bank('1020', 'BDO');
        $check = $this->received(null, now()->addDays(8)->toDateString(), 800000);
        $check->update(['check_number' => 'CHK-777']);

        app(\App\Services\Accounting\CashManagementService::class)->createBankDeposit([
            'cash_account_id' => \App\Models\Account::firstOrCreate(
                ['slug' => 'cash'],
                ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'current_asset']
            )->id,
            'bank_account_id' => $bdo->id,
            'amount' => 800000,
            'deposit_date' => now()->toDateString(),
            'deposit_type' => 'check',
            'check_date' => now()->addDays(8)->toDateString(),
            'check_number' => 'CHK-777',
        ]);

        $forecast = app(CheckForecastClass::class)->build();
        $account = collect($forecast['accounts'])->firstWhere('bank_account_id', $bdo->id);

        $this->assertSame(0.0, (float) $forecast['unassigned_received'], 'Depositing it says which bank it lands in.');
        $this->assertSame(800000.0, (float) collect($account['rows'])->first()['in']);
    }

    public function test_a_received_check_with_no_bank_yet_is_reported_not_counted(): void
    {
        $bdo = $this->bank('1020', 'BDO');
        $this->received(null, now()->addDays(2)->toDateString(), 300000);
        $this->issued($bdo, now()->addDays(9)->toDateString(), 1000000);

        $forecast = app(CheckForecastClass::class)->build();
        $rows = collect(collect($forecast['accounts'])->firstWhere('bank_account_id', $bdo->id)['rows']);

        $this->assertTrue($rows[0]['short'], 'An unassigned check cannot be assumed to land in this bank.');
        $this->assertSame(300000.0, (float) $forecast['unassigned_received'], 'But it must be reported, not hidden.');
    }
}
