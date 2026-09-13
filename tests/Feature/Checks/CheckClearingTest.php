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
use App\Notifications\BouncedCheckNotification;
use App\Services\Modules\CheckRegisterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckClearingTest extends TestCase
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

    public function test_bouncing_leaves_the_invoice_owed_and_posts_nothing(): void
    {
        $receipt = $this->receipt('Check');
        // checks.check_number is NOT NULL and defaults to reference_number,
        // which the receipt() helper never sets (see Task 4's report on the
        // same gap) — set it so the bare registerReceived() call below
        // doesn't trip an unrelated constraint.
        $receipt->update(['reference_number' => '000123']);
        $check = app(CheckRegisterClass::class)->registerReceived($receipt);
        $balanceBefore = (float) $receipt->arInvoice->balance_due;

        app(CheckRegisterClass::class)->markBounced($check, 'Insufficient funds');

        $this->assertSame(Check::STATUS_BOUNCED, $check->fresh()->status);
        $this->assertSame('Insufficient funds', $check->fresh()->bounce_reason);
        $this->assertSame($balanceBefore, (float) $receipt->arInvoice->fresh()->balance_due);
        $this->assertSame(0, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
    }

    public function test_a_cleared_check_cannot_be_bounced(): void
    {
        $receipt = $this->receipt('Check');
        $receipt->update(['reference_number' => '000123']);
        $check = app(CheckRegisterClass::class)->registerReceived($receipt);
        $check->update(['status' => Check::STATUS_CLEARED]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CheckRegisterClass::class)->markBounced($check, 'Too late');
    }

    /**
     * `notifyReceivingRep()` resolves the User via the Employee side of the
     * relation (User::employee() is hasOne(Employee, 'user_id') — Users don't
     * have an employee_id column). This seeds an Employee linked to a User via
     * user_id, and confirms the notification actually reaches that User.
     */
    public function test_bouncing_notifies_the_receiving_rep(): void
    {
        Notification::fake();

        $receipt = $this->receipt('Check');
        $receipt->update(['reference_number' => '000123']);
        $repUser = User::factory()->create();
        Employee::where('id', $receipt->arInvoice->sales_order->sales_rep_id)
            ->update(['user_id' => $repUser->id]);

        $check = app(CheckRegisterClass::class)->registerReceived($receipt);

        app(CheckRegisterClass::class)->markBounced($check, 'Insufficient funds');

        Notification::assertSentTo($repUser, BouncedCheckNotification::class);
    }
}
