<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\ListPosition;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use App\Services\System\User\UserClass;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Add Employee form's "Will this employee sign in?" switch: on, the
 * employee is saved with a login account linked to them, made the way User
 * Management makes one; off, no account.
 */
class EmployeeLoginAccountTest extends TestCase
{
    use RefreshDatabase;

    private ListRole $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
        $this->cashier = ListRole::create(['name' => 'Cashier', 'type' => 'role', 'definition' => 't', 'is_active' => true]);
    }

    /** @param array<string, string> $grants module key => access level */
    private function userWith(array $grants): User
    {
        $role = ListRole::create(['name' => 'Role '.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        foreach ($grants as $module => $level) {
            RolePermission::create([
                'role_id' => $role->id,
                'module_id' => Module::where('key', $module)->firstOrFail()->id,
                'submodule_id' => null,
                'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'firstname' => 'Joshua',
            'middlename' => 'Reyes',
            'lastname' => 'Santos',
            'email' => 'joshua@example.com',
            'mobile' => '09171234567',
            'birthdate' => '1995-03-29',
            'sex' => 'Male',
            'position_id' => ListPosition::create(['title' => 'Clerk', 'slug' => 'clerk-'.uniqid(), 'rate_per_day' => 500])->id,
            'is_regular' => 0,
            'is_active' => 1,
            'is_blacklisted' => 0,
        ], $overrides);
    }

    public function test_switching_it_on_saves_the_employee_with_a_linked_account(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder', 'user_management' => 'encoder']);

        $this->actingAs($encoder)
            ->post('/employees', $this->payload(['create_account' => 1, 'role_ids' => [$this->cashier->id]]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('password', UserClass::DEFAULT_PASSWORD);

        $employee = Employee::where('firstname', 'Joshua')->firstOrFail();
        $account = $employee->user;

        $this->assertNotNull($account);
        // Initials and birth date, as User Management makes it.
        $this->assertSame('jrs0329', $account->username);
        $this->assertSame('joshua@example.com', $account->email);
        $this->assertTrue((bool) $account->must_change);
        $this->assertTrue($account->roles()->whereKey($this->cashier->id)->exists());
        // So the Payroll Clerk column and the like show the full name.
        $this->assertSame('Joshua R. Santos', $account->employee->fullname);
    }

    public function test_left_off_the_employee_has_no_account(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder', 'user_management' => 'encoder']);
        $users = User::count();

        $this->actingAs($encoder)
            ->post('/employees', $this->payload(['create_account' => 0, 'email' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Employee::where('firstname', 'Joshua')->firstOrFail()->user_id);
        $this->assertSame($users, User::count());
    }

    public function test_an_account_needs_a_role(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder', 'user_management' => 'encoder']);

        $this->actingAs($encoder)
            ->post('/employees', $this->payload(['create_account' => 1, 'role_ids' => []]))
            ->assertSessionHasErrors('role_ids');

        $this->assertSame(0, Employee::count());
    }

    public function test_an_account_can_be_made_without_an_email(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder', 'user_management' => 'encoder']);

        $this->actingAs($encoder)
            ->post('/employees', $this->payload([
                'create_account' => 1, 'email' => null, 'role_ids' => [$this->cashier->id],
            ]))
            ->assertSessionHasNoErrors();

        $account = Employee::where('firstname', 'Joshua')->firstOrFail()->user;

        $this->assertSame('jrs0329', $account->username);
        $this->assertSame('', $account->email);
    }

    public function test_an_account_without_an_email_signs_in_by_username(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder', 'user_management' => 'encoder']);
        $this->actingAs($encoder)->post('/employees', $this->payload([
            'create_account' => 1, 'email' => null, 'role_ids' => [$this->cashier->id],
        ]));
        auth()->logout();

        $this->post('/login', ['email' => 'jrs0329', 'password' => UserClass::DEFAULT_PASSWORD]);

        $this->assertAuthenticatedAs(User::where('username', 'jrs0329')->firstOrFail());
    }

    public function test_it_needs_user_management_access(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder']);

        $this->actingAs($encoder)
            ->post('/employees', $this->payload(['create_account' => 1, 'role_ids' => [$this->cashier->id]]))
            ->assertSessionHasErrors('create_account');

        $this->assertSame(0, Employee::count());
    }

    public function test_an_email_another_account_uses_is_refused(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder', 'user_management' => 'encoder']);
        User::factory()->create(['email' => 'joshua@example.com']);

        $this->actingAs($encoder)
            ->post('/employees', $this->payload(['create_account' => 1, 'role_ids' => [$this->cashier->id]]))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Employee::count());
    }

    // ── Edit Employee: linking an account that already exists ──

    private function unlinkedEmployee(): Employee
    {
        return Employee::create([
            'firstname' => 'Joshua', 'middlename' => 'Reyes', 'lastname' => 'Santos', 'mobile' => '09171234567',
            'birthdate' => '1995-03-29', 'sex' => 'Male', 'religion' => 'None', 'is_regular' => 0, 'is_active' => 1,
            'position_id' => ListPosition::create(['title' => 'Clerk', 'slug' => 'clerk-'.uniqid(), 'rate_per_day' => 500])->id,
        ]);
    }

    private function edit(User $as, Employee $employee, array $overrides)
    {
        return $this->actingAs($as)->put('/employees/'.$employee->id, array_merge([
            // The form sends the id in the body too; the update reads it there.
            'id' => $employee->id,
            'firstname' => $employee->firstname, 'middlename' => $employee->middlename, 'lastname' => $employee->lastname,
            'mobile' => $employee->mobile, 'birthdate' => '1995-03-29', 'sex' => $employee->sex,
            'position_id' => $employee->position_id, 'is_regular' => 0, 'is_active' => 1, 'is_blacklisted' => 0,
            'account_id' => $employee->user_id,
        ], $overrides));
    }

    public function test_editing_links_an_existing_account(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder', 'user_management' => 'encoder']);
        $account = User::factory()->create(['username' => 'joshu@29']);
        $employee = $this->unlinkedEmployee();

        $this->edit($encoder, $employee, ['account_id' => $account->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('info', "Linked to the account joshu@29. It now shows this employee's name.");

        $this->assertSame($account->id, $employee->fresh()->user_id);
        // What the Payroll Clerk column reads.
        $this->assertSame('Joshua R. Santos', $account->fresh()->employee->fullname);
    }

    public function test_an_account_another_employee_has_is_refused(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder', 'user_management' => 'encoder']);
        $taken = User::factory()->create();
        Employee::create(['firstname' => 'Ana', 'lastname' => 'Cruz', 'mobile' => '09170000009', 'birthdate' => '1990-01-01',
            'sex' => 'Female', 'religion' => 'None', 'user_id' => $taken->id]);
        $employee = $this->unlinkedEmployee();

        $this->edit($encoder, $employee, ['account_id' => $taken->id])->assertSessionHasErrors('account_id');

        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_clearing_the_picker_unlinks_the_account(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder', 'user_management' => 'encoder']);
        $employee = $this->unlinkedEmployee();
        $employee->update(['user_id' => User::factory()->create()->id]);

        $this->edit($encoder, $employee, ['account_id' => null])->assertSessionHasNoErrors();

        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_changing_the_link_needs_user_management_access_but_a_plain_edit_does_not(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder']);
        $employee = $this->unlinkedEmployee();

        // The picker left as it was: an ordinary edit.
        $this->edit($encoder, $employee, ['lastname' => 'Santos-Cruz'])->assertSessionHasNoErrors();
        $this->assertSame('Santos-Cruz', $employee->fresh()->lastname);

        $this->edit($encoder, $employee->fresh(), ['account_id' => User::factory()->create()->id])
            ->assertSessionHasErrors('account_id');
        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_the_picker_lists_only_accounts_nobody_is_linked_to(): void
    {
        $encoder = $this->userWith(['employees' => 'encoder', 'user_management' => 'encoder']);
        User::factory()->create(['username' => 'joshu@29']);
        $linked = User::factory()->create(['username' => 'linked01']);
        Employee::create(['firstname' => 'Ana', 'lastname' => 'Cruz', 'mobile' => '09170000009', 'birthdate' => '1990-01-01',
            'sex' => 'Female', 'religion' => 'None', 'user_id' => $linked->id]);

        $names = collect($this->actingAs($encoder)->getJson('/employees?option=unlinked_accounts')->assertOk()->json())
            ->pluck('name');

        $this->assertTrue($names->contains(fn ($name) => str_starts_with($name, 'joshu@29')));
        $this->assertFalse($names->contains(fn ($name) => str_starts_with($name, 'linked01')));

        $this->actingAs($this->userWith(['employees' => 'encoder']))
            ->getJson('/employees?option=unlinked_accounts')
            ->assertForbidden();
    }
}
