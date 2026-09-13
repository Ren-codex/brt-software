<?php

namespace Tests\Feature\Checks;

use App\Models\Check;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Modules\CheckRegisterClass;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A rep must not be able to discharge their own check. If they could, "cleared"
 * would mean nothing — the whole point is that someone else confirms the money
 * actually arrived.
 */
class CheckRegisterPermissionTest extends TestCase
{
    use RefreshDatabase;
    use MakesCheckFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function userWith(?string $moduleKey, ?string $submoduleKey, ?string $level): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($moduleKey) {
            $module = Module::where('key', $moduleKey)->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id,
                'module_id' => $module->id,
                'submodule_id' => $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null,
                'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function pendingCheck(): Check
    {
        $receipt = $this->receipt('Check', '000123');

        return app(CheckRegisterClass::class)->registerReceived($receipt);
    }

    public function test_the_register_is_closed_without_a_grant(): void
    {
        $this->actingAs($this->userWith(null, null, null))
            ->get('/accounting/check-register')
            ->assertForbidden();
    }

    public function test_view_grant_opens_the_register(): void
    {
        $this->actingAs($this->userWith('accounting', 'check_register', 'view'))
            ->get('/accounting/check-register')
            ->assertOk();
    }

    public function test_a_viewer_cannot_confirm_a_check(): void
    {
        $check = $this->pendingCheck();

        $this->actingAs($this->userWith('accounting', 'check_register', 'view'))
            ->put('/accounting/check-register/' . $check->id . '/confirm')
            ->assertForbidden();

        $this->assertSame(Check::STATUS_PENDING, $check->fresh()->status);
    }

    public function test_a_viewer_cannot_bounce_a_check(): void
    {
        $check = $this->pendingCheck();

        $this->actingAs($this->userWith('accounting', 'check_register', 'view'))
            ->put('/accounting/check-register/' . $check->id . '/bounce', ['bounce_reason' => 'Insufficient funds'])
            ->assertForbidden();

        $this->assertSame(Check::STATUS_PENDING, $check->fresh()->status);
    }

    public function test_a_sales_rep_cannot_reach_the_register_at_all(): void
    {
        // A module-wide sales grant is what a rep actually holds. It must not
        // carry over into Accounting.
        $this->actingAs($this->userWith('sales', null, 'encoder'))
            ->get('/accounting/check-register')
            ->assertForbidden();
    }

    public function test_an_approver_can_bounce(): void
    {
        $check = $this->pendingCheck();

        $this->actingAs($this->userWith('accounting', 'check_register', 'approver'))
            ->put('/accounting/check-register/' . $check->id . '/bounce', ['bounce_reason' => 'Insufficient funds'])
            ->assertOk();

        $this->assertSame(Check::STATUS_BOUNCED, $check->fresh()->status);
    }
}
