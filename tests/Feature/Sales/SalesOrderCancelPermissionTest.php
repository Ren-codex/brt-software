<?php

namespace Tests\Feature\Sales;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cancelling a sales order is the void-holder's job — a sales rep pulling back
 * an order they raised — but an approver may do it too. Requiring 'approver'
 * alone locked out the sales rep, who holds 'void'.
 */
class SalesOrderCancelPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['for-payment', 'cancelled', 'closed', 'partially-paid', 'paid'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucfirst($slug), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function userWithLevel(string $level): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id,
            'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'sales_orders')->firstOrFail()->id,
            'access_level' => $level,
        ]);

        return $user;
    }

    private function order(string $statusSlug = 'for-payment'): SalesOrder
    {
        return SalesOrder::create([
            'so_number' => 'SO-' . uniqid(),
            'payment_mode' => 'Cash',
            'order_date' => now()->toDateString(),
            'total_amount' => 100,
            'total_discount' => 0,
            'added_by_id' => User::factory()->create()->id,
            'requires_batch_approval' => false,
            'status_id' => ListStatus::where('slug', $statusSlug)->first()->id,
        ]);
    }

    public function test_a_partially_paid_order_can_still_be_cancelled(): void
    {
        $status = $this->actingAs($this->userWithLevel('void'))
            ->delete('/sales-orders/' . $this->order('partially-paid')->id, ['remarks' => 'customer changed mind'])
            ->getStatusCode();

        $this->assertNotEquals(403, $status);
    }

    public function test_a_closed_order_cannot_be_cancelled(): void
    {
        // Closed means the sale has run its full course through the ledger.
        $order = $this->order('closed');

        $this->actingAs($this->userWithLevel('void'))
            ->delete('/sales-orders/' . $order->id, ['remarks' => 'too late'])
            ->assertSessionHasErrors('cancel');

        $this->assertEquals('closed', $order->fresh()->status->slug, 'A closed order must be left alone.');
    }

    public function test_a_sales_rep_holding_void_may_cancel(): void
    {
        $status = $this->actingAs($this->userWithLevel('void'))
            ->delete('/sales-orders/' . $this->order()->id, ['remarks' => 'customer backed out'])
            ->getStatusCode();

        $this->assertNotEquals(403, $status, 'A void grant should be enough to cancel.');
    }

    public function test_an_approver_may_still_cancel(): void
    {
        $status = $this->actingAs($this->userWithLevel('approver'))
            ->delete('/sales-orders/' . $this->order()->id, ['remarks' => 'duplicate'])
            ->getStatusCode();

        $this->assertNotEquals(403, $status, 'Approvers must not lose the ability they had.');
    }

    public function test_an_encoder_alone_may_not_cancel(): void
    {
        $this->actingAs($this->userWithLevel('encoder'))
            ->delete('/sales-orders/' . $this->order()->id, ['remarks' => 'nope'])
            ->assertForbidden();
    }

    public function test_a_view_only_user_may_not_cancel(): void
    {
        $this->actingAs($this->userWithLevel('view'))
            ->delete('/sales-orders/' . $this->order()->id, ['remarks' => 'nope'])
            ->assertForbidden();
    }
}
