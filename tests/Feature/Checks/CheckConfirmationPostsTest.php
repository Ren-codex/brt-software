<?php

namespace Tests\Feature\Checks;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckConfirmationPostsTest extends TestCase
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

    public function test_confirming_a_check_posts_the_entry_and_reduces_the_balance(): void
    {
        $receipt = $this->receipt('Check');
        $invoice = $receipt->arInvoice;
        $this->actingAs(User::factory()->create());

        app(\App\Services\Modules\ArInvoiceClass::class)->confirmCheck($receipt->id, 'BDO', now()->toDateString());

        $this->assertSame(1, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
        $this->assertSame(0.0, (float) $invoice->fresh()->balance_due);
    }

    public function test_confirming_a_check_twice_is_rejected_and_posts_only_once(): void
    {
        $receipt = $this->receipt('Check');
        $this->actingAs(User::factory()->create());

        app(\App\Services\Modules\ArInvoiceClass::class)->confirmCheck($receipt->id, 'BDO', now()->toDateString());

        $this->expectException(ValidationException::class);

        try {
            app(\App\Services\Modules\ArInvoiceClass::class)->confirmCheck($receipt->id, 'BDO', now()->toDateString());
        } finally {
            $this->assertSame(1, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
        }
    }
}
