<?php

namespace Tests\Feature\Accounting;

use App\Models\ArInvoice;
use App\Models\Check;
use App\Models\Module;
use App\Models\Receipt;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * A bank transfer a driver reported has to be confirmable somewhere.
 *
 * Until someone says the money landed, the invoice holds its balance open and
 * the ledger posts nothing — and there was no screen in the system that could
 * say it. Checks had one, because a check becomes a register row; a transfer
 * never does, deliberately, since the forecast sums pending checks by maturity
 * and a transfer has none.
 *
 * So transfers are listed beside the register and confirmed through it, without
 * ever being written into `checks`.
 */
class FieldTransferRegisterTest extends TestCase
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

    /** Someone who may look at the register, and optionally act on it. */
    private function accountant(string $level): User
    {
        $user = User::factory()->create();
        $roleId = UserRole::where('user_id', $this->user->id)->value('role_id');

        // Reuse the fixture's role so one grant serves both users.
        UserRole::create([
            'user_id' => $user->id, 'role_id' => $roleId,
            'is_active' => 1, 'added_by_id' => $user->id,
        ]);

        $module = Module::where('key', 'accounting')->firstOrFail();
        RolePermission::firstOrCreate([
            'role_id' => $roleId,
            'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'check_register')->firstOrFail()->id,
            'access_level' => $level,
        ]);

        return $user;
    }

    private function collectByTransfer(): Receipt
    {
        $invoice = ArInvoice::firstOrFail();

        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'balance_due' => (float) $invoice->balance_due,
            'amount_paid' => (float) $invoice->balance_due,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'Bank Transfer',
            'reference_number' => 'BPI-554433',
        ])->assertSessionHasNoErrors();

        return Receipt::firstOrFail();
    }

    public function test_an_unconfirmed_field_transfer_is_listed_for_the_register(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->collectByTransfer();

        $rows = $this->actingAs($this->accountant('view'))
            ->getJson('/accounting/check-register?option=field-transfers')
            ->assertOk()
            ->json();

        $this->assertCount(1, $rows);
        $this->assertSame('BPI-554433', $rows[0]['reference_number']);
        $this->assertSame(3000.0, (float) $rows[0]['amount']);
    }

    public function test_it_is_never_written_into_the_check_register(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->collectByTransfer();

        // A row here would fall due on the day it was collected and inflate
        // the maturity forecast by its amount.
        $this->assertSame(0, Check::count());
    }

    public function test_confirming_releases_the_balance(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $receipt = $this->collectByTransfer();

        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->balance_due);

        $this->actingAs($this->accountant('approver'))
            ->putJson('/accounting/check-register/transfers/'.$receipt->id.'/confirm', [
                'bank_name' => 'BPI',
            ])
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);
        $this->assertNotNull($receipt->fresh()->confirmed_at);
    }

    public function test_a_confirmed_transfer_drops_off_the_list(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $receipt = $this->collectByTransfer();

        $this->actingAs($this->accountant('approver'))
            ->putJson('/accounting/check-register/transfers/'.$receipt->id.'/confirm', ['bank_name' => 'BPI'])
            ->assertOk();

        $rows = $this->actingAs($this->accountant('approver'))
            ->getJson('/accounting/check-register?option=field-transfers')
            ->assertOk()
            ->json();

        $this->assertCount(0, $rows);
    }

    public function test_confirming_needs_a_bank_name(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $receipt = $this->collectByTransfer();

        $this->actingAs($this->accountant('approver'))
            ->putJson('/accounting/check-register/transfers/'.$receipt->id.'/confirm', [])
            ->assertStatus(422);
    }

    public function test_looking_is_not_enough_to_confirm(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $receipt = $this->collectByTransfer();

        $this->actingAs($this->accountant('view'))
            ->putJson('/accounting/check-register/transfers/'.$receipt->id.'/confirm', ['bank_name' => 'BPI'])
            ->assertForbidden();

        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->balance_due);
    }

    public function test_a_counter_transfer_is_not_listed(): void
    {
        // Settled on the spot — the cashier was looking at the confirmation, so
        // there is nothing for the office to vouch for later.
        $this->postCod([
            'payment_mode' => 'Cash',
            'delivery_date' => null,
            'driver_id' => null,
            'payment_lines' => [[
                'payment_mode' => 'Bank Transfer',
                'payment_amount' => 3000,
                'reference_number' => 'CTR-1',
            ]],
        ])->assertSessionHasNoErrors();

        $rows = $this->actingAs($this->accountant('view'))
            ->getJson('/accounting/check-register?option=field-transfers')
            ->assertOk()
            ->json();

        $this->assertCount(0, $rows);
    }
}
