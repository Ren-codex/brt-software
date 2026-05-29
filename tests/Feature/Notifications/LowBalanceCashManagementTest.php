<?php

namespace Tests\Feature\Notifications;

use App\Models\ListRole;
use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
use App\Services\Accounting\CashManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowBalanceCashManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => '', 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id, ['added_by_id' => $this->admin->id]);
        $this->actingAs($this->admin);
    }

    private function makeFund(array $attrs = []): PettyCashFund
    {
        static $seq = 0;

        return PettyCashFund::create(array_merge([
            'name' => 'Test Fund',
            'gl_code' => 'PCF-CMS-'.(++$seq),
            'balance' => 1000,
            'low_balance_threshold' => 500,
            'created_by_id' => $this->admin->id,
        ], $attrs));
    }

    public function test_disbursement_fires_notification_on_threshold_crossing(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 600, 'low_balance_threshold' => 500]);

        app(CashManagementService::class)->addTransaction($fund, [
            'type' => 'disbursement',
            'amount' => 200,
            'transaction_date' => now()->toDateString(),
        ]);

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
    }

    public function test_disbursement_does_not_fire_when_no_threshold_crossing(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 1000, 'low_balance_threshold' => 500]);

        app(CashManagementService::class)->addTransaction($fund, [
            'type' => 'disbursement',
            'amount' => 100,
            'transaction_date' => now()->toDateString(),
        ]);

        Notification::assertNothingSent();
    }

    public function test_delete_replenishment_fires_notification_when_balance_crosses_threshold(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 600, 'low_balance_threshold' => 500]);
        $txn = PettyCashTransaction::create([
            'transaction_no' => 'TXN-TEST-001',
            'fund_id' => $fund->id,
            'type' => 'replenishment',
            'amount' => 200,
            'transaction_date' => now()->toDateString(),
            'created_by_id' => $this->admin->id,
        ]);

        app(CashManagementService::class)->deleteTransaction($txn->id);

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
    }
}
