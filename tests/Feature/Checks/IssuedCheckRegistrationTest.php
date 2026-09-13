<?php

namespace Tests\Feature\Checks;

use App\Models\ListStatus;
use App\Models\ListSupplier;
use App\Models\PurchaseOrder;
use App\Models\ReceivedStock;
use App\Models\ReceivedStockPayment;
use App\Models\User;
use App\Services\Modules\CheckRegisterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Coverage for CheckRegisterClass::registerIssued() — the AP-side twin of
 * registerReceived(). No prior test class exercised this method directly.
 */
class IssuedCheckRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payment(?string $referenceNumber = null): ReceivedStockPayment
    {
        $user = User::factory()->create();
        $status = ListStatus::firstOrCreate(
            ['slug' => 'pending'],
            ['name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333']
        );
        $supplier = ListSupplier::create([
            'name' => 'Supplier', 'address' => 'Addr', 'contact_person' => 'P',
            'contact_number' => '09000000000', 'email' => 's@test.com', 'tin' => '000',
        ]);
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id, 'po_date' => now()->toDateString(),
            'total_amount' => 5000, 'status_id' => $status->id, 'created_by_id' => $user->id,
        ]);
        $received = ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplier->id,
            'received_date' => now()->toDateString(), 'received_no' => 'RS-' . uniqid(),
            'received_by_id' => $user->id,
        ]);

        return ReceivedStockPayment::create([
            'received_stock_id' => $received->id, 'payment_date' => now()->toDateString(),
            'payment_mode' => 'Check', 'amount_paid' => 5000,
            'reference_number' => $referenceNumber,
        ]);
    }

    /**
     * `checks.check_number` is NOT NULL with no default. A blank check
     * number is not a usable register entry — it isn't fabricated, it's
     * rejected with a clear message instead of a cryptic SQL NOT NULL error.
     */
    public function test_registering_an_issued_check_with_no_check_number_is_rejected(): void
    {
        $payment = $this->payment();

        $this->expectException(ValidationException::class);
        app(CheckRegisterClass::class)->registerIssued($payment);
    }

    public function test_registering_an_issued_check_with_a_number_succeeds(): void
    {
        $payment = $this->payment('000789');

        $check = app(CheckRegisterClass::class)->registerIssued($payment);

        $this->assertSame('000789', $check->check_number);
        $this->assertSame(\App\Models\Check::DIRECTION_ISSUED, $check->direction);
    }
}
