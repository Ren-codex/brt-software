<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\ListPosition;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use App\Services\DropdownClass;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * People here do more than one job. A sales rep takes the wheel when a driver
 * is out; a driver covers a second truck's route.
 *
 * An employee still has one position, and it is the one that pays: the daily
 * rate lives on the position, and payroll reads that one and nothing else.
 * The extras say what else this person can be picked for, so the driver list
 * stops hiding somebody who drives.
 */
class SecondPositionTest extends TestCase
{
    use RefreshDatabase;

    private ListPosition $rep;

    private ListPosition $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $this->rep = ListPosition::create(['title' => 'Sales Rep', 'slug' => 'sales-rep', 'rate_per_day' => 600, 'is_regular' => 1, 'is_active' => 1]);
        $this->driver = ListPosition::create(['title' => 'Driver 2 Wingvan', 'slug' => 'driver-2', 'rate_per_day' => 450, 'is_regular' => 1, 'is_active' => 1]);
    }

    private function employee(ListPosition $primary, array $alsoWorksAs = []): Employee
    {
        $employee = Employee::create([
            'firstname' => 'Bea', 'lastname' => 'Cruz', 'mobile' => '0917'.random_int(1000000, 9999999),
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'position_id' => $primary->id, 'is_active' => 1,
        ]);

        $employee->syncExtraPositions(collect($alsoWorksAs)->pluck('id')->all());

        return $employee->fresh();
    }

    private function dropdown(string $name): array
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R'.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'sales_orders')->firstOrFail()->id,
            'access_level' => 'admin',
        ]);

        $this->actingAs($user);

        return collect(app(DropdownClass::class)->{$name}())->pluck('value')->all();
    }

    public function test_a_rep_who_also_drives_appears_in_the_driver_list(): void
    {
        $bothJobs = $this->employee($this->rep, [$this->driver]);

        $this->assertContains($bothJobs->id, $this->dropdown('drivers'));
        // And is still a rep, which is what they mostly are.
        $this->assertContains($bothJobs->id, $this->dropdown('sales_reps'));
    }

    public function test_the_primary_position_is_what_pays(): void
    {
        $bothJobs = $this->employee($this->rep, [$this->driver]);

        // The rep's rate, not the driver's, however many jobs they cover.
        $this->assertSame(600.0, (float) $bothJobs->position->rate_per_day);

        $row = collect(app(DropdownClass::class)->employees())->firstWhere('value', $bothJobs->id);
        $this->assertSame(600.0, (float) $row['basic_salary']);
        $this->assertSame('Sales Rep', $row['position_name']);
    }

    public function test_dropping_the_extra_takes_them_off_that_list(): void
    {
        $bothJobs = $this->employee($this->rep, [$this->driver]);
        $this->assertContains($bothJobs->id, $this->dropdown('drivers'));

        $bothJobs->syncExtraPositions([]);

        $this->assertNotContains($bothJobs->id, $this->dropdown('drivers'));
        $this->assertContains($bothJobs->id, $this->dropdown('sales_reps'));
    }

    public function test_the_primary_is_never_also_an_extra(): void
    {
        $employee = $this->employee($this->rep, [$this->rep, $this->driver]);

        // Holding the same position twice says nothing and would show the
        // title twice on screen.
        $this->assertSame([$this->driver->id], $employee->extraPositions->pluck('id')->all());
    }

    public function test_an_employee_with_one_job_is_unchanged(): void
    {
        $justARep = $this->employee($this->rep);

        $this->assertContains($justARep->id, $this->dropdown('sales_reps'));
        $this->assertNotContains($justARep->id, $this->dropdown('drivers'));
        $this->assertCount(0, $justARep->extraPositions);
    }

    public function test_the_form_saves_the_extras_and_payroll_ignores_them(): void
    {
        // The whole path: the form posts ids, the request lets them through,
        // the service stores them, and the rate still comes from the primary.
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R'.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'employees')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => null, 'access_level' => 'admin',
        ]);

        $this->actingAs($user)->post('/employees', [
            'firstname' => 'Bea', 'lastname' => 'Cruz', 'mobile' => '09171234567',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'address' => 'Zamboanga City',
            'position_id' => $this->rep->id,
            'extra_position_ids' => [$this->driver->id],
            'hours_per_day' => 8, 'overtime_rate' => 0,
            'is_regular' => 1, 'is_active' => 1, 'is_blacklisted' => 0,
        ])->assertSessionHasNoErrors();

        $saved = Employee::where('firstname', 'Bea')->firstOrFail();
        $this->assertSame([$this->driver->id], $saved->extraPositions->pluck('id')->all());
        $this->assertSame($this->rep->id, $saved->position_id);
        $this->assertContains($saved->id, $this->dropdown('drivers'));

        // Paid as a rep, whatever else they cover.
        $row = collect(app(DropdownClass::class)->employees())->firstWhere('value', $saved->id);
        $this->assertSame(600.0, (float) $row['basic_salary']);
    }

    public function test_a_driver_covering_a_second_truck_shows_once(): void
    {
        $secondTruck = ListPosition::create(['title' => 'Driver 1 Elf', 'slug' => 'driver-1', 'rate_per_day' => 450, 'is_regular' => 1, 'is_active' => 1]);
        $driver = $this->employee($this->driver, [$secondTruck]);

        // Both positions match "driver", and the list must not name them twice.
        $ids = $this->dropdown('drivers');
        $this->assertSame([$driver->id], array_values(array_filter($ids, fn ($id) => $id === $driver->id)));
    }
}
