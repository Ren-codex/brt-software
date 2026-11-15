<?php

namespace Tests\Feature\Approvals;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Remittance;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accepting a remittance now happens inside the Approvals page, which fetches
 * the remittance with its receipts from a dedicated endpoint. The endpoint is
 * held to the same rule as the rest of the approvals screen: only someone who
 * may approve remittances can read it.
 */
class AcceptRemittanceInApprovalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
        ListStatus::firstOrCreate(['slug' => 'for-verification'], ['name' => 'For Verification', 'text_color' => '#fff', 'bg_color' => '#333']);
    }

    private function userWithGrant(string $moduleKey, string $submoduleKey, string $level): User
    {
        $role = ListRole::create(['name' => 'Role '.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', $moduleKey)->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id,
            'access_level' => $level,
        ]);

        return $user;
    }

    private function remittance(User $creator): Remittance
    {
        return Remittance::create([
            'remittance_no' => 'REM-'.uniqid(),
            'remittance_date' => now()->toDateString(),
            'summary' => [],
            'total_amount' => 5000,
            'status_id' => ListStatus::where('slug', 'for-verification')->first()->id,
            'created_by_id' => $creator->id,
        ]);
    }

    public function test_a_remittance_approver_can_fetch_the_remittance_with_its_receipts(): void
    {
        $approver = $this->userWithGrant('sales', 'remittances', 'approver');
        $remittance = $this->remittance($approver);

        $this->actingAs($approver)
            ->getJson("/approvals/remittance-detail/{$remittance->id}")
            ->assertOk()
            ->assertJsonPath('data.remittance_no', $remittance->remittance_no)
            ->assertJsonPath('data.receipts', []);
    }

    public function test_someone_who_cannot_approve_remittances_is_refused(): void
    {
        // A payroll approver has no business reading a remittance here.
        $outsider = $this->userWithGrant('payroll', 'payroll_processing', 'approver');
        $remittance = $this->remittance($outsider);

        $this->actingAs($outsider)
            ->getJson("/approvals/remittance-detail/{$remittance->id}")
            ->assertForbidden();
    }

    public function test_the_route_is_not_captured_by_the_generic_source_detail(): void
    {
        // '/approvals/remittance-detail/{id}' must resolve to the new endpoint,
        // not the generic '/approvals/{source}/{id}' with source=remittance-detail.
        $approver = $this->userWithGrant('sales', 'remittances', 'approver');
        $remittance = $this->remittance($approver);

        $this->actingAs($approver)
            ->getJson("/approvals/remittance-detail/{$remittance->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'remittance_no', 'total_amount', 'receipts']]);
    }
}
