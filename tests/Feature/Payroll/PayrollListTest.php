<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Payroll;
use App\Models\PayrollLog;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A payroll that saved and then could not be seen reads exactly like one that
 * never saved — and the next attempt is refused as a duplicate. Three separate
 * things conspired to do that, so the list is pinned down here.
 */
class PayrollListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
        $this->user = $this->officer();
    }

    private function officer(): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Payroll Officer'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create([
            'user_id' => $user->id, 'role_id' => $role->id,
            'is_active' => 1, 'added_by_id' => $user->id,
        ]);

        $module = Module::where('key', 'payroll')->firstOrFail();
        RolePermission::firstOrCreate([
            'role_id' => $role->id,
            'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'payroll_processing')->firstOrFail()->id,
        ], ['access_level' => 'encoder']);

        return $user;
    }

    private function makePayrolls(User $creator, int $count): void
    {
        $status = ListStatus::firstOrCreate(['slug' => 'pending'], [
            'name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);

        for ($i = 1; $i <= $count; $i++) {
            $payroll = Payroll::create([
                'payroll_no' => sprintf('PR-%03d', $i),
                'pay_period_start' => now()->subDays(60 - $i)->toDateString(),
                'pay_period_end' => now()->subDays(55 - $i)->toDateString(),
                'status_id' => $status->id,
                'total_amount' => 1000 * $i,
                'created_by' => $creator->id,
            ]);

            // created_at is not fillable, so it has to be set after the fact
            // for these to be distinguishable by age at all.
            $payroll->forceFill(['created_at' => now()->subDays(60 - $i)])->saveQuietly();

            // Every real payroll carries one of these — store() writes it — so
            // a fixture without one does not exercise what the list renders.
            PayrollLog::create([
                'payroll_id' => $payroll->id,
                'action' => 'created',
                'actioned_by_id' => $creator->id,
            ]);
        }
    }

    private function list(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->getJson('/payrolls?option=lists&count=10');
    }

    public function test_the_newest_payroll_is_the_first_one_you_see(): void
    {
        // The one just created is the one being looked for.
        $this->makePayrolls($this->user, 12);

        $numbers = collect($this->list()->assertOk()->json('data'))->pluck('payroll_no');

        $this->assertSame('PR-012', $numbers->first(), 'The newest payroll was not on top.');
        $this->assertCount(10, $numbers);
        $this->assertFalse($numbers->contains('PR-001'), 'The oldest should have fallen to page 2.');
    }

    public function test_the_list_carries_what_the_pagination_control_needs(): void
    {
        // Flattened, there is no way to reach page 2 at all.
        $this->makePayrolls($this->user, 12);

        $body = $this->list()->assertOk()->json();

        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('links', $body);
        $this->assertSame(12, $body['meta']['total']);
        $this->assertSame(2, $body['meta']['last_page']);
    }

    public function test_the_second_page_holds_the_rest(): void
    {
        $this->makePayrolls($this->user, 12);

        $numbers = collect($this->actingAs($this->user)
            ->getJson('/payrolls?option=lists&count=10&page=2')
            ->assertOk()->json('data'))->pluck('payroll_no');

        $this->assertSame(['PR-002', 'PR-001'], $numbers->all());
    }

    public function test_a_creator_with_no_employee_record_does_not_take_the_list_down(): void
    {
        // Not every user is an employee. One payroll created by such a user
        // used to return 500 for the whole page.
        $this->makePayrolls($this->user, 3);

        $body = $this->list()->assertOk()->json();

        $this->assertCount(3, $body['data']);
        $this->assertSame($this->user->username, $body['data'][0]['created_by'],
            'With no employee record, the username stands in rather than nothing at all.');
    }

    public function test_the_creation_log_names_whoever_wrote_it(): void
    {
        // Every payroll carries a log, so a log that cannot render is a list
        // that cannot render — this is what kept the page broken.
        $this->makePayrolls($this->user, 1);

        $log = $this->list()->assertOk()->json('data.0.logs.0');

        $this->assertSame('created', $log['action']);
        $this->assertSame($this->user->username, $log['actioned_by'],
            'With no employee record, the username stands in.');
    }

    public function test_the_log_names_the_employee_when_there_is_one(): void
    {
        Employee::create([
            'firstname' => 'Ben', 'lastname' => 'Santos', 'mobile' => '09170000001',
            'birthdate' => '1990-01-01', 'sex' => 'Male', 'religion' => 'None',
            'user_id' => $this->user->id,
        ]);
        $this->makePayrolls($this->user->fresh(), 1);

        $named = $this->list()->assertOk()->json('data.0.logs.0.actioned_by');

        // It read `full_name` where the accessor is `fullname`, so this was
        // blank even when the employee was there.
        $this->assertStringContainsString('Ben', $named);
        $this->assertStringContainsString('Santos', $named);
    }

    public function test_a_creator_with_an_employee_record_is_named(): void
    {
        Employee::create([
            'firstname' => 'Ana', 'lastname' => 'Cruz', 'mobile' => '09170000000',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'user_id' => $this->user->id,
        ]);
        $this->makePayrolls($this->user->fresh(), 1);

        $named = $this->list()->assertOk()->json('data.0.created_by');
        $this->assertStringContainsString('Ana', $named);
        $this->assertStringContainsString('Cruz', $named);
    }
}
