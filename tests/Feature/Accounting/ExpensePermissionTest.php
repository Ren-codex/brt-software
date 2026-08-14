<?php

namespace Tests\Feature\Accounting;

use App\Models\Expense;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensePermissionTest extends TestCase
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
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'expenses')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeExpense(): Expense
    {
        return Expense::create([
            'expense_type' => 'operational', 'amount' => 500,
            'expense_date' => now()->toDateString(), 'added_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_index_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/expenses')->assertForbidden();
    }

    public function test_index_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/expenses')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/expenses', [])->assertForbidden();
    }

    public function test_approve_denied_without_approver_grant(): void
    {
        $expense = $this->makeExpense();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->patch("/accounting/expenses/{$expense->id}/approve")->assertForbidden();
    }

    public function test_approve_allowed_with_approver_grant(): void
    {
        $expense = $this->makeExpense();
        $user = $this->administratorWithGrant('approver');

        $response = $this->actingAs($user)->patch("/accounting/expenses/{$expense->id}/approve");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_void_denied_without_approver_grant(): void
    {
        $expense = $this->makeExpense();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->patch("/accounting/expenses/{$expense->id}/void")->assertForbidden();
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $expense = $this->makeExpense();
        $user = $this->administratorWithGrant('approver');

        $this->actingAs($user)->delete("/accounting/expenses/{$expense->id}")->assertForbidden();
    }
}
