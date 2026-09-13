<?php

namespace Tests\Feature\Checks;

use App\Models\ArInvoice;
use App\Models\Check;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Modules\ArInvoiceClass;
use App\Services\Modules\CheckMonitoringClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A check's own date is the day it can be cashed, and the register exists to
 * track exactly that. Recording the payment date instead would make the whole
 * register useless for the thing it is for — knowing which checks fall due when.
 */
class CheckDateCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): ArInvoice
    {
        $user = User::factory()->create();
        Auth::login($user);

        foreach ([
            'unpaid' => 'Unpaid', 'pending' => 'Pending', 'for-payment' => 'For Payment',
            'paid' => 'Paid', 'closed' => 'Closed', 'partially-paid' => 'Partially Paid',
        ] as $slug => $name) {
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
            'so_number' => 'SO-' . uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => 5000, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $user->id,
            'requires_batch_approval' => false, 'sales_rep_id' => $rep->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);

        return ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-' . uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => 5000,
            'amount_paid' => 0, 'balance_due' => 5000, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
        ]);
    }

    private function pay(ArInvoice $invoice, array $split): void
    {
        $request = new Request();
        $request->merge([
            'id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'splits' => [$split],
        ]);

        app(ArInvoiceClass::class)->payment($request);
    }

    public function test_the_check_date_entered_is_the_one_recorded(): void
    {
        $invoice = $this->invoice();
        $checkDate = now()->addDays(9)->toDateString();

        $this->pay($invoice, [
            'payment_mode' => 'Check', 'amount' => 5000,
            'reference_number' => '000456', 'check_date' => $checkDate,
        ]);

        $receipt = Receipt::where('payment_mode', 'Check')->firstOrFail();
        $check = Check::where('source_id', $receipt->id)->firstOrFail();

        $this->assertSame($checkDate, $check->check_date->toDateString(), 'The register must hold the date on the check.');
        $this->assertSame($checkDate, $receipt->check_date->toDateString(), 'The receipt must hold it too.');
        $this->assertNotSame(now()->toDateString(), $check->check_date->toDateString());
    }

    public function test_a_check_without_its_date_is_refused(): void
    {
        $invoice = $this->invoice();

        $this->expectException(ValidationException::class);

        $this->pay($invoice, [
            'payment_mode' => 'Check', 'amount' => 5000, 'reference_number' => '000456',
        ]);
    }

    public function test_a_cash_payment_needs_no_check_date(): void
    {
        $invoice = $this->invoice();

        $this->pay($invoice, ['payment_mode' => 'Cash', 'amount' => 5000]);

        $this->assertSame(0.0, (float) $invoice->fresh()->balance_due);
    }

    public function test_correcting_the_date_updates_the_register_too(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, [
            'payment_mode' => 'Check', 'amount' => 5000,
            'reference_number' => '000456', 'check_date' => now()->addDays(9)->toDateString(),
        ]);
        $receipt = Receipt::where('payment_mode', 'Check')->firstOrFail();
        $corrected = now()->addDays(20)->toDateString();

        app(CheckMonitoringClass::class)->updateCheckDate($receipt->id, $corrected);

        $check = Check::where('source_id', $receipt->id)->firstOrFail();
        $this->assertSame($corrected, $check->check_date->toDateString(), 'A corrected date must reach the register.');
    }

    public function test_confirming_with_a_corrected_date_updates_the_register(): void
    {
        $invoice = $this->invoice();
        $this->pay($invoice, [
            'payment_mode' => 'Check', 'amount' => 5000,
            'reference_number' => '000456', 'check_date' => now()->addDays(9)->toDateString(),
        ]);
        $receipt = Receipt::where('payment_mode', 'Check')->firstOrFail();
        $corrected = now()->addDays(15)->toDateString();

        app(ArInvoiceClass::class)->confirmCheck($receipt->id, 'BDO', $corrected);

        $check = Check::where('source_id', $receipt->id)->firstOrFail();
        $this->assertSame($corrected, $check->check_date->toDateString());
    }
}
