<?php

namespace Tests\Feature\Sales;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may say that money arrived in the bank.
 *
 * Collecting a payment and confirming it cleared are different jobs. A sales
 * rep records the collection; confirming moves the invoice balance and posts
 * to the ledger at once, and should not be done by the same person who chased
 * the payment. A rep holds 'encoder' by default, so that grant must not be
 * enough here — while recording the driver's handover still is.
 */
class ConfirmReceiptPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function userWithGrant(?string $level): User
    {
        $role = ListRole::create([
            'name' => 'Test Role '.uniqid(), 'type' => 'role',
            'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create([
            'user_id' => $user->id, 'role_id' => $role->id,
            'is_active' => 1, 'added_by_id' => $user->id,
        ]);

        if ($level !== null) {
            RolePermission::create([
                'role_id' => $role->id,
                'module_id' => Module::where('key', 'sales')->firstOrFail()->id,
                'submodule_id' => null,
                'access_level' => $level,
            ]);
        }

        return $user;
    }

    /** A receipt id that does not exist: authorization runs before the lookup. */
    private function confirm(User $user)
    {
        return $this->actingAs($user)->putJson('/receipts/999999/confirm-check', [
            'bank_name' => 'Test Bank',
        ]);
    }

    public function test_confirm_denied_without_any_grant(): void
    {
        $this->confirm($this->userWithGrant(null))->assertForbidden();
    }

    public function test_confirm_denied_for_a_sales_reps_encoder_grant(): void
    {
        // The grant a Sales Rep is seeded with. Collecting is theirs; vouching
        // for the bank is not.
        $this->confirm($this->userWithGrant('encoder'))->assertForbidden();
    }

    public function test_confirm_denied_with_view_alone(): void
    {
        $this->confirm($this->userWithGrant('view'))->assertForbidden();
    }

    /**
     * Past the gate the request reaches the service and fails on the missing
     * receipt. handleTransaction catches that and answers 200 with
     * status:false, so "got through" is the absence of a 403 plus a body that
     * reports the failure — not a 404.
     */
    public function test_confirm_passes_authorization_for_an_approver(): void
    {
        $this->confirm($this->userWithGrant('approver'))
            ->assertOk()
            ->assertJson(['status' => false]);
    }

    public function test_confirm_passes_authorization_for_an_administrator(): void
    {
        // 'admin' satisfies every required level (PermissionService::levelsSatisfying).
        $this->confirm($this->userWithGrant('admin'))
            ->assertOk()
            ->assertJson(['status' => false]);
    }

    public function test_recording_the_drivers_handover_still_takes_only_encoder(): void
    {
        // The rep records the handover; that is the half of this that stays theirs.
        $this->actingAs($this->userWithGrant('encoder'))
            ->putJson('/receipts/999999/turn-over', ['held_by_employee_id' => 1])
            ->assertStatus(422);
    }

    public function test_handover_denied_without_any_grant(): void
    {
        $this->actingAs($this->userWithGrant(null))
            ->putJson('/receipts/999999/turn-over', ['held_by_employee_id' => 1])
            ->assertForbidden();
    }
}
