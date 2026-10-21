<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The View Employee modal's At a Glance card: an employee's payslips, sales,
 * deliveries and cash in hand, each shown only to someone who may see it.
 */
class EmployeeOverviewTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['approval', 'for-release', 'disapproved'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('-', ' ', $slug)), 'text_color' => '#fff', 'bg_color' => '#333']);
        }

        $this->employee = Employee::create([
            'firstname' => 'Ana', 'lastname' => 'Cruz', 'mobile' => '09170000001',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
        ]);
    }

    private function viewer(string $roleName, array $grants = []): User
    {
        $role = ListRole::firstOrCreate(['name' => $roleName], ['type' => 'role', 'definition' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        foreach ($grants as $module => $level) {
            RolePermission::create(['role_id' => $role->id, 'module_id' => Module::where('key', $module)->firstOrFail()->id,
                'submodule_id' => null, 'access_level' => $level]);
        }

        return $user;
    }

    private function payslip(string $status, float $net, string $end): void
    {
        $payroll = Payroll::create([
            'payroll_no' => 'PAY-'.uniqid(), 'pay_period_start' => now()->startOfMonth()->toDateString(), 'pay_period_end' => $end,
            'status_id' => ListStatus::getBySlug($status)->id, 'total_amount' => $net, 'created_by' => User::factory()->create()->id,
        ]);
        PayrollItem::create(['payroll_id' => $payroll->id, 'employee_id' => $this->employee->id, 'basic_salary' => $net, 'net_salary' => $net, 'total_days' => 13]);
    }

    public function test_net_pay_counts_approved_payrolls_only(): void
    {
        $this->payslip('for-release', 9000, now()->toDateString());
        $this->payslip('disapproved', 5000, now()->toDateString());
        $this->payslip('approval', 4000, now()->toDateString());

        $overview = $this->actingAs($this->viewer('Super Admin'))
            ->getJson("/employees/{$this->employee->id}/overview")->assertOk()->json();

        $this->assertEquals(9000, $overview['payroll']['year_net']);
        $this->assertSame(3, $overview['payroll']['count']);
        $this->assertCount(3, $overview['payroll']['recent']);
        $this->assertNotNull($overview['sales']);
        $this->assertNotNull($overview['cash']);
    }

    public function test_parts_the_viewer_may_not_see_are_left_out(): void
    {
        $this->payslip('for-release', 9000, now()->toDateString());
        $employeesOnly = $this->viewer('Clerk', ['employees' => 'view']);

        $overview = $this->actingAs($employeesOnly)
            ->getJson("/employees/{$this->employee->id}/overview")->assertOk()->json();

        $this->assertNull($overview['payroll']);
        $this->assertNull($overview['sales']);
        $this->assertNull($overview['deliveries']);
        $this->assertNull($overview['cash']);
    }

    public function test_it_needs_employees_access(): void
    {
        $this->actingAs($this->viewer('Nobody'))
            ->getJson("/employees/{$this->employee->id}/overview")->assertForbidden();
    }
}
