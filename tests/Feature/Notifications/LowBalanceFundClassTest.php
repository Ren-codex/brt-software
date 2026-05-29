<?php

namespace Tests\Feature\Notifications;

use App\Models\ListRole;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
use App\Services\Libraries\FundClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowBalanceFundClassTest extends TestCase
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

    public function test_adjust_balance_fires_notification_when_new_balance_crosses_threshold(): void
    {
        Notification::fake();

        $fund = PettyCashFund::create([
            'name' => 'Test Fund',
            'gl_code' => 'PCF-FC-001',
            'balance' => 600,
            'low_balance_threshold' => 500,
            'created_by_id' => $this->admin->id,
        ]);
        $request = Request::create('/funds/'.$fund->id.'/adjust', 'PATCH', [
            'id' => $fund->id,
            'balance' => 300,
        ]);

        app(FundClass::class)->adjustBalance($fund->id, $request);

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
    }

    public function test_adjust_balance_does_not_fire_when_already_below_threshold(): void
    {
        Notification::fake();

        $fund = PettyCashFund::create([
            'name' => 'Test Fund',
            'gl_code' => 'PCF-FC-002',
            'balance' => 200,
            'low_balance_threshold' => 500,
            'created_by_id' => $this->admin->id,
        ]);
        $request = Request::create('/funds/'.$fund->id.'/adjust', 'PATCH', [
            'id' => $fund->id,
            'balance' => 100,
        ]);

        app(FundClass::class)->adjustBalance($fund->id, $request);

        Notification::assertNothingSent();
    }
}
