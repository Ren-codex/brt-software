<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Modules\ArInvoiceClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Collecting against a credit sale's invoice, where the customer settles with
 * more than one method. Each split becomes its own receipt; transfers and
 * checks carry the bank account and reference the collection can be
 * reconciled by.
 */
class ArInvoiceSplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    private ArInvoice $invoice;
    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'pending' => 'Pending', 'paid' => 'Paid', 'unpaid' => 'Unpaid',
            'closed' => 'Closed', 'partially-paid' => 'Partially Paid',
            'for-payment' => 'For Payment',
        ] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }

        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create([
            'name' => 'ABC Trading', 'address' => 'Zamboanga City',
            'contact_number' => '09170000000', 'is_active' => 1, 'added_by_id' => $user->id,
        ]);

        // Settling an invoice in full awards the rep their incentive, so the
        // order needs a rep for that path to run.
        $rep = Employee::create([
            'lastname' => 'Sales', 'firstname' => 'John', 'mobile' => '09170000001',
            'birthdate' => '1990-01-01', 'sex' => 'Male', 'religion' => 'N/A',
            'is_regular' => 1, 'is_blacklisted' => 0,
        ]);

        $order = SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => 10000, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $user->id, 'requires_batch_approval' => false,
            'sales_rep_id' => $rep->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);

        $this->invoice = ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => 10000,
            'amount_paid' => 0, 'balance_due' => 10000, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
        ]);

        $this->bank = BankAccount::create([
            'bank_name' => 'BDO', 'account_name' => 'BRT Operating', 'account_number' => '001',
            'gl_code' => '1020', 'balance' => 0, 'is_active' => 1,
        ]);
    }

    private function pay(array $splits)
    {
        $request = new Request();
        $request->merge([
            'id' => $this->invoice->id,
            'payment_date' => now()->toDateString(),
            'splits' => $splits,
        ]);

        return app(ArInvoiceClass::class)->payment($request);
    }

    public function test_a_split_collection_records_the_bank_and_reference_per_method(): void
    {
        $this->pay([
            ['payment_mode' => 'Cash', 'amount' => 4000],
            ['payment_mode' => 'Bank Transfer', 'amount' => 5000, 'bank_account_id' => $this->bank->id, 'reference_number' => 'TRN-4410'],
            ['payment_mode' => 'Check', 'amount' => 1000, 'reference_number' => '000456'],
        ]);

        $transfer = Receipt::where('payment_mode', 'Bank Transfer')->firstOrFail();
        $this->assertEquals($this->bank->id, $transfer->bank_account_id);
        $this->assertEquals('TRN-4410', $transfer->reference_number);

        $check = Receipt::where('payment_mode', 'Check')->firstOrFail();
        $this->assertNull($check->bank_account_id);
        $this->assertEquals('000456', $check->reference_number);

        $cash = Receipt::where('payment_mode', 'Cash')->firstOrFail();
        $this->assertNull($cash->bank_account_id);
        $this->assertNull($cash->reference_number);
    }

    public function test_the_invoice_is_settled_by_the_combined_splits(): void
    {
        $this->pay([
            ['payment_mode' => 'Cash', 'amount' => 4000],
            ['payment_mode' => 'Bank Transfer', 'amount' => 6000, 'bank_account_id' => $this->bank->id, 'reference_number' => 'TRN-1'],
        ]);

        $invoice = $this->invoice->fresh();

        $this->assertEquals(10000.0, round((float) $invoice->amount_paid, 2));
        $this->assertEquals(0.0, round((float) $invoice->balance_due, 2));
        $this->assertEquals('paid', $invoice->status->slug);
    }

    public function test_a_partial_collection_leaves_the_balance_outstanding(): void
    {
        $this->pay([
            ['payment_mode' => 'Cash', 'amount' => 2500],
            ['payment_mode' => 'Check', 'amount' => 1500, 'reference_number' => '000789'],
        ]);

        $invoice = $this->invoice->fresh();

        $this->assertEquals(4000.0, round((float) $invoice->amount_paid, 2));
        $this->assertEquals(6000.0, round((float) $invoice->balance_due, 2));
        $this->assertEquals('partially-paid', $invoice->status->slug);
    }

    public function test_a_transfer_without_a_reference_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->pay([
            ['payment_mode' => 'Cash', 'amount' => 5000],
            ['payment_mode' => 'Bank Transfer', 'amount' => 5000, 'bank_account_id' => $this->bank->id],
        ]);
    }

    public function test_a_check_without_a_number_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->pay([['payment_mode' => 'Check', 'amount' => 5000]]);
    }

    public function test_collecting_more_than_the_balance_is_still_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->pay([
            ['payment_mode' => 'Cash', 'amount' => 9000],
            ['payment_mode' => 'Cash', 'amount' => 2000],
        ]);
    }

    public function test_a_cash_only_collection_needs_no_reference(): void
    {
        $this->pay([['payment_mode' => 'Cash', 'amount' => 3000]]);

        $this->assertEquals(3000.0, round((float) Receipt::firstOrFail()->amount_paid, 2));
    }
}
