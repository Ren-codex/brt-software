<?php

namespace Tests\Feature\Checks;

use App\Models\BankAccount;
use App\Models\Check;
use App\Models\ListStatus;
use App\Models\ListSupplier;
use App\Models\PurchaseOrder;
use App\Models\Receipt;
use App\Models\ReceivedStock;
use App\Models\ReceivedStockPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the Task 10 backfill migration
 * (2026_09_14_000002_backfill_checks_register.php), which populates the
 * checks register from receipts and received_stock_payments that predate
 * this feature.
 *
 * Every test runs the migration via runBackfill() rather than
 * `$this->artisan('migrate', ['--path' => ...])`. RefreshDatabase runs
 * `migrate:fresh` exactly once per test process — before any fixture data
 * exists — and the in-memory sqlite connection (and its `migrations`
 * repository table) is kept alive across every test in the run. That means
 * this migration is already recorded as "ran" (against empty tables)
 * before any test body executes, so a later `artisan migrate --path=...`
 * inside a test finds nothing pending and silently does nothing; this was
 * confirmed by running the brief's literal artisan-based test in isolation
 * and watching it fail the same way it does in the full suite. Calling the
 * migration object's up() directly sidesteps artisan's repository
 * bookkeeping entirely and exercises the exact same code the real
 * migration runs — exists() guards included, which the rerun test below
 * relies on by calling up() twice on purpose.
 */
class CheckBackfillTest extends TestCase
{
    use RefreshDatabase;
    use MakesCheckFixtures;

    private function runBackfill(): void
    {
        (require base_path('database/migrations/2026_09_14_000002_backfill_checks_register.php'))->up();
    }

    /** @return array{0: ReceivedStock, 1: ReceivedStockPayment} */
    private function supplierPayment(?string $referenceNumber, ?int $bankAccountId = null): array
    {
        $user = User::factory()->create();
        $status = ListStatus::firstOrCreate(
            ['slug' => 'pending'],
            ['name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333']
        );
        $supplier = ListSupplier::create([
            'name' => 'Supplier ' . uniqid(), 'address' => 'Addr', 'contact_person' => 'P',
            'contact_number' => '09000000000', 'email' => uniqid() . '@test.com', 'tin' => '000',
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

        $payment = ReceivedStockPayment::create([
            'received_stock_id' => $received->id, 'payment_date' => now()->toDateString(),
            'payment_mode' => 'Check', 'amount_paid' => 5000,
            'reference_number' => $referenceNumber, 'bank_account_id' => $bankAccountId,
        ]);

        return [$received, $payment];
    }

    public function test_an_unconfirmed_check_receipt_becomes_a_pending_register_row(): void
    {
        $receipt = $this->receipt('Check');   // confirmed_at is null

        $this->runBackfill();

        $check = Check::where('source_type', Receipt::class)->where('source_id', $receipt->id)->first();
        $this->assertNotNull($check);
        $this->assertSame(Check::STATUS_PENDING, $check->status);
    }

    public function test_a_confirmed_check_receipt_becomes_a_cleared_register_row(): void
    {
        $receipt = $this->receipt('Check', '000456');
        $confirmer = User::factory()->create();
        $receipt->update(['confirmed_at' => now(), 'confirmed_by_id' => $confirmer->id, 'bank_name' => 'BDO']);

        $this->runBackfill();

        $check = Check::where('source_type', Receipt::class)->where('source_id', $receipt->id)->first();
        $this->assertNotNull($check);
        $this->assertSame(Check::STATUS_CLEARED, $check->status);
        $this->assertSame('000456', $check->check_number);
        $this->assertNotNull($check->cleared_at);
        $this->assertSame($confirmer->id, $check->cleared_by_id);
    }

    /**
     * Historic issued checks are backfilled as already cleared: they posted
     * under the old behaviour before this feature existed, and rewriting
     * posted history is out of scope.
     */
    public function test_an_issued_supplier_check_payment_becomes_a_cleared_register_row(): void
    {
        [$stock, $payment] = $this->supplierPayment('CHK-9001');

        $this->runBackfill();

        $check = Check::where('source_type', ReceivedStockPayment::class)->where('source_id', $payment->id)->first();
        $this->assertNotNull($check);
        $this->assertSame(Check::STATUS_CLEARED, $check->status);
        $this->assertSame('CHK-9001', $check->check_number);
    }

    /**
     * checks.check_number is NOT NULL with no default. A historic record
     * with no reference_number still gets a register row — visibly
     * incomplete via the literal '(unknown)' marker — rather than being
     * silently skipped.
     */
    public function test_a_missing_reference_number_backfills_as_unknown(): void
    {
        $receipt = $this->receipt('Check', null);

        $this->runBackfill();

        $check = Check::where('source_type', Receipt::class)->where('source_id', $receipt->id)->first();
        $this->assertNotNull($check);
        $this->assertSame('(unknown)', $check->check_number);
    }

    /**
     * This migration will eventually run against a live production
     * database, so it must be safe to re-run without duplicating rows —
     * exactly what the exists() guards in up() are for.
     */
    public function test_rerunning_the_backfill_does_not_duplicate_rows(): void
    {
        $receipt = $this->receipt('Check');
        [$stock, $payment] = $this->supplierPayment('CHK-9002');

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame(1, Check::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
        $this->assertSame(1, Check::where('source_type', ReceivedStockPayment::class)->where('source_id', $payment->id)->count());
    }

    /**
     * Task 9 adds a service-layer uniqueness guard to registerIssued(), but
     * this migration inserts via the DB facade, bypassing it deliberately:
     * historic data may contain real duplicate check numbers per bank
     * account, and the backfill must not choke on them.
     */
    public function test_the_backfill_tolerates_duplicate_historic_check_numbers(): void
    {
        $bankAccount = BankAccount::firstOrCreate(
            ['gl_code' => '1020'],
            ['bank_name' => 'BDO', 'account_name' => 'BRT']
        );
        [$stockA, $paymentA] = $this->supplierPayment('DUPLICATE-1', $bankAccount->id);
        [$stockB, $paymentB] = $this->supplierPayment('DUPLICATE-1', $bankAccount->id);

        $this->runBackfill();

        $this->assertSame(
            2,
            Check::where('check_number', 'DUPLICATE-1')->where('bank_account_id', $bankAccount->id)->count(),
            'The backfill must insert both historic rows even though they share a check number and bank account.'
        );
    }
}
