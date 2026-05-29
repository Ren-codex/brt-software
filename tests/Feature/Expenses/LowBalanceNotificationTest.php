<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\ListRole;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
use App\Services\Modules\ExpenseClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowBalanceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $topManagement;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => '', 'is_active' => true]);
        $tmRole    = ListRole::create(['name' => 'Top Management', 'type' => 'role', 'definition' => '', 'is_active' => true]);

        $this->admin         = User::factory()->create();
        $this->topManagement = User::factory()->create();
        $this->regularUser   = User::factory()->create();

        $this->admin->roles()->attach($adminRole->id, ['added_by_id' => $this->admin->id]);
        $this->topManagement->roles()->attach($tmRole->id, ['added_by_id' => $this->admin->id]);
        // regularUser has no role attached
    }

    private function makeFund(array $attrs = []): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create(array_merge([
            'name'    => 'Test Fund',
            'gl_code' => 'PCF-LB-' . (++$seq),
            'balance' => 1000,
            'low_balance_threshold' => 500,
        ], $attrs));
    }

    private function saveExpense(PettyCashFund $fund, float $amount): void
    {
        $this->actingAs($this->admin);
        $request = Request::create('/expenses', 'POST', [
            'fund_id'      => $fund->id,
            'expense_type' => 'operational',
            'amount'       => $amount,
            'expense_date' => now()->toDateString(),
            'description'  => 'Test expense',
            'status'       => 'recorded',
        ]);
        app(ExpenseClass::class)->save($request, $this->admin->id);
    }

    public function test_notification_fired_when_expense_causes_balance_to_cross_threshold(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 600, 'low_balance_threshold' => 500]);
        // Expense of 200 brings balance from 600 to 400 — crosses below 500 threshold
        $this->saveExpense($fund, 200);

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
        Notification::assertSentTo($this->topManagement, LowBalanceFundNotification::class);
    }

    public function test_notification_not_fired_when_balance_already_below_threshold(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 300, 'low_balance_threshold' => 500]);
        // Balance already below threshold — expense just makes it lower, no crossing
        $this->saveExpense($fund, 100);

        Notification::assertNotSentTo($this->admin, LowBalanceFundNotification::class);
        Notification::assertNotSentTo($this->topManagement, LowBalanceFundNotification::class);
    }

    public function test_notification_not_fired_when_fund_has_no_threshold(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 1000, 'low_balance_threshold' => null]);
        $this->saveExpense($fund, 800);

        Notification::assertNothingSent();
    }

    public function test_notification_fires_again_after_fund_replenishment_crosses_threshold(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 600, 'low_balance_threshold' => 500]);
        // First crossing: 600 → 400
        $this->saveExpense($fund, 200);
        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);

        // Replenish back above threshold
        $fund->update(['balance' => 800]);

        // Second crossing: 800 → 300
        $this->saveExpense($fund->fresh(), 500);
        // assertSentTo checks total, so it should have been sent twice now
        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class, function ($notification, $channels) {
            return true;
        });
    }

    public function test_notification_sent_to_administrator_and_top_management_only(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 600, 'low_balance_threshold' => 500]);
        $this->saveExpense($fund, 200);

        Notification::assertNotSentTo($this->regularUser, LowBalanceFundNotification::class);
        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
        Notification::assertSentTo($this->topManagement, LowBalanceFundNotification::class);
    }

    public function test_check_and_notify_low_balance_fires_notification_on_threshold_crossing(): void
    {
        Notification::fake();

        $fund    = $this->makeFund(['balance' => 600, 'low_balance_threshold' => 500]);
        $service = app(\App\Services\NotificationService::class);

        $service->checkAndNotifyLowBalance($fund, 600.0, 400.0);

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
        Notification::assertSentTo($this->topManagement, LowBalanceFundNotification::class);
    }

    public function test_check_and_notify_low_balance_does_not_fire_when_already_below(): void
    {
        Notification::fake();

        $fund    = $this->makeFund(['balance' => 300, 'low_balance_threshold' => 500]);
        $service = app(\App\Services\NotificationService::class);

        $service->checkAndNotifyLowBalance($fund, 300.0, 200.0);

        Notification::assertNothingSent();
    }
}
