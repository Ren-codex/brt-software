<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\ListRole;
use App\Models\Loan;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanPermissionTest extends TestCase
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
            $submodule = $module->submodules()->where('key', 'loans')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeLoan(): Loan
    {
        $employee = Employee::create([
            'lastname' => 'Test', 'firstname' => 'Employee' . uniqid(),
            'mobile' => '09000000000', 'birthdate' => '1990-01-01', 'sex' => 'male', 'religion' => 'n/a',
        ]);

        return Loan::create([
            'employee_id' => $employee->id, 'loan_type' => 'personal',
            'amount' => 5000, 'interest_rate' => 0, 'term_months' => 6,
            'added_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/loans?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->getJson('/loans?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/loans', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $response = $this->actingAs($user)->post('/loans', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_update_status_denied_without_approver_grant(): void
    {
        $loan = $this->makeLoan();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->put("/loans/{$loan->id}/status")->assertForbidden();
    }

    public function test_update_status_allowed_with_approver_grant(): void
    {
        $loan = $this->makeLoan();
        $user = $this->administratorWithGrant('approver');

        $response = $this->actingAs($user)->put("/loans/{$loan->id}/status");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_release_denied_with_only_approver_grant(): void
    {
        $loan = $this->makeLoan();
        $user = $this->administratorWithGrant('approver');

        $this->actingAs($user)->put("/loans/{$loan->id}/status", ['status' => 'active'])->assertForbidden();
    }

    public function test_release_allowed_with_releaser_grant(): void
    {
        $loan = $this->makeLoan();
        $user = $this->administratorWithGrant('releaser');

        $response = $this->actingAs($user)->put("/loans/{$loan->id}/status", ['status' => 'active']);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $loan = $this->makeLoan();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete("/loans/{$loan->id}")->assertForbidden();
    }

    public function test_loan_payments_list_gated_by_the_same_loans_grant(): void
    {
        $denied = $this->administratorWithGrant(null);
        $this->actingAs($denied)->getJson('/loan-payments?option=lists')->assertForbidden();

        $allowed = $this->administratorWithGrant('view');
        $this->actingAs($allowed)->getJson('/loan-payments?option=lists')->assertOk();
    }
}
