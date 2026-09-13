<?php

namespace Tests\Feature\Checks;

use App\Models\ArInvoice;
use App\Models\Check;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Modules\ArInvoiceClass;
use App\Services\Modules\CheckRegisterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReceivedCheckRegistrationTest extends TestCase
{
    use RefreshDatabase;
    use MakesCheckFixtures;

    public function test_recording_a_check_receipt_creates_a_pending_register_row(): void
    {
        $receipt = $this->receipt('Check');

        $check = app(CheckRegisterClass::class)
            ->registerReceived($receipt, ['check_number' => '0012345', 'check_date' => now()->addDays(9)->toDateString()]);

        $this->assertSame(Check::STATUS_PENDING, $check->status);
        $this->assertSame(Check::DIRECTION_RECEIVED, $check->direction);
        $this->assertSame((float) $receipt->amount_paid, (float) $check->amount);
        $this->assertSame($receipt->customer_id, $check->customer_id);
    }

    public function test_recording_a_check_receipt_posts_no_journal_entry(): void
    {
        $receipt = $this->receipt('Check');

        app(CheckRegisterClass::class)
            ->registerReceived($receipt, ['check_number' => '0012345', 'check_date' => now()->addDays(9)->toDateString()]);

        $this->assertSame(0, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
        $this->assertSame(0, JournalEntry::count());
    }

    /**
     * `checks.check_date` is NOT NULL with no DB default, so a caller that
     * registers a check without passing `check_date` explicitly must not
     * blow up. The receipt's own check_date is preferred; when that isn't
     * set yet (as is true for every receipt built by the `receipt()` helper
     * here), it falls back to the receipt date rather than inserting null.
     */
    public function test_registering_a_check_with_no_attributes_defaults_the_check_date(): void
    {
        // The receipt() helper (like the real payment flow) doesn't set
        // check_date, but does carry a check_number via reference_number —
        // pass one here so this test isolates the check_date default rather
        // than tripping the unrelated check_number NOT NULL constraint.
        $receipt = $this->receipt('Check', '000123');

        $check = app(CheckRegisterClass::class)->registerReceived($receipt);

        $this->assertNotNull($check->check_date);
        $this->assertSame($receipt->receipt_date, $check->check_date->toDateString());
    }

    /**
     * `checks.check_number` is NOT NULL with no default. A blank check
     * number is not a usable register entry — it isn't fabricated (unlike
     * check_date), it's rejected with a clear message instead of a cryptic
     * SQL NOT NULL error.
     */
    public function test_registering_a_check_with_no_check_number_is_rejected(): void
    {
        $receipt = $this->receipt('Check');

        $this->expectException(ValidationException::class);
        app(CheckRegisterClass::class)->registerReceived($receipt);
    }

    /**
     * End-to-end coverage of the actual integration point: paying an invoice
     * with a Check split through ArInvoiceClass::payment() must itself create
     * the pending register row, not just the CheckRegisterClass unit calls
     * above. Fixture shape reused from ArInvoiceSplitPaymentTest.
     */
    public function test_paying_with_a_check_creates_a_pending_register_row(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['unpaid' => 'Unpaid', 'pending' => 'Pending', 'for-payment' => 'For Payment', 'paid' => 'Paid', 'closed' => 'Closed', 'partially-paid' => 'Partially Paid'] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => $name, 'text_color' => '#fff', 'bg_color' => '#333']);
        }

        $customer = Customer::create([
            'name' => 'ABC Trading', 'address' => 'Zamboanga City',
            'contact_number' => '09170000000', 'is_active' => 1, 'added_by_id' => $user->id,
        ]);
        $rep = Employee::create([
            'lastname' => 'Sales', 'firstname' => 'John', 'mobile' => '09170000001',
            'birthdate' => '1990-01-01', 'sex' => 'Male', 'religion' => 'N/A',
            'is_regular' => 1, 'is_blacklisted' => 0,
        ]);
        $order = SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => 5000, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $user->id, 'requires_batch_approval' => false,
            'sales_rep_id' => $rep->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);
        $invoice = ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => 5000,
            'amount_paid' => 0, 'balance_due' => 5000, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
        ]);

        $request = new Request();
        $request->merge([
            'id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'splits' => [
                ['payment_mode' => 'Check', 'amount' => 5000, 'reference_number' => '000456'],
            ],
        ]);

        app(ArInvoiceClass::class)->payment($request);

        $receipt = Receipt::where('payment_mode', 'Check')->firstOrFail();
        $check = Check::where('source_type', Receipt::class)->where('source_id', $receipt->id)->first();

        $this->assertNotNull($check);
        $this->assertSame(Check::STATUS_PENDING, $check->status);
        $this->assertSame(Check::DIRECTION_RECEIVED, $check->direction);
    }
}
