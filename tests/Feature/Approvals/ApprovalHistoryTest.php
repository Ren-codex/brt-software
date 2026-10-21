<?php

namespace Tests\Feature\Approvals;

use App\Models\Employee;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Payroll;
use App\Models\PayrollLog;
use App\Models\Remittance;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Approvals page's Approved and Disapproved tabs: what has already been
 * decided, by whom and when, on the sources the viewer may approve.
 */
class ApprovalHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['for-verification', 'liquidated', 'disapproved', 'approval', 'for-release'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucwords(str_replace('-', ' ', $slug)), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function approverOf(string $module, string $submodule): User
    {
        $role = ListRole::create(['name' => 'Approver '.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $moduleModel = Module::where('key', $module)->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $moduleModel->id,
            'submodule_id' => $moduleModel->submodules()->where('key', $submodule)->firstOrFail()->id,
            'access_level' => 'approver',
        ]);

        return $user;
    }

    private function withEmployee(User $user, string $first, string $last): User
    {
        Employee::create([
            'firstname' => $first, 'lastname' => $last, 'mobile' => '0917'.random_int(1000000, 9999999),
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None', 'user_id' => $user->id,
        ]);

        return $user;
    }

    private function remittance(string $status, array $attributes = []): Remittance
    {
        return Remittance::create(array_merge([
            'remittance_no' => 'REM-'.uniqid(), 'remittance_date' => now()->toDateString(),
            'summary' => [], 'total_amount' => 4700,
            'status_id' => ListStatus::getBySlug($status)->id,
            'created_by_id' => User::factory()->create()->id,
        ], $attributes));
    }

    private function history(User $as, string $decision = 'approved'): array
    {
        return $this->actingAs($as)
            ->getJson('/approvals?option=history&decision='.$decision)
            ->assertOk()->json();
    }

    private function group(array $history, string $source): ?array
    {
        return collect($history['groups'])->firstWhere('source', $source);
    }

    public function test_a_verified_remittance_is_listed_with_who_verified_it(): void
    {
        $approver = $this->approverOf('sales', 'remittances');
        $verifier = $this->withEmployee(User::factory()->create(), 'Ana', 'Cruz');

        $this->remittance('for-verification');
        $verified = $this->remittance('liquidated', [
            'approved_by_id' => $verifier->id, 'approved_at' => now(), 'remarks' => 'Counted twice',
        ]);

        $group = $this->group($this->history($approver), 'remittance');

        $this->assertSame(1, $group['count']);
        $row = $group['items'][0];
        $this->assertSame($verified->id, $row['id']);
        $this->assertSame('approved', $row['decision']);
        $this->assertSame('Verified', $row['decision_label']);
        $this->assertSame('Ana Cruz', $row['decided_by']);
        $this->assertSame('Counted twice', $row['remarks']);
        $this->assertNotNull($row['decided_at']);
    }

    public function test_a_disapproved_remittance_is_listed_under_disapproved_only(): void
    {
        $approver = $this->approverOf('sales', 'remittances');
        $this->remittance('disapproved', ['approved_by_id' => $approver->id, 'approved_at' => now()]);

        $this->assertSame(0, $this->group($this->history($approver, 'approved'), 'remittance')['count']);

        $row = $this->group($this->history($approver, 'rejected'), 'remittance')['items'][0];

        $this->assertSame('rejected', $row['decision']);
        $this->assertSame('Disapproved', $row['decision_label']);
        // No employee record: the username stands in.
        $this->assertSame($approver->username, $row['decided_by']);
    }

    public function test_a_payroll_sent_back_then_approved_is_listed_on_both_tabs(): void
    {
        $approver = $this->approverOf('payroll', 'payroll_processing');
        $payroll = Payroll::create([
            'payroll_no' => 'PAY-1', 'pay_period_start' => '2026-10-01', 'pay_period_end' => '2026-10-15',
            'status_id' => ListStatus::getBySlug('for-release')->id, 'total_amount' => 17589,
            'created_by' => $approver->id,
        ]);
        PayrollLog::create(['payroll_id' => $payroll->id, 'action' => 'created', 'actioned_by_id' => $approver->id]);
        PayrollLog::create(['payroll_id' => $payroll->id, 'action' => 'status updated to disapproved', 'actioned_by_id' => $approver->id, 'remarks' => 'Wrong period']);
        PayrollLog::create(['payroll_id' => $payroll->id, 'action' => 'status updated to for-release', 'actioned_by_id' => $approver->id]);

        $approved = $this->group($this->history($approver, 'approved'), 'payroll');
        $rejected = $this->group($this->history($approver, 'rejected'), 'payroll');

        $this->assertSame(1, $approved['count']);
        $this->assertSame('approved', $approved['items'][0]['decision']);
        $this->assertSame(1, $rejected['count']);
        $this->assertSame('Wrong period', $rejected['items'][0]['remarks']);
        $this->assertNotSame($approved['items'][0]['key'], $rejected['items'][0]['key']);
    }

    public function test_a_source_that_is_never_rejected_still_has_an_empty_disapproved_pill(): void
    {
        $approver = $this->approverOf('accounting', 'expenses');

        $group = $this->group($this->history($approver, 'rejected'), 'expense');

        $this->assertSame(0, $group['count']);
        $this->assertSame([], $group['items']);
    }

    public function test_only_sources_the_viewer_can_approve_are_listed(): void
    {
        $payrollApprover = $this->approverOf('payroll', 'payroll_processing');
        $this->remittance('liquidated', ['approved_by_id' => $payrollApprover->id, 'approved_at' => now()]);

        $history = $this->history($payrollApprover);

        $this->assertNull($this->group($history, 'remittance'));
        $this->assertNotNull($this->group($history, 'payroll'));
    }
}
