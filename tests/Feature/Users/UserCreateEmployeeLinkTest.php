<?php

namespace Tests\Feature\Users;

use App\Models\Employee;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An account with no employee record can't save its own profile (only an
 * admin gets one made for them there), so User Management must not create a
 * non-admin account without linking it to an employee.
 */
class UserCreateEmployeeLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $role = $this->role('Administrator');
        $this->manager = User::factory()->create();
        UserRole::create(['user_id' => $this->manager->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $this->manager->id]);
        RolePermission::create([
            'role_id' => $role->id,
            'module_id' => Module::where('key', 'user_management')->firstOrFail()->id,
            'submodule_id' => null,
            'access_level' => 'admin',
        ]);
    }

    private function role(string $name): ListRole
    {
        return ListRole::firstOrCreate(['name' => $name], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'firstname' => 'Ana',
            'lastname' => 'Cruz',
            'mobile' => '09170000000',
            'birthdate' => '1990-01-01',
            'sex' => 'Female',
            'religion' => 'None',
        ], $attributes));
    }

    public function test_a_non_admin_account_needs_an_employee(): void
    {
        $this->actingAs($this->manager)
            ->post('/users', [
                'username' => 'cashier1',
                'email' => 'cashier1@example.com',
                'role_ids' => [$this->role('Cashier')->id],
            ])
            ->assertSessionHasErrors('employee_id');

        $this->assertDatabaseMissing('users', ['username' => 'cashier1']);
    }

    public function test_a_non_admin_account_linked_to_an_employee_is_created(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->manager)
            ->post('/users', [
                'email' => 'ana@example.com',
                'employee_id' => $employee->id,
                'role_ids' => [$this->role('Cashier')->id],
            ])
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame($user->id, $employee->fresh()->user_id);
    }

    public function test_an_admin_account_may_be_made_without_an_employee(): void
    {
        $this->actingAs($this->manager)
            ->post('/users', [
                'username' => 'admin2',
                'email' => 'admin2@example.com',
                'role_ids' => [$this->role('Mini Admin')->id, $this->role('Cashier')->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'admin2']);
    }

    public function test_an_employee_who_already_has_an_account_is_refused(): void
    {
        $employee = $this->employee(['user_id' => $this->manager->id]);

        $this->actingAs($this->manager)
            ->post('/users', [
                'username' => 'cashier2',
                'email' => 'cashier2@example.com',
                'employee_id' => $employee->id,
                'role_ids' => [$this->role('Cashier')->id],
            ])
            ->assertSessionHasErrors('employee_id');

        $this->assertDatabaseMissing('users', ['username' => 'cashier2']);
    }
}
