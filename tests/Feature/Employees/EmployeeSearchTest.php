<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\ListPosition;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $role = ListRole::create(['name' => 'Viewer', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $this->viewer = User::factory()->create(['username' => 'viewer01']);
        UserRole::create(['user_id' => $this->viewer->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $this->viewer->id]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => Module::where('key', 'employees')->firstOrFail()->id,
            'submodule_id' => null, 'access_level' => 'view',
        ]);
    }

    private function employee(array $attributes): Employee
    {
        return Employee::create(array_merge([
            'mobile' => '09000000000', 'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'n/a',
        ], $attributes));
    }

    private function search(string $keyword): array
    {
        return $this->actingAs($this->viewer)
            ->getJson('/employees?option=lists&count=10&keyword=' . urlencode($keyword))
            ->assertOk()
            ->json('data.*.firstname');
    }

    public function test_search_narrows_the_list_by_name_mobile_and_position(): void
    {
        $driver = ListPosition::create(['title' => 'Delivery Driver', 'slug' => 'delivery-driver', 'rate_per_day' => 500]);
        $this->employee(['firstname' => 'Ana', 'lastname' => 'Cruz', 'mobile' => '09171112222']);
        $this->employee(['firstname' => 'Ben', 'lastname' => 'Reyes', 'position_id' => $driver->id]);

        $this->assertSame(['Ana'], $this->search('cruz'));
        $this->assertSame(['Ana'], $this->search('0917111'));
        $this->assertSame(['Ben'], $this->search('DRIVER'));
        $this->assertSame([], $this->search('nobody-by-this-name'));
    }
}
