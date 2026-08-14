<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function userWithGrant(?string $level): User
    {
        $role = ListRole::create(['name' => 'Test Role ' . uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'employees')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => null, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeEmployee(): Employee
    {
        return Employee::create([
            'lastname' => 'Test', 'firstname' => 'Employee' . uniqid(),
            'mobile' => '09000000000', 'birthdate' => '1990-01-01', 'sex' => 'male', 'religion' => 'n/a',
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->userWithGrant(null);

        $this->actingAs($user)->getJson('/employees?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->userWithGrant('view');

        $this->actingAs($user)->getJson('/employees?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->userWithGrant('view');

        $this->actingAs($user)->post('/employees', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->userWithGrant('encoder');

        $response = $this->actingAs($user)->post('/employees', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $employee = $this->makeEmployee();
        $user = $this->userWithGrant('encoder');

        $this->actingAs($user)->delete("/employees/{$employee->id}")->assertForbidden();
    }

    public function test_destroy_allowed_with_admin_grant(): void
    {
        $employee = $this->makeEmployee();
        $user = $this->userWithGrant('admin');

        $response = $this->actingAs($user)->delete("/employees/{$employee->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_salaries_list_gated_by_the_same_employees_grant(): void
    {
        $denied = $this->userWithGrant(null);
        $this->actingAs($denied)->getJson('/libraries/salaries?option=lists')->assertForbidden();

        // Salaries still sits behind the existing role:Administrator gate too —
        // the fine-grained grant alone isn't enough, matching Inventory's pattern.
        $adminRole = ListRole::firstOrCreate(['name' => 'Administrator'], ['type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $allowed = $this->userWithGrant('view');
        UserRole::create(['user_id' => $allowed->id, 'role_id' => $adminRole->id, 'is_active' => 1, 'added_by_id' => $allowed->id]);
        $this->actingAs($allowed)->getJson('/libraries/salaries?option=lists')->assertOk();
    }

    public function test_positions_list_gated_by_the_same_employees_grant(): void
    {
        $denied = $this->userWithGrant(null);
        $this->actingAs($denied)->getJson('/libraries/positions?option=lists')->assertForbidden();

        $adminRole = ListRole::firstOrCreate(['name' => 'Administrator'], ['type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $allowed = $this->userWithGrant('view');
        UserRole::create(['user_id' => $allowed->id, 'role_id' => $adminRole->id, 'is_active' => 1, 'added_by_id' => $allowed->id]);
        $this->actingAs($allowed)->getJson('/libraries/positions?option=lists')->assertOk();
    }
}
