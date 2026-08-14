<?php

namespace Tests\Feature\Payroll;

use App\Models\ListPayrollItem;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollSettingsPermissionTest extends TestCase
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
            $module = Module::where('key', 'payroll')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'payroll_settings')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makePayrollItem(): ListPayrollItem
    {
        return ListPayrollItem::create(['slug' => 'test-item-' . uniqid(), 'name' => 'Test Item', 'type' => 'earning']);
    }

    public function test_payroll_settings_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/payroll-settings?option=lists')->assertForbidden();
    }

    public function test_payroll_settings_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->getJson('/payroll-settings?option=lists')->assertOk();
    }

    public function test_payroll_settings_update_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->put('/payroll-settings/1', [])->assertForbidden();
    }

    public function test_payroll_items_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/libraries/payroll-items?option=lists')->assertForbidden();
    }

    public function test_payroll_items_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->getJson('/libraries/payroll-items?option=lists')->assertOk();
    }

    public function test_payroll_items_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/libraries/payroll-items', [])->assertForbidden();
    }

    public function test_payroll_items_toggle_active_denied_without_encoder_grant(): void
    {
        $item = $this->makePayrollItem();
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->patch("/libraries/payroll-items/{$item->id}/toggle-active")->assertForbidden();
    }

    public function test_payroll_items_destroy_denied_without_admin_grant(): void
    {
        $item = $this->makePayrollItem();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete("/libraries/payroll-items/{$item->id}")->assertForbidden();
    }

    public function test_payroll_items_destroy_allowed_with_admin_grant(): void
    {
        $item = $this->makePayrollItem();
        $user = $this->administratorWithGrant('admin');

        $response = $this->actingAs($user)->delete("/libraries/payroll-items/{$item->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
