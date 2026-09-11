<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Receipt;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for punch-list #11/#12: a check must be manually
 * confirmed (with a bank name) before its amount counts toward the AR
 * invoice's balance. Before this, any payment_mode — including Check —
 * reduced balance_due immediately, so an uncleared check looked identical to
 * cash on the customer's account.
 */
class ArInvoiceCheckConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ArInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach ([
            'unpaid' => 'Unpaid', 'partially-paid' => 'Partially Paid', 'paid' => 'Paid',
            'for-payment' => 'For Payment', 'closed' => 'Closed', 'pending' => 'Pending',
        ] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => $name, 'text_color' => '#fff', 'bg_color' => '#333']);
        }

        $this->user = User::factory()->create();
        $role = ListRole::create(['name' => 'R'.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $this->user->id]);
        $module = Module::where('key', 'sales')->firstOrFail();
        foreach (['ar_invoices', 'receipts'] as $submoduleKey) {
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id,
                'access_level' => 'encoder',
            ]);
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id,
                'access_level' => 'view',
            ]);
        }

        $customer = Customer::create([
            'name' => 'Juan Dela Cruz', 'address' => 'Zamboanga', 'contact_number' => '09170000000',
            'is_active' => 1, 'added_by_id' => $this->user->id,
        ]);

        $rep = \App\Models\Employee::create([
            'lastname' => 'Cruz', 'firstname' => 'Juan', 'mobile' => '09170000002',
            'birthdate' => '1990-01-01', 'sex' => 'Male', 'religion' => 'N/A',
            'is_regular' => 1, 'is_blacklisted' => 0,
        ]);

        $order = SalesOrder::create([
            'so_number' => 'SO-'.uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => 10000, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $this->user->id, 'sales_rep_id' => $rep->id,
            'requires_batch_approval' => false,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);

        $this->invoice = ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-'.uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => 10000,
            'amount_paid' => 0, 'balance_due' => 10000, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);
    }

    private function payFullyByCheck(): Receipt
    {
        $this->actingAs($this->user)->putJson('/ar-invoices/'.$this->invoice->id, [
            'id' => $this->invoice->id,
            'option' => 'payment',
            'balance_due' => 10000,
            'payment_date' => now()->toDateString(),
            'splits' => [
                ['payment_mode' => 'Check', 'amount' => 10000, 'reference_number' => '000123'],
            ],
        ])->assertOk();

        return Receipt::where('ar_invoice_id', $this->invoice->id)->firstOrFail();
    }

    public function test_a_100_percent_check_payment_leaves_the_invoice_untouched_until_confirmed(): void
    {
        $this->payFullyByCheck();

        $invoice = $this->invoice->fresh();
        $this->assertEquals(0.0, round((float) $invoice->amount_paid, 2));
        $this->assertEquals(10000.0, round((float) $invoice->balance_due, 2));
        $this->assertEquals('for-payment', $invoice->status->slug);
    }

    public function test_confirming_the_check_applies_it_to_the_balance_and_settles_the_invoice(): void
    {
        $receipt = $this->payFullyByCheck();

        $this->actingAs($this->user)
            ->putJson("/receipts/{$receipt->id}/confirm-check", ['bank_name' => 'BDO'])
            ->assertOk();

        $invoice = $this->invoice->fresh();
        $this->assertEquals(10000.0, round((float) $invoice->amount_paid, 2));
        $this->assertEquals(0.0, round((float) $invoice->balance_due, 2));
        $this->assertEquals('paid', $invoice->status->slug);

        $receipt = $receipt->fresh();
        $this->assertEquals('BDO', $receipt->bank_name);
        $this->assertNotNull($receipt->confirmed_at);
    }

    public function test_confirming_without_a_bank_name_is_rejected(): void
    {
        $receipt = $this->payFullyByCheck();

        $this->actingAs($this->user)
            ->putJson("/receipts/{$receipt->id}/confirm-check", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('bank_name');

        $this->assertEquals(10000.0, round((float) $this->invoice->fresh()->balance_due, 2));
    }

    public function test_a_non_check_receipt_cannot_be_confirmed_this_way(): void
    {
        $this->actingAs($this->user)->putJson('/ar-invoices/'.$this->invoice->id, [
            'id' => $this->invoice->id,
            'option' => 'payment',
            'balance_due' => 10000,
            'payment_date' => now()->toDateString(),
            'splits' => [['payment_mode' => 'Cash', 'amount' => 10000]],
        ])->assertOk();

        $receipt = Receipt::where('payment_mode', 'Cash')->firstOrFail();

        $this->actingAs($this->user)
            ->putJson("/receipts/{$receipt->id}/confirm-check", ['bank_name' => 'BDO'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_mode');
    }

    public function test_a_check_cannot_be_confirmed_twice(): void
    {
        $receipt = $this->payFullyByCheck();

        $this->actingAs($this->user)
            ->putJson("/receipts/{$receipt->id}/confirm-check", ['bank_name' => 'BDO'])
            ->assertOk();

        $this->actingAs($this->user)
            ->putJson("/receipts/{$receipt->id}/confirm-check", ['bank_name' => 'Metrobank'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirmed_at');
    }
}
