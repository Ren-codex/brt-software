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

class PettyCashVoucherValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function encoderUser(): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'accounting')->firstOrFail();
        $submodule = $module->submodules()->where('key', 'petty_cash')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submodule->id, 'access_level' => 'encoder',
        ]);

        return $user;
    }

    private function voucherPayload(PettyCashFund $fund, array $overrides = []): array
    {
        return array_merge([
            'fund_id'      => $fund->id,
            'expense_date' => now()->toDateString(),
            'payee'        => 'Test Payee',
            'expense_type' => 'operational',
            'amount'       => 100,
        ], $overrides);
    }

    public function test_voucher_rejected_for_inactive_fund(): void
    {
        $fund = PettyCashFund::create(['name' => 'Retired Fund', 'gl_code' => 'PCF-'.uniqid(), 'balance' => 1000, 'is_active' => false]);
        $user = $this->encoderUser();

        $response = $this->actingAs($user)->postJson('/accounting/petty-cash/vouchers', $this->voucherPayload($fund));

        $response->assertStatus(422)->assertJsonFragment([
            'message' => "The selected fund 'Retired Fund' is inactive and cannot receive new vouchers.",
        ]);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertEquals(1000, $fund->fresh()->balance);
    }

    public function test_voucher_rejected_when_amount_exceeds_balance(): void
    {
        $fund = PettyCashFund::create(['name' => 'Main Fund', 'gl_code' => 'PCF-'.uniqid(), 'balance' => 50, 'is_active' => true]);
        $user = $this->encoderUser();

        $response = $this->actingAs($user)->postJson('/accounting/petty-cash/vouchers', $this->voucherPayload($fund, ['amount' => 100]));

        $response->assertStatus(422);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_voucher_accepted_for_active_fund_with_sufficient_balance(): void
    {
        $fund = PettyCashFund::create(['name' => 'Main Fund', 'gl_code' => 'PCF-'.uniqid(), 'balance' => 1000, 'is_active' => true]);
        $user = $this->encoderUser();

        $response = $this->actingAs($user)->postJson('/accounting/petty-cash/vouchers', $this->voucherPayload($fund, ['amount' => 100]));

        $response->assertOk();
        $this->assertEquals(900, $fund->fresh()->balance);
    }
}
