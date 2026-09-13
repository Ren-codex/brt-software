<?php

namespace Tests\Feature\Checks;

use App\Models\BankAccount;
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
 * The same bank account must not issue the same check number twice. Enforced
 * in registerIssued() rather than a DB unique index: received checks have a
 * null bank_account_id, and MySQL treats nulls as distinct, so an index would
 * silently fail to cover exactly the rows it appeared to.
 */
class DuplicateCheckNumberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Copied from IssuedCheckClearingTest/IssuedCheckPostingTest (Tasks 6-8)
     * rather than shared via a trait, matching how MakesCheckFixtures itself
     * started out as a per-test copy before being extracted — this task
     * isn't the one refactoring the supplier-payment side.
     *
     * @return array{0: \App\Models\ReceivedStock, 1: \App\Models\ReceivedStockPayment}
     */
    private function supplierPayment(string $mode, float $amount): array
    {
        $user = User::factory()->create();

        $supplier = ListSupplier::create([
            'name' => 'Test Supplier ' . uniqid(), 'address' => 'Zamboanga City',
            'contact_person' => 'Roberto Cruz', 'contact_number' => '09170000000',
            'email' => uniqid() . '@example.com', 'tin' => '000-000-000',
            'is_active' => 1, 'is_blacklisted' => 0,
        ]);

        $status = ListStatus::firstOrCreate(
            ['slug' => 'pending'],
            ['name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333']
        );

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-' . uniqid(),
            'po_date' => now()->toDateString(),
            'total_amount' => $amount,
            'created_by_id' => $user->id,
            'status_id' => $status->id,
        ]);

        $stock = ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplier->id,
            'received_no' => 'RS-' . uniqid(), 'received_date' => now(),
            'payment_mode' => 'Credit', 'amount_paid' => 0, 'received_by_id' => $user->id,
        ]);

        $bankAccount = BankAccount::firstOrCreate(
            ['gl_code' => '1020'],
            ['bank_name' => 'BDO', 'account_name' => 'BRT']
        );

        $payment = ReceivedStockPayment::create([
            'received_stock_id' => $stock->id,
            'payment_date' => now()->toDateString(),
            'payment_mode' => $mode,
            'amount_paid' => $amount,
            'bank_account_id' => $bankAccount->id,
            'bank_name' => 'BDO',
            'reference_number' => 'CHK-' . uniqid(),
            'created_by_id' => $user->id,
        ]);

        return [$stock, $payment];
    }

    public function test_the_same_account_cannot_issue_one_number_twice(): void
    {
        [$stock, $payment] = $this->supplierPayment('Check', 1000);
        app(CheckRegisterClass::class)->registerIssued($payment, ['check_number' => '0012345']);

        [$stock2, $payment2] = $this->supplierPayment('Check', 2000);

        $this->expectException(ValidationException::class);
        app(CheckRegisterClass::class)->registerIssued($payment2, ['check_number' => '0012345']);
    }

    /**
     * A bounced check is normally replaced by a different physical check
     * with its own number — but the bounced number itself was still spent
     * (written, then dishonored), so it must not be reusable either.
     */
    public function test_a_bounced_checks_number_still_blocks_reuse(): void
    {
        [$stock, $payment] = $this->supplierPayment('Check', 1000);
        $check = app(CheckRegisterClass::class)->registerIssued($payment, ['check_number' => '0099999']);
        $check->update(['status' => \App\Models\Check::STATUS_BOUNCED]);

        [$stock2, $payment2] = $this->supplierPayment('Check', 2000);

        $this->expectException(ValidationException::class);
        app(CheckRegisterClass::class)->registerIssued($payment2, ['check_number' => '0099999']);
    }

    /**
     * bank_account_id is null for every received check, so two different
     * customers' checks sharing a number is expected, not a duplicate.
     */
    public function test_a_different_account_can_reuse_the_same_number(): void
    {
        [$stock, $payment] = $this->supplierPayment('Check', 1000);
        app(CheckRegisterClass::class)->registerIssued($payment, ['check_number' => '0055555']);

        $otherAccount = BankAccount::firstOrCreate(
            ['gl_code' => '1030'],
            ['bank_name' => 'BPI', 'account_name' => 'BRT 2']
        );
        [$stock2, $payment2] = $this->supplierPayment('Check', 2000);
        $payment2->update(['bank_account_id' => $otherAccount->id]);

        $check = app(CheckRegisterClass::class)->registerIssued($payment2, ['check_number' => '0055555']);

        $this->assertSame('0055555', $check->check_number);
    }
}
