<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Check;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\Receipt;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Undoing a payment recorded against the wrong mode.
 *
 * The mode is not a label: it decides who is holding the money, whether the
 * bank has to confirm it, and whether a row appears in the cheque register.
 * Typed wrong, the ledger says one thing and custody says another, and the
 * rep's remittance will not reconcile.
 *
 * Editing the receipt changed the mode and none of its consequences, so the
 * way back is to void the whole thing and record it again — which leaves the
 * reason and the name of whoever did it on the record, rather than quietly
 * rewriting history.
 */
class VoidingAReceiptTest extends TestCase
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

    private function approver(): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R'.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'receipts')->firstOrFail()->id,
            'access_level' => 'approver',
        ]);

        return $user;
    }

    /** A delivered COD order paid at the door, in the mode given. */
    private function collect(string $mode = 'Cash'): Receipt
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $order->update(['sales_rep_id' => Employee::firstOrFail()->id]);

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, array_filter([
            'action' => 'mark-delivered',
            'collected_amount' => 3000,
            'collected_mode' => $mode,
            'collected_reference' => $mode === 'Check' ? 'CHK-WRONG' : null,
            'collected_check_date' => $mode === 'Check' ? now()->addDays(5)->toDateString() : null,
        ]))->assertSessionHasNoErrors();

        return Receipt::where('receipt_type', 'payment')->firstOrFail();
    }

    private function void(User $as, Receipt $receipt, string $reason = 'Recorded as cash, was a cheque')
    {
        return $this->actingAs($as)->putJson('/receipts/'.$receipt->id.'/void', ['reason' => $reason]);
    }

    public function test_voiding_a_cash_receipt_puts_the_invoice_back(): void
    {
        $receipt = $this->collect('Cash');
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);

        $this->void($this->approver(), $receipt)->assertOk();

        $invoice = ArInvoice::firstOrFail();
        $this->assertSame(3000.0, (float) $invoice->balance_due, 'The customer owes it again.');
        $this->assertSame(0.0, (float) $invoice->amount_paid);
        $this->assertSame('voided', $receipt->fresh('status')->status->slug);
        $this->assertNull($receipt->fresh()->held_by_employee_id, 'Nobody is carrying money that was never taken.');
    }

    public function test_the_ledger_is_reversed_not_edited(): void
    {
        $receipt = $this->collect('Cash');
        $before = JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count();

        $this->void($this->approver(), $receipt)->assertOk();

        // The original entry stays and a reversal joins it: an auditor sees
        // what was recorded and what undid it.
        $this->assertGreaterThan($before, JournalEntry::where('source_type', Receipt::class)
            ->where('source_id', $receipt->id)->count());
    }

    public function test_it_says_who_voided_it_and_why(): void
    {
        $receipt = $this->collect('Cash');
        $approver = $this->approver();

        $this->void($approver, $receipt, 'Paid by cheque, typed as cash')->assertOk();

        $row = DB::table('void_receipts')->where('receipt_id', $receipt->id)->first();
        $this->assertNotNull($row, 'The reason belongs on the record.');
        $this->assertSame('Paid by cheque, typed as cash', $row->reason);
        $this->assertSame($approver->id, $row->voided_by_id);
    }

    public function test_an_uncleared_cheque_does_not_credit_the_invoice_back(): void
    {
        // It never reduced the balance in the first place — a cheque is not
        // money until the bank pays it — so putting 3,000 back would hand the
        // customer a payment they never made.
        $receipt = $this->collect('Check');
        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->balance_due);

        $this->void($this->approver(), $receipt)->assertOk();

        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->balance_due);
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->amount_paid);
    }

    public function test_voiding_a_cheque_takes_it_out_of_the_register(): void
    {
        $receipt = $this->collect('Check');
        $this->assertSame(1, Check::where('check_number', 'CHK-WRONG')->count());

        $this->void($this->approver(), $receipt)->assertOk();

        $this->assertSame(0, Check::where('check_number', 'CHK-WRONG')->count(),
            'A cheque typed by mistake was never on hand.');
    }

    public function test_a_remitted_receipt_is_refused_with_the_remittance_named(): void
    {
        $receipt = $this->collect('Cash');
        $receipt->update(['remittance_id' => $this->remittanceFor($receipt)]);

        $this->void($this->approver(), $receipt)
            ->assertStatus(422)
            ->assertJsonValidationErrors('receipt');

        $this->assertNotSame('voided', $receipt->fresh('status')->status->slug);
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due, 'Nothing moved.');
    }

    public function test_an_encoder_cannot_void(): void
    {
        $receipt = $this->collect('Cash');

        // The person who typed it is not the person who undoes it.
        $this->void($this->user, $receipt)->assertForbidden();

        $this->assertNotSame('voided', $receipt->fresh('status')->status->slug);
    }

    public function test_it_cannot_be_voided_twice(): void
    {
        $receipt = $this->collect('Cash');
        $approver = $this->approver();

        $this->void($approver, $receipt)->assertOk();
        $this->void($approver, $receipt)->assertStatus(422);

        $this->assertSame(3000.0, (float) ArInvoice::firstOrFail()->balance_due, 'Not credited back twice.');
        $this->assertSame(1, DB::table('void_receipts')->where('receipt_id', $receipt->id)->count());
    }

    public function test_a_reason_is_required(): void
    {
        $receipt = $this->collect('Cash');

        $this->actingAs($this->approver())
            ->putJson('/receipts/'.$receipt->id.'/void', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    private function remittanceFor(Receipt $receipt): int
    {
        return DB::table('remittances')->insertGetId([
            'remittance_no' => 'REM-TEST-1',
            'remittance_date' => now()->toDateString(),
            'total_amount' => $receipt->amount_paid,
            'received_amount' => $receipt->amount_paid,
            'summary' => json_encode([]),
            'status_id' => \App\Models\ListStatus::where('slug', 'pending')->value('id'),
            'created_by_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
