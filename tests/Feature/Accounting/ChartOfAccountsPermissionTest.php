<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountsPermissionTest extends TestCase
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
            $submodule = $module->submodules()->where('key', 'chart_of_accounts')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeAccount(): Account
    {
        return Account::create([
            'code' => 'ACC-' . uniqid(), 'slug' => 'acc-' . uniqid(),
            'name' => 'Test Account', 'type' => 'asset',
        ]);
    }

    public function test_settings_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/settings')->assertForbidden();
    }

    public function test_settings_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/settings')->assertOk();
    }

    public function test_store_account_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/accounts', [])->assertForbidden();
    }

    public function test_destroy_account_denied_without_admin_grant(): void
    {
        $account = $this->makeAccount();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete("/accounting/accounts/{$account->id}")->assertForbidden();
    }

    public function test_destroy_account_allowed_with_admin_grant(): void
    {
        $account = $this->makeAccount();
        $user = $this->administratorWithGrant('admin');

        $response = $this->actingAs($user)->delete("/accounting/accounts/{$account->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_bank_accounts_list_gated_by_the_same_grant(): void
    {
        $denied = $this->administratorWithGrant(null);
        $this->actingAs($denied)->get('/accounting/bank-accounts/list')->assertForbidden();

        $allowed = $this->administratorWithGrant('view');
        $this->actingAs($allowed)->get('/accounting/bank-accounts/list')->assertOk();
    }

    public function test_bank_accounts_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/bank-accounts', [])->assertForbidden();
    }
}
