<?php

namespace Tests\Feature\Checks;

use App\Models\Check;
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
 * A rep is accountable for the checks they took, and for nobody else's. This
 * view exists so they can see whether they are discharged — not so they can see
 * the whole business's checks, and not so they can clear their own.
 */
class CheckMonitoringScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function rep(string $lastname): Employee
    {
        return Employee::create([
            'lastname' => $lastname, 'firstname' => 'Rep', 'mobile' => '0917' . random_int(1000000, 9999999),
            'birthdate' => '1990-01-01', 'sex' => 'Male', 'religion' => 'N/A',
            'is_regular' => 1, 'is_blacklisted' => 0,
        ]);
    }

    private function userFor(?Employee $employee, ?string $submodule, ?string $level): User
    {
        $user = User::factory()->create();
        $employee?->update(['user_id' => $user->id]);

        $role = ListRole::create(['name' => 'R' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level) {
            $module = Module::where('key', 'sales')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule ? $module->submodules()->where('key', $submodule)->firstOrFail()->id : null,
                'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function checkFor(?Employee $rep, string $number): Check
    {
        return Check::create([
            'direction' => Check::DIRECTION_RECEIVED, 'check_number' => $number,
            'check_date' => now()->addDays(5)->toDateString(), 'amount' => 1000,
            'received_by_id' => $rep?->id,
            'source_type' => 'App\Models\Receipt', 'source_id' => random_int(1000, 9999),
        ]);
    }

    public function test_a_rep_sees_only_their_own_checks(): void
    {
        $mine = $this->rep('Santos');
        $theirs = $this->rep('Cruz');
        $this->checkFor($mine, 'MINE-1');
        $this->checkFor($theirs, 'THEIRS-1');

        $body = $this->actingAs($this->userFor($mine, 'check_monitoring', 'view'))
            ->getJson('/sales/check-monitoring')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $body);
        $this->assertSame('MINE-1', $body[0]['check_number']);
    }

    public function test_a_sales_admin_sees_every_check(): void
    {
        $this->checkFor($this->rep('Santos'), 'MINE-1');
        $this->checkFor($this->rep('Cruz'), 'THEIRS-1');

        $body = $this->actingAs($this->userFor(null, null, 'admin'))
            ->getJson('/sales/check-monitoring')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $body);
    }

    public function test_the_view_is_closed_without_a_grant(): void
    {
        $this->actingAs($this->userFor(null, null, null))
            ->getJson('/sales/check-monitoring')
            ->assertForbidden();
    }

    public function test_a_user_with_no_employee_record_sees_nothing_rather_than_everything(): void
    {
        $this->checkFor($this->rep('Santos'), 'MINE-1');

        $body = $this->actingAs($this->userFor(null, 'check_monitoring', 'view'))
            ->getJson('/sales/check-monitoring')
            ->assertOk()
            ->json('data');

        $this->assertCount(0, $body, 'Failing open would show one rep every other rep\'s checks.');
    }

    public function test_a_rep_never_sees_the_issued_side(): void
    {
        $mine = $this->rep('Santos');
        $this->checkFor($mine, 'MINE-1');
        Check::create([
            'direction' => Check::DIRECTION_ISSUED, 'check_number' => 'SUPPLIER-1',
            'check_date' => now()->addDays(5)->toDateString(), 'amount' => 1000000,
            'received_by_id' => $mine->id,
            'source_type' => 'App\Models\ReceivedStockPayment', 'source_id' => 1,
        ]);

        $body = $this->actingAs($this->userFor($mine, 'check_monitoring', 'view'))
            ->getJson('/sales/check-monitoring')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $body);
        $this->assertSame('MINE-1', $body[0]['check_number'], 'What the owner pays suppliers is not a rep\'s business.');
    }
}
