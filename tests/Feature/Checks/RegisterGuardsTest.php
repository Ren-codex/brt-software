<?php

namespace Tests\Feature\Checks;

use App\Models\BankAccount;
use App\Models\Check;
use App\Models\JournalEntry;
use App\Models\ListSupplier;
use App\Models\PurchaseOrder;
use App\Models\ReceivedStock;
use App\Models\ReceivedStockPayment;
use App\Models\User;
use App\Services\Modules\CheckRegisterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Two ways the register could move money it should not: entering a payment that
 * was never a check (it already posted, so clearing would post it twice), and
 * losing a recorded bounce because telling the rep about it failed.
 */
class RegisterGuardsTest extends TestCase
{
    use RefreshDatabase;
    use MakesCheckFixtures;

    private function payment(string $mode): ReceivedStockPayment
    {
        $user = User::factory()->create();
        $supplier = ListSupplier::create([
            'name' => 'Supplier ' . uniqid(), 'address' => 'Zamboanga City',
            'contact_person' => 'Roberto Cruz', 'contact_number' => '09170000000',
            'email' => uniqid() . '@example.com', 'tin' => '000-000-000',
            'is_active' => 1, 'is_blacklisted' => 0,
        ]);
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id, 'po_number' => 'PO-' . uniqid(),
            'po_date' => now()->toDateString(), 'total_amount' => 5000,
            'status_id' => 1, 'created_by_id' => $user->id,
        ]);
        $stock = ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplier->id,
            'received_no' => 'RS-' . uniqid(), 'received_date' => now(),
            'payment_mode' => 'Credit', 'amount_paid' => 0, 'received_by_id' => $user->id,
        ]);
        $bank = BankAccount::firstOrCreate(['gl_code' => '1020'], ['bank_name' => 'BDO', 'account_name' => 'BRT']);

        return ReceivedStockPayment::create([
            'received_stock_id' => $stock->id, 'payment_date' => now()->toDateString(),
            'payment_mode' => $mode, 'amount_paid' => 5000,
            'bank_account_id' => $bank->id, 'bank_name' => 'BDO',
            'reference_number' => 'REF-' . uniqid(), 'created_by_id' => $user->id,
        ]);
    }

    public function test_a_payment_that_is_not_a_check_cannot_enter_the_register(): void
    {
        $payment = $this->payment('Cash');

        $this->expectException(ValidationException::class);

        app(CheckRegisterClass::class)->registerIssued($payment);
    }

    public function test_a_check_payment_still_enters_the_register(): void
    {
        $check = app(CheckRegisterClass::class)->registerIssued($this->payment('Check'));

        $this->assertSame(Check::DIRECTION_ISSUED, $check->direction);
        $this->assertSame(Check::STATUS_PENDING, $check->status);
    }

    public function test_a_failed_notification_does_not_erase_the_bounce(): void
    {
        $receipt = $this->receipt('Check', '000123');
        $check = app(CheckRegisterClass::class)->registerReceived($receipt);

        // The rep must have a real user account, or notifyReceivingRep() finds
        // nobody and the failure path is never reached — the test would pass
        // while proving nothing.
        $repUser = User::factory()->create();
        \App\Models\Employee::where('id', $check->received_by_id)->update(['user_id' => $repUser->id]);
        $this->assertNotNull(
            User::whereHas('employee', fn ($q) => $q->where('id', $check->received_by_id))->first(),
            'fixture must link the rep to a user, or this test proves nothing'
        );

        // The real hazard is a controller wrapping this in a transaction: if the
        // notification throws, the rollback would erase a bounce that genuinely
        // happened. Telling the rep is best-effort; recording the bounce is not.
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('mail server down'));

        \Illuminate\Support\Facades\DB::transaction(function () use ($check) {
            app(CheckRegisterClass::class)->markBounced($check, 'Insufficient funds');
        });

        $this->assertSame(Check::STATUS_BOUNCED, $check->fresh()->status, 'A recorded bounce must outlive a failed notification.');
        $this->assertSame(0, JournalEntry::where('source_id', $check->source_id)->count());
    }
}
