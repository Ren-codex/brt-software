<?php

namespace Tests\Feature\Payroll;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\PayrollTemplate;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTemplatePermissionTest extends TestCase
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
            $submodule = $module->submodules()->where('key', 'payroll_templates')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeTemplate(): PayrollTemplate
    {
        return PayrollTemplate::create([
            'name' => 'Test Template ' . uniqid(),
            'created_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/payroll-templates?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->getJson('/payroll-templates?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/payroll-templates', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $response = $this->actingAs($user)->post('/payroll-templates', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_add_employees_denied_without_encoder_grant(): void
    {
        $template = $this->makeTemplate();
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post("/payroll-templates/{$template->id}/add-employees", [])->assertForbidden();
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $template = $this->makeTemplate();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete("/payroll-templates/{$template->id}")->assertForbidden();
    }

    public function test_destroy_allowed_with_admin_grant(): void
    {
        $template = $this->makeTemplate();
        $user = $this->administratorWithGrant('admin');

        $response = $this->actingAs($user)->delete("/payroll-templates/{$template->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
