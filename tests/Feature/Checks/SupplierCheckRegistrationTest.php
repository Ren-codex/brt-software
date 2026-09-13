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
use App\Services\ReceivedStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * A supplier check posts nothing when it is written — correct, the money has not
 * left. But clearing is driven from the register, so a check that never reaches
 * the register can never be posted at all: the money would leave the bank in
 * reality and never appear in the books.
 */
class SupplierCheckRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payable(): ReceivedStock
    {
        $user = User::factory()->create();
        Auth::login($user);

        $supplier = ListSupplier::create([
            'name' => 'Supplier ' . uniqid(), 'address' => 'Zamboanga City',
            'contact_person' => 'Roberto Cruz', 'contact_number' => '09170000000',
            'email' => uniqid() . '@example.com', 'tin' => '000-000-000',
            'is_active' => 1, 'is_blacklisted' => 0,
        ]);
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id, 'po_number' => 'PO-' . uniqid(),
            'po_date' => now()->toDateString(), 'total_amount' => 1000000,
            'status_id' => 1, 'created_by_id' => $user->id,
        ]);

        return ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplier->id,
            'received_no' => 'RS-' . uniqid(), 'received_date' => now(),
            'payment_mode' => 'Credit', 'amount_paid' => 0, 'received_by_id' => $user->id,
        ]);
    }

    private function bank(): BankAccount
    {
        return BankAccount::firstOrCreate(['gl_code' => '1020'], ['bank_name' => 'BDO', 'account_name' => 'BRT']);
    }

    private function pay(ReceivedStock $stock, array $line): void
    {
        app(ReceivedStockService::class)->applyPayment($stock, ['lines' => [$line]]);
    }

    public function test_paying_a_supplier_by_check_puts_it_in_the_register(): void
    {
        $stock = $this->payable();
        $bank = $this->bank();
        $checkDate = now()->addDays(9)->toDateString();

        $this->pay($stock, [
            'payment_mode' => 'Check',
            'payment_amount' => 1000000,
            'bank_account_id' => $bank->id,
            'reference_number' => '000789',
            'check_date' => $checkDate,
        ]);

        $check = Check::issued()->first();

        $this->assertNotNull($check, 'A supplier check must reach the register, or it can never be posted.');
        $this->assertSame(Check::STATUS_PENDING, $check->status);
        $this->assertSame('000789', $check->check_number);
        $this->assertSame($checkDate, $check->check_date->toDateString());
        $this->assertSame($bank->id, $check->bank_account_id, 'The forecast needs to know which bank it drains.');
        $this->assertSame($stock->supplier_id, $check->supplier_id);
    }

    public function test_the_check_still_posts_nothing_when_written(): void
    {
        $stock = $this->payable();

        $this->pay($stock, [
            'payment_mode' => 'Check', 'payment_amount' => 1000000,
            'bank_account_id' => $this->bank()->id,
            'reference_number' => '000790', 'check_date' => now()->addDays(9)->toDateString(),
        ]);

        $payment = ReceivedStockPayment::first();
        $this->assertSame(0, JournalEntry::where('source_type', ReceivedStockPayment::class)->where('source_id', $payment->id)->count());
    }

    public function test_a_cash_payment_creates_no_register_row_and_posts_normally(): void
    {
        $stock = $this->payable();

        $this->pay($stock, ['payment_mode' => 'Cash on Hand', 'payment_amount' => 50000]);

        $this->assertSame(0, Check::count(), 'Only checks belong in the register.');
        $payment = ReceivedStockPayment::first();
        $this->assertSame(1, JournalEntry::where('source_type', ReceivedStockPayment::class)->where('source_id', $payment->id)->count());
    }

    public function test_a_check_without_its_date_is_refused(): void
    {
        $stock = $this->payable();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->pay($stock, [
            'payment_mode' => 'Check', 'payment_amount' => 1000000,
            'bank_account_id' => $this->bank()->id, 'reference_number' => '000791',
        ]);
    }
}
