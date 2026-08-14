<?php

namespace Tests\Feature\Accounting;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\PettyCashFund;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PettyCashPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'petty_cash')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeFund(): PettyCashFund
    {
        return PettyCashFund::create(['name' => 'Main Fund', 'gl_code' => 'PCF-' . uniqid()]);
    }

    public function test_index_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/petty-cash')->assertForbidden();
    }

    public function test_index_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/petty-cash')->assertOk();
    }

    public function test_store_voucher_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/petty-cash/vouchers', [])->assertForbidden();
    }

    public function test_void_voucher_denied_without_approver_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete('/accounting/petty-cash/vouchers/1')->assertForbidden();
    }

    public function test_fund_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/accounting/funds?option=lists')->assertForbidden();
    }

    public function test_fund_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/funds', [])->assertForbidden();
    }

    public function test_fund_top_up_denied_without_encoder_grant(): void
    {
        $fund = $this->makeFund();
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post("/accounting/funds/{$fund->id}/top-up", [])->assertForbidden();
    }

    public function test_fund_adjust_balance_denied_without_approver_grant(): void
    {
        $fund = $this->makeFund();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->patch("/accounting/funds/{$fund->id}/balance", [])->assertForbidden();
    }

    public function test_fund_adjust_balance_allowed_with_approver_grant(): void
    {
        $fund = $this->makeFund();
        $user = $this->administratorWithGrant('approver');

        $response = $this->actingAs($user)->patch("/accounting/funds/{$fund->id}/balance", []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
