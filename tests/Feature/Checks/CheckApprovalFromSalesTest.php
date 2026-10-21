<?php

namespace Tests\Feature\Checks;

use App\Models\ArInvoice;
use App\Models\Check;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Clearing a cheque from the Sales screen.
 *
 * Sales check monitoring is where anyone chasing a sale looks, so the approval
 * belongs in reach of that screen — but it stays the same approval, through the
 * same endpoint, held to the same grant. A second doorway, not a second rule.
 *
 * That is what these pin: the people who could already clear a cheque still
 * can, the people who could not still cannot, and a cleared cheque leaves the
 * queue the screen shows. If the Sales screen ever grew a confirmation of its
 * own, these would keep it honest.
 */
class CheckApprovalFromSalesTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);
    }

    /** A real cheque: taken against a real invoice, so clearing it has work to do. */
    private function chequeOnAnInvoice(): Check
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $invoice = ArInvoice::firstOrFail();

        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'action' => 'payment',
            'payment_date' => now()->toDateString(),
            'balance_due' => (float) $invoice->balance_due,
            'splits' => [[
                'payment_mode' => 'Check', 'amount' => 3000, 'bank_account_id' => null,
                'reference_number' => 'CHK-APPROVE-1', 'check_date' => now()->addDays(5)->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();

        return Check::where('check_number', 'CHK-APPROVE-1')->firstOrFail();
    }

    private function userWith(string $module, ?string $submodule, string $level): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R'.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $m = Module::where('key', $module)->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id,
            'module_id' => $m->id,
            'submodule_id' => $submodule ? $m->submodules()->where('key', $submodule)->firstOrFail()->id : null,
            'access_level' => $level,
        ]);

        return $user;
    }

    public function test_a_rep_watching_their_cheques_cannot_clear_one(): void
    {
        $cheque = $this->chequeOnAnInvoice();

        // Everything the monitoring screen grants, and nothing more.
        $this->actingAs($this->userWith('sales', 'check_monitoring', 'view'))
            ->putJson('/accounting/check-register/'.$cheque->id.'/confirm')
            ->assertForbidden();

        $this->assertSame(Check::STATUS_PENDING, $cheque->fresh()->status);
    }

    public function test_even_a_sales_administrator_cannot(): void
    {
        $cheque = $this->chequeOnAnInvoice();

        // Running Sales is not the same as saying money reached the bank.
        $this->actingAs($this->userWith('sales', null, 'admin'))
            ->putJson('/accounting/check-register/'.$cheque->id.'/confirm')
            ->assertForbidden();

        $this->assertSame(Check::STATUS_PENDING, $cheque->fresh()->status);
    }

    public function test_a_cheque_with_no_bank_says_so_rather_than_clearing(): void
    {
        // Nobody at the door knows which bank it will be deposited to, so a
        // cheque often reaches the register without one. It cannot be cleared
        // until somebody says where the money landed.
        $cheque = $this->chequeOnAnInvoice();
        $this->assertNull($cheque->bank_name);

        $this->actingAs($this->userWith('accounting', 'check_register', 'approver'))
            ->putJson('/accounting/check-register/'.$cheque->id.'/confirm')
            ->assertStatus(422)
            ->assertJsonValidationErrors('bank_name');

        $this->assertSame(Check::STATUS_PENDING, $cheque->fresh()->status);
        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->balance_due);
    }

    public function test_naming_the_bank_clears_it_and_the_money_lands(): void
    {
        $cheque = $this->chequeOnAnInvoice();
        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->balance_due);

        $this->actingAs($this->userWith('accounting', 'check_register', 'approver'))
            ->putJson('/accounting/check-register/'.$cheque->id.'/confirm', ['bank_name' => 'BDO'])
            ->assertOk();

        // The bank it was named with is kept on the cheque, not just used once.
        $this->assertSame('BDO', $cheque->fresh()->bank_name);

        $this->assertSame(Check::STATUS_CLEARED, $cheque->fresh()->status);
        // The cheque was a promise until now; clearing it is what pays the invoice.
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);
    }

    public function test_a_cleared_cheque_leaves_the_queue_the_screen_shows(): void
    {
        $cheque = $this->chequeOnAnInvoice();
        $watcher = $this->userWith('sales', null, 'admin');

        $pending = fn () => $this->actingAs($watcher)
            ->getJson('/sales/check-monitoring?status=pending')
            ->assertOk()
            ->json('data');

        $this->assertSame(['CHK-APPROVE-1'], array_column($pending(), 'check_number'));

        $this->actingAs($this->userWith('accounting', 'check_register', 'approver'))
            ->putJson('/accounting/check-register/'.$cheque->id.'/confirm', ['bank_name' => 'BDO'])
            ->assertOk();

        $this->assertSame([], array_column($pending(), 'check_number'));
    }

    public function test_it_cannot_be_cleared_twice(): void
    {
        $cheque = $this->chequeOnAnInvoice();
        $approver = $this->userWith('accounting', 'check_register', 'approver');

        $this->actingAs($approver)
            ->putJson('/accounting/check-register/'.$cheque->id.'/confirm', ['bank_name' => 'BDO'])->assertOk();
        $this->actingAs($approver)
            ->putJson('/accounting/check-register/'.$cheque->id.'/confirm', ['bank_name' => 'BDO'])->assertStatus(422);

        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->amount_paid);
    }
}
