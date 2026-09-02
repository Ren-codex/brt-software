<?php

namespace Tests\Feature\Notifications;

use App\Models\ListRole;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowBalanceNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $topManagement;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => '', 'is_active' => true]);
        $tmRole = ListRole::create(['name' => 'Top Management', 'type' => 'role', 'definition' => '', 'is_active' => true]);

        $this->admin = User::factory()->create();
        $this->topManagement = User::factory()->create();

        $this->admin->roles()->attach($adminRole->id, ['added_by_id' => $this->admin->id]);
        $this->topManagement->roles()->attach($tmRole->id, ['added_by_id' => $this->admin->id]);
    }

    private function makeFund(array $attrs = []): PettyCashFund
    {
        static $seq = 0;

        return PettyCashFund::create(array_merge([
            'name' => 'Test Fund',
            'gl_code' => 'PCF-NS-'.(++$seq),
            'balance' => 1000,
            'low_balance_threshold' => 500,
        ], $attrs));
    }

    public function test_check_and_notify_low_balance_fires_notification_on_threshold_crossing(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 600, 'low_balance_threshold' => 500]);
        $service = app(NotificationService::class);

        $service->checkAndNotifyLowBalance($fund, 600.0, 400.0);

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
        Notification::assertSentTo($this->topManagement, LowBalanceFundNotification::class);
    }

    public function test_check_and_notify_low_balance_does_not_fire_when_already_below(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 300, 'low_balance_threshold' => 500]);
        $service = app(NotificationService::class);

        $service->checkAndNotifyLowBalance($fund, 300.0, 200.0);

        Notification::assertNothingSent();
    }
}
