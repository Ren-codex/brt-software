<?php

namespace Tests\Feature\Inventory;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListSupplier;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\ReceivedStock;
use App\Models\RolePermission;
use App\Models\Submodule;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Taking a delivery in and paying for it are separate duties.
 *
 * They used to share one permission, so a warehouse manager could not be
 * allowed to receive goods without also being able to move money — which is
 * why they needed sight of the bank accounts. Paying now answers to
 * accounting/accounts_payable.
 */
class SupplierPaymentSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['pending', 'completed', 'unpaid'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucfirst($slug), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function userWith(array $grants): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        foreach ($grants as [$moduleKey, $submoduleKey, $level]) {
            $module = Module::where('key', $moduleKey)->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id,
                'module_id' => $module->id,
                'submodule_id' => $submoduleKey
                    ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id
                    : null,
                'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function payable(): ReceivedStock
    {
        $user = User::factory()->create();

        $supplier = ListSupplier::create([
            'name' => 'Test Supplier ' . uniqid(), 'address' => 'Zamboanga City',
            'contact_person' => 'Roberto Cruz', 'contact_number' => '09170000000',
            'email' => uniqid() . '@example.com', 'tin' => '000-000-000',
            'is_active' => 1, 'is_blacklisted' => 0,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-' . uniqid(),
            'po_date' => now()->toDateString(),
            'total_amount' => 1000,
            'created_by_id' => $user->id,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
        ]);

        return ReceivedStock::create([
            'po_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_no' => 'RS-' . uniqid(),
            'received_date' => now(),
            'payment_mode' => 'Credit',
            'amount_paid' => 0,
            'received_by_id' => $user->id,
        ]);
    }

    public function test_the_accounts_payable_submodule_exists_under_accounting(): void
    {
        $submodule = Submodule::where('key', 'accounts_payable')->first();

        $this->assertNotNull($submodule, 'accounts_payable submodule should exist.');
        $this->assertEquals('accounting', $submodule->module->key);
    }

    public function test_a_warehouse_encoder_cannot_settle_a_supplier_bill(): void
    {
        $user = $this->userWith([['inventory', 'receiving', 'encoder']]);

        $this->actingAs($user)
            ->postJson('/received-stocks/' . $this->payable()->id . '/pay', [
                'payment_mode' => 'Cash on Hand',
                'payment_amount' => 100,
            ])
            ->assertForbidden();
    }

    public function test_someone_granted_accounts_payable_may_settle_a_bill(): void
    {
        $user = $this->userWith([['accounting', 'accounts_payable', 'encoder']]);

        // Not a 403 — whatever the request does next, it got past the gate.
        $status = $this->actingAs($user)
            ->postJson('/received-stocks/' . $this->payable()->id . '/pay', [
                'payment_mode' => 'Cash on Hand',
                'payment_amount' => 100,
            ])->getStatusCode();

        $this->assertNotEquals(403, $status, 'A grant on accounts_payable should get past the gate.');
    }

    public function test_a_module_wide_accounting_grant_is_enough_to_pay(): void
    {
        // Administrator and Accountant hold accounting module-wide, so they keep
        // the ability without anyone editing their permissions.
        $user = $this->userWith([['accounting', null, 'admin']]);

        $status = $this->actingAs($user)
            ->postJson('/received-stocks/' . $this->payable()->id . '/pay', [
                'payment_mode' => 'Cash on Hand',
                'payment_amount' => 100,
            ])->getStatusCode();

        $this->assertNotEquals(403, $status, 'A module-wide accounting grant should get past the gate.');
    }

    public function test_receiving_still_works_for_a_warehouse_encoder(): void
    {
        $user = $this->userWith([['inventory', 'receiving', 'encoder']]);

        // Reaching validation rather than a permission wall is the point here.
        $this->actingAs($user)->postJson('/received-stocks', [])->assertStatus(422);
    }
}
