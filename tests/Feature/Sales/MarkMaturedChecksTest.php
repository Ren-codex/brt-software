<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Regression coverage for punch-list #5: released checks auto-mature after 7 days. */
class MarkMaturedChecksTest extends TestCase
{
    use RefreshDatabase;

    private function makeCheckReceipt(string $checkStatus, ?Carbon $releasedAt): Receipt
    {
        $user = User::factory()->create();
        foreach (['for-payment', 'pending'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace('-', ' ', $slug)), 'text_color' => '#fff', 'bg_color' => '#333']);
        }

        $customer = Customer::create([
            'name' => 'C', 'address' => 'A', 'contact_number' => '0900'.random_int(1000000, 9999999),
            'is_active' => 1, 'added_by_id' => $user->id,
        ]);

        $order = SalesOrder::create([
            'so_number' => 'SO-'.uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => 1000, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $user->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);

        $invoice = ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-'.uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => 1000,
            'amount_paid' => 0, 'balance_due' => 1000, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);

        return Receipt::create([
            'receipt_number' => 'OR-'.uniqid(), 'receipt_type' => 'payment',
            'receipt_date' => now()->toDateString(), 'amount_paid' => 1000, 'balance_due' => 1000,
            'payment_mode' => 'Check', 'reference_number' => '000123',
            'ar_invoice_id' => $invoice->id, 'customer_id' => $customer->id,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'check_status' => $checkStatus, 'released_at' => $releasedAt,
        ]);
    }

    public function test_a_check_released_more_than_7_days_ago_matures(): void
    {
        $receipt = $this->makeCheckReceipt('released', now()->subDays(8));

        $this->artisan('checks:mark-matured');

        $this->assertEquals('matured', $receipt->fresh()->check_status);
    }

    public function test_a_check_released_less_than_7_days_ago_stays_released(): void
    {
        $receipt = $this->makeCheckReceipt('released', now()->subDays(3));

        $this->artisan('checks:mark-matured');

        $this->assertEquals('released', $receipt->fresh()->check_status);
    }

    public function test_a_check_still_on_hand_is_not_matured_regardless_of_age(): void
    {
        $receipt = $this->makeCheckReceipt('on_hand', null);

        $this->artisan('checks:mark-matured');

        $this->assertEquals('on_hand', $receipt->fresh()->check_status);
    }
}
