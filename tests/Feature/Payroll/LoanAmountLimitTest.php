<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\ListRole;
use App\Models\LoanTypeLimit;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for punch-list items #1 and #3: a loan/cash-advance
 * withdrawal must not exceed a per-loan-type cap, and must declare whether
 * it's disbursed as cash or check.
 */
class LoanAmountLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        // SeriesService generates loan_no on creation.
        \Illuminate\Support\Facades\DB::table('series')->insert([
            'name' => 'Loan Number', 'slug' => 'loan_number', 'prefix' => 'LN-',
            'current_date' => now()->toDateString(), 'starting_value' => 1, 'max_digit' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function adminUser(): User
    {
        $role = ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'payroll')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => null, 'access_level' => 'admin',
        ]);

        return $user;
    }

    private function makeEmployee(): Employee
    {
        return Employee::create([
            'lastname' => 'Test', 'firstname' => 'Employee'.uniqid(),
            'mobile' => '09000000000', 'birthdate' => '1990-01-01', 'sex' => 'male', 'religion' => 'n/a',
        ]);
    }

    public function test_amount_over_the_loan_types_limit_is_rejected(): void
    {
        $user = $this->adminUser();
        $employee = $this->makeEmployee();
        $limit = LoanTypeLimit::where('loan_type', 'personal')->firstOrFail();

        $response = $this->actingAs($user)->post('/loans', [
            'employee_id' => $employee->id,
            'loan_type' => 'personal',
            'payment_type' => 'cash',
            'amount' => $limit->max_amount + 1,
            'interest_rate' => 0,
            'term_months' => 6,
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_amount_within_the_loan_types_limit_is_accepted(): void
    {
        $user = $this->adminUser();
        $employee = $this->makeEmployee();
        $limit = LoanTypeLimit::where('loan_type', 'personal')->firstOrFail();

        $response = $this->actingAs($user)->post('/loans', [
            'employee_id' => $employee->id,
            'loan_type' => 'personal',
            'payment_type' => 'check',
            'amount' => $limit->max_amount - 1,
            'interest_rate' => 0,
            'term_months' => 6,
            'status' => 'pending',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('loans', ['employee_id' => $employee->id, 'payment_type' => 'check']);
    }

    public function test_payment_type_is_required(): void
    {
        $user = $this->adminUser();
        $employee = $this->makeEmployee();

        $response = $this->actingAs($user)->post('/loans', [
            'employee_id' => $employee->id,
            'loan_type' => 'personal',
            'amount' => 1000,
            'interest_rate' => 0,
            'term_months' => 6,
        ]);

        $response->assertSessionHasErrors('payment_type');
    }

    public function test_different_loan_types_enforce_their_own_limit(): void
    {
        $user = $this->adminUser();
        $employee = $this->makeEmployee();
        $cashAdvanceLimit = LoanTypeLimit::where('loan_type', 'cash_advance')->firstOrFail();

        // Over the cash_advance limit but comfortably under the personal limit.
        $response = $this->actingAs($user)->post('/loans', [
            'employee_id' => $employee->id,
            'loan_type' => 'cash_advance',
            'payment_type' => 'cash',
            'amount' => $cashAdvanceLimit->max_amount + 500,
            'interest_rate' => 0,
            'term_months' => 6,
        ]);

        $response->assertSessionHasErrors('amount');
    }
}
