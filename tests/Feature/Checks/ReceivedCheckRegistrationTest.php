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
use App\Services\Modules\CheckRegisterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivedCheckRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function receipt(string $mode): Receipt
    {
        foreach (['unpaid' => 'Unpaid', 'pending' => 'Pending', 'for-payment' => 'For Payment', 'paid' => 'Paid', 'closed' => 'Closed', 'partially-paid' => 'Partially Paid'] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => $name, 'text_color' => '#fff', 'bg_color' => '#333']);
        }
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Juan', 'address' => 'Z', 'contact_number' => '09170000000',
            'is_active' => 1, 'added_by_id' => $user->id,
        ]);
        $rep = Employee::create([
            'lastname' => 'Cruz', 'firstname' => 'Juan', 'mobile' => '09170000002',
            'birthdate' => '1990-01-01', 'sex' => 'Male', 'religion' => 'N/A',
            'is_regular' => 1, 'is_blacklisted' => 0,
        ]);
        $order = SalesOrder::create([
            'so_number' => 'SO-' . uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => 5000, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $user->id, 'requires_batch_approval' => false,
            'sales_rep_id' => $rep->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);
        $invoice = ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-' . uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => 5000,
            'amount_paid' => 0, 'balance_due' => 5000, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
        ]);

        return Receipt::create([
            'ar_invoice_id' => $invoice->id, 'customer_id' => $customer->id,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'receipt_number' => 'OR-' . uniqid(), 'receipt_type' => 'payment',
            'receipt_date' => now()->toDateString(), 'amount_paid' => 5000,
            'balance_due' => 5000, 'payment_mode' => $mode,
        ]);
    }

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
}
