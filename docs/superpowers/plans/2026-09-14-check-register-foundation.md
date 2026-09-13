# Check Register Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record every check in one register, in both directions, and make money post only when a person confirms the check actually cleared.

**Architecture:** A single `checks` table is authoritative for the lifecycle of every check. Receipts and supplier payment lines create register rows; nothing posts to the ledger while a check is pending. Confirming a received check posts the collection entry and reduces the invoice balance together; clearing an issued check posts DR Accounts Payable / CR Bank. Two live defects are corrected as part of this work.

**Tech Stack:** Laravel 11, MySQL (SQLite in tests), Pest/PHPUnit via `php artisan test`.

**Spec:** `docs/superpowers/specs/2026-09-14-check-register-design.md`

## Global Constraints

- Never run `migrate` against production from a script; migrations are applied deliberately after a verified dump. See `feedback_never_migrate_production`.
- This plan is backend only. UI surfaces and the forecast view are a separate plan.
- Money never posts for a `pending` check, in either direction.
- Do not drop or rewrite the existing `receipts.check_*` columns.
- Pint is not run: this repo is not Pint-clean, and running it buries changes in unrelated reformatting.
- Run the full suite (`php artisan test`) before each commit; it must stay green.

---

### Task 1: The `checks` table and model

**Files:**
- Create: `database/migrations/2026_09_14_000001_create_checks_table.php`
- Create: `app/Models/Check.php`
- Test: `tests/Feature/Checks/CheckModelTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Models\Check` with constants `DIRECTION_RECEIVED='received'`, `DIRECTION_ISSUED='issued'`, `STATUS_PENDING='pending'`, `STATUS_CLEARED='cleared'`, `STATUS_BOUNCED='bounced'`; scopes `pending()`, `received()`, `issued()`; `isPending(): bool`; morph relation `source()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Checks;

use App\Models\Check;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_check_starts_pending(): void
    {
        $check = Check::create([
            'direction' => Check::DIRECTION_RECEIVED,
            'check_number' => '0012345',
            'check_date' => now()->addDays(9)->toDateString(),
            'amount' => 100000,
            'source_type' => 'App\Models\Receipt',
            'source_id' => 1,
        ]);

        $this->assertSame(Check::STATUS_PENDING, $check->status);
        $this->assertTrue($check->isPending());
    }

    public function test_scopes_separate_direction_and_status(): void
    {
        $base = [
            'check_number' => '1', 'check_date' => now()->toDateString(), 'amount' => 1,
            'source_type' => 'App\Models\Receipt', 'source_id' => 1,
        ];
        Check::create($base + ['direction' => Check::DIRECTION_RECEIVED]);
        Check::create($base + ['direction' => Check::DIRECTION_ISSUED]);
        Check::create($base + ['direction' => Check::DIRECTION_ISSUED, 'status' => Check::STATUS_CLEARED]);

        $this->assertSame(1, Check::received()->count());
        $this->assertSame(2, Check::issued()->count());
        $this->assertSame(2, Check::pending()->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CheckModelTest`
Expected: FAIL — `Class "App\Models\Check" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One register for every check in either direction. A pending check moves no
 * money: it is a record of an instrument, not of a transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->enum('direction', ['received', 'issued']);
            $table->string('check_number', 50);
            $table->date('check_date');
            $table->decimal('amount', 15, 2);
            $table->string('bank_name')->nullable();
            $table->unsignedInteger('bank_account_id')->nullable();
            $table->enum('status', ['pending', 'cleared', 'bounced'])->default('pending');
            $table->string('source_type');
            $table->unsignedInteger('source_id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->unsignedInteger('received_by_id')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->unsignedInteger('cleared_by_id')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->unsignedInteger('bounced_by_id')->nullable();
            $table->string('bounce_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'check_date']);
            $table->index(['direction', 'status']);
            $table->index(['source_type', 'source_id']);

            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
            $table->foreign('received_by_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Check extends Model
{
    public const DIRECTION_RECEIVED = 'received';
    public const DIRECTION_ISSUED = 'issued';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_BOUNCED = 'bounced';

    protected $fillable = [
        'direction', 'check_number', 'check_date', 'amount', 'bank_name',
        'bank_account_id', 'status', 'source_type', 'source_id', 'customer_id',
        'supplier_id', 'received_by_id', 'cleared_at', 'cleared_by_id',
        'bounced_at', 'bounced_by_id', 'bounce_reason', 'notes',
    ];

    protected $casts = [
        'check_date' => 'date',
        'cleared_at' => 'datetime',
        'bounced_at' => 'datetime',
    ];

    public function source()
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeReceived($query)
    {
        return $query->where('direction', self::DIRECTION_RECEIVED);
    }

    public function scopeIssued($query)
    {
        return $query->where('direction', self::DIRECTION_ISSUED);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CheckModelTest`
Expected: PASS, 2 tests.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_14_000001_create_checks_table.php app/Models/Check.php tests/Feature/Checks/CheckModelTest.php
git commit -m "Add the check register table"
```

---

### Task 2: Stop an unconfirmed check crediting receivables

This is the spec's Prerequisite. `ArInvoiceClass` calls `recordReceiptEntry()` for every split including checks, so a check against a credit invoice posts DR Undeposited Collections / CR Accounts Receivable while still unconfirmed — but `applyPaymentToInvoice()` skips the check amount, so `balance_due` does not move. Ledger and invoice disagree until confirmation.

**Files:**
- Modify: `app/Services/Accounting/JournalEntryService.php` (`recordReceiptEntry`, around line 104)
- Test: `tests/Feature/Checks/UnconfirmedCheckPostingTest.php`

**Interfaces:**
- Consumes: `App\Models\Check` from Task 1.
- Produces: `recordReceiptEntry(Receipt $receipt): ?JournalEntry` returns `null` for a check receipt.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Checks;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnconfirmedCheckPostingTest extends TestCase
{
    use RefreshDatabase;

    private function receipt(string $mode): Receipt
    {
        foreach (['unpaid' => 'Unpaid', 'pending' => 'Pending', 'for-payment' => 'For Payment'] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => $name, 'text_color' => '#fff', 'bg_color' => '#333']);
        }
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Juan', 'address' => 'Z', 'contact_number' => '09170000000',
            'is_active' => 1, 'added_by_id' => $user->id,
        ]);
        $order = SalesOrder::create([
            'so_number' => 'SO-' . uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => 5000, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $user->id, 'requires_batch_approval' => false,
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

    public function test_a_check_receipt_posts_nothing_until_confirmed(): void
    {
        $receipt = $this->receipt('Check');

        $entry = app(JournalEntryService::class)->recordReceiptEntry($receipt);

        $this->assertNull($entry);
        $this->assertSame(0, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
    }

    public function test_a_cash_receipt_still_posts_immediately(): void
    {
        $receipt = $this->receipt('Cash');

        $entry = app(JournalEntryService::class)->recordReceiptEntry($receipt);

        $this->assertNotNull($entry);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UnconfirmedCheckPostingTest`
Expected: FAIL on `test_a_check_receipt_posts_nothing_until_confirmed` — an entry is returned. The cash test passes already.

- [ ] **Step 3: Add the guard**

In `recordReceiptEntry()`, directly after the `$amount <= 0` guard:

```php
        // A check is not money until someone confirms it cleared. Posting here
        // would credit Accounts Receivable while applyPaymentToInvoice()
        // deliberately leaves balance_due alone, so the ledger and the invoice
        // would disagree for as long as the check stayed unconfirmed.
        // ArInvoiceClass::confirmCheck() posts this entry on confirmation.
        if (strcasecmp((string) $receipt->payment_mode, 'Check') === 0) {
            return null;
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=UnconfirmedCheckPostingTest`
Expected: PASS, 2 tests.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: all green. If `ArInvoiceCheckConfirmationTest` or `ArInvoiceSplitPaymentTest` fail, read them — they encode the old behaviour and their expectations may need updating to match the spec. Do not weaken an assertion without understanding what it was protecting.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Accounting/JournalEntryService.php tests/Feature/Checks/UnconfirmedCheckPostingTest.php
git commit -m "Stop an unconfirmed check crediting receivables"
```

---

### Task 3: Confirmation posts the entry and the balance together

**Files:**
- Modify: `app/Services/Modules/ArInvoiceClass.php` (`confirmCheck`, around line 324)
- Test: `tests/Feature/Checks/CheckConfirmationPostsTest.php`

**Interfaces:**
- Consumes: the guard from Task 2.
- Produces: `confirmCheck($receiptId, $bankName, $checkDate = null)` posts the collection entry and reduces `balance_due` in one transaction.

- [ ] **Step 1: Write the failing test**

Reuse the `receipt()` helper from Task 2 (copy it into this test class — the engineer may read tasks out of order, and duplicated fixtures are cheaper than a shared base class here).

```php
    public function test_confirming_a_check_posts_the_entry_and_reduces_the_balance(): void
    {
        $receipt = $this->receipt('Check');
        $invoice = $receipt->arInvoice;
        $this->actingAs(User::factory()->create());

        app(\App\Services\Modules\ArInvoiceClass::class)->confirmCheck($receipt->id, 'BDO', now()->toDateString());

        $this->assertSame(1, JournalEntry::where('source_type', Receipt::class)->where('source_id', $receipt->id)->count());
        $this->assertSame(0.0, (float) $invoice->fresh()->balance_due);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CheckConfirmationPostsTest`
Expected: FAIL — the balance drops (that part already works) but no journal entry exists, because Task 2 stopped it posting at receipt time and nothing posts it on confirmation.

- [ ] **Step 3: Post on confirmation**

In `confirmCheck()`, after `$this->applyPaymentToInvoice($ar_invoice, (float) $receipt->amount_paid);` and before the `$receipt->update([...])` call:

```php
        // The receipt posted nothing when it was recorded (see
        // JournalEntryService::recordReceiptEntry). Confirmation is the single
        // moment both the ledger and the invoice move, so they cannot drift.
        $this->journalEntryService->recordCheckCollectionEntry($receipt);
```

Then add to `JournalEntryService`, immediately after `recordReceiptEntry()`:

```php
    /**
     * The collection entry for a check, posted on confirmation rather than on
     * receipt. Same lines as recordReceiptEntry(); separate entry point so the
     * check guard there cannot swallow it.
     */
    public function recordCheckCollectionEntry(Receipt $receipt): ?JournalEntry
    {
        $amount = round((float) $receipt->amount_paid, 2);
        if ($amount <= 0) {
            return null;
        }

        $receipt->loadMissing(['arInvoice.sales_order']);

        $undepositedAccount = $this->ensureAccount('1050', 'undeposited_collections', 'Undeposited Collections', 'asset', 'current_asset');
        $receivableAccount = $this->ensureAccount('1100', 'accounts_receivable', 'Accounts Receivable', 'asset', 'current_asset');
        $memo = 'Check ' . $receipt->receipt_number . ' confirmed cleared.';

        return $this->createEntry(
            $receipt,
            $receipt->check_date ?: $receipt->receipt_date,
            'receipt_collection',
            $memo,
            [
                [
                    'account_id' => $undepositedAccount->id,
                    'line_type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Record confirmed check collection.',
                ],
                [
                    'account_id' => $receivableAccount->id,
                    'line_type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Reduce accounts receivable balance.',
                ],
            ]
        );
    }
```

Confirm `ArInvoiceClass` has `$this->journalEntryService` available; if not, inject `JournalEntryService` through its constructor following the pattern already used in `ReceiptClass`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CheckConfirmationPostsTest`
Expected: PASS.

- [ ] **Step 5: Run the full suite and commit**

```bash
php artisan test
git add app/Services/Modules/ArInvoiceClass.php app/Services/Accounting/JournalEntryService.php tests/Feature/Checks/CheckConfirmationPostsTest.php
git commit -m "Post the check collection entry on confirmation"
```

---

### Task 4: Register rows for received checks

**Files:**
- Create: `app/Services/Modules/CheckRegisterClass.php`
- Modify: `app/Services/Modules/ArInvoiceClass.php` (receipt creation loop, around line 243)
- Test: `tests/Feature/Checks/ReceivedCheckRegistrationTest.php`

**Interfaces:**
- Consumes: `App\Models\Check`.
- Produces: `CheckRegisterClass::registerReceived(Receipt $receipt, array $attributes = []): Check` and `CheckRegisterClass::registerIssued(ReceivedStockPayment $payment, array $attributes = []): Check`.

- [ ] **Step 1: Write the failing test**

```php
    public function test_recording_a_check_receipt_creates_a_pending_register_row(): void
    {
        $receipt = $this->receipt('Check');

        $check = app(\App\Services\Modules\CheckRegisterClass::class)
            ->registerReceived($receipt, ['check_number' => '0012345', 'check_date' => now()->addDays(9)->toDateString()]);

        $this->assertSame(\App\Models\Check::STATUS_PENDING, $check->status);
        $this->assertSame(\App\Models\Check::DIRECTION_RECEIVED, $check->direction);
        $this->assertSame((float) $receipt->amount_paid, (float) $check->amount);
        $this->assertSame($receipt->customer_id, $check->customer_id);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReceivedCheckRegistrationTest`
Expected: FAIL — `CheckRegisterClass` does not exist.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\Modules;

use App\Models\Check;
use App\Models\Receipt;
use App\Models\ReceivedStockPayment;

/**
 * Creates register rows. Deliberately does no posting: a pending check moves no
 * money, and clearing is handled by the services that own each ledger.
 */
class CheckRegisterClass
{
    public function registerReceived(Receipt $receipt, array $attributes = []): Check
    {
        $receipt->loadMissing('arInvoice.sales_order');

        return Check::create(array_merge([
            'direction' => Check::DIRECTION_RECEIVED,
            'check_number' => $receipt->reference_number,
            'check_date' => $receipt->check_date,
            'amount' => $receipt->amount_paid,
            'bank_name' => $receipt->bank_name,
            'source_type' => Receipt::class,
            'source_id' => $receipt->id,
            'customer_id' => $receipt->customer_id,
            'received_by_id' => $receipt->arInvoice?->sales_order?->sales_rep_id,
            'status' => Check::STATUS_PENDING,
        ], $attributes));
    }

    public function registerIssued(ReceivedStockPayment $payment, array $attributes = []): Check
    {
        $payment->loadMissing('receivedStock');

        return Check::create(array_merge([
            'direction' => Check::DIRECTION_ISSUED,
            'check_number' => $payment->reference_number,
            'check_date' => $payment->payment_date,
            'amount' => $payment->amount_paid,
            'bank_name' => $payment->bank_name,
            'bank_account_id' => $payment->bank_account_id,
            'source_type' => ReceivedStockPayment::class,
            'source_id' => $payment->id,
            'supplier_id' => $payment->receivedStock?->supplier_id,
            'status' => Check::STATUS_PENDING,
        ], $attributes));
    }
}
```

- [ ] **Step 4: Call it when a check receipt is created**

In `ArInvoiceClass`, inside the `foreach ($splits as $split)` loop, after `$this->journalEntryService->recordReceiptEntry($receipt);`:

```php
            if ($isCheck) {
                app(CheckRegisterClass::class)->registerReceived($receipt, [
                    'check_number' => $split['reference_number'] ?? null,
                    'check_date' => $split['check_date'] ?? $request->payment_date,
                ]);
            }
```

- [ ] **Step 5: Run test and full suite, then commit**

```bash
php artisan test --filter=ReceivedCheckRegistrationTest
php artisan test
git add app/Services/Modules/CheckRegisterClass.php app/Services/Modules/ArInvoiceClass.php tests/Feature/Checks/ReceivedCheckRegistrationTest.php
git commit -m "Register received checks"
```

---

### Task 5: Clear and bounce a received check

**Files:**
- Modify: `app/Services/Modules/CheckRegisterClass.php`
- Create: `app/Notifications/BouncedCheckNotification.php`
- Test: `tests/Feature/Checks/CheckClearingTest.php`

**Interfaces:**
- Consumes: `Check` and `CheckRegisterClass` from Task 4.
- Produces: `markBounced(Check $check, string $reason): Check`, plus the private helpers `assertPending(Check $check): void` and `notifyReceivingRep(Check $check): void`. `markCleared()` arrives in Task 7 and reuses `assertPending()`.

- [ ] **Step 1: Write the failing test**

```php
    public function test_bouncing_leaves_the_invoice_owed_and_posts_nothing(): void
    {
        $receipt = $this->receipt('Check');
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
        $check = app(CheckRegisterClass::class)->registerReceived($receipt);
        $check->update(['status' => Check::STATUS_CLEARED]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CheckRegisterClass::class)->markBounced($check, 'Too late');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CheckClearingTest`
Expected: FAIL — `markBounced` does not exist.

- [ ] **Step 3: Implement the transitions**

Add to `CheckRegisterClass`:

```php
    public function markBounced(Check $check, string $reason): Check
    {
        $this->assertPending($check);

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'bounce_reason' => 'Say why the check bounced — the rep needs it to chase the customer.',
            ]);
        }

        $check->update([
            'status' => Check::STATUS_BOUNCED,
            'bounced_at' => now(),
            'bounced_by_id' => Auth::id(),
            'bounce_reason' => trim($reason),
        ]);

        // Nothing posts and no balance changes: a bounced check never was money.
        $this->notifyReceivingRep($check);

        return $check;
    }

    private function assertPending(Check $check): void
    {
        if (!$check->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending check can be cleared or bounced. This one is ' . $check->status . '.',
            ]);
        }
    }

    private function notifyReceivingRep(Check $check): void
    {
        $user = $check->received_by_id
            ? \App\Models\User::where('employee_id', $check->received_by_id)->first()
            : null;

        $user?->notify(new \App\Notifications\BouncedCheckNotification($check));
    }
```

Add `use Illuminate\Support\Facades\Auth;` and `use Illuminate\Validation\ValidationException;` at the top.

`app/Notifications/BouncedCheckNotification.php`, mirroring the channels and shape of `OverdueInvoiceNotification`:

```php
<?php

namespace App\Notifications;

use App\Models\Check;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class BouncedCheckNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(public Check $check) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'bounced_check';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'bounced_check',
            'check_id' => $this->check->id,
            'check_number' => $this->check->check_number,
            'amount' => (float) $this->check->amount,
            'check_date' => $this->check->check_date?->toDateString(),
            'bounce_reason' => $this->check->bounce_reason,
            'customer_name' => $this->check->customer_id
                ? \App\Models\Customer::find($this->check->customer_id)?->name
                : null,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

Before writing `notifyReceivingRep`, confirm how a `User` links to an `Employee`: run `grep -n "employee" app/Models/User.php`. If the column is not `employee_id`, use whatever that relation actually is.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CheckClearingTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
php artisan test
git add app/Services/Modules/CheckRegisterClass.php app/Notifications/BouncedCheckNotification.php tests/Feature/Checks/CheckClearingTest.php
git commit -m "Clear and bounce a received check"
```

---

### Task 6: A supplier check stops draining the bank

**Files:**
- Modify: `app/Services/Accounting/JournalEntryService.php` (`recordReceivedStockPaymentEntry`, around line 552)
- Test: `tests/Feature/Checks/IssuedCheckPostingTest.php`

**Interfaces:**
- Consumes: `CheckRegisterClass::registerIssued()` from Task 4.
- Produces: `recordReceivedStockPaymentEntry()` returns `null` for a check payment line.

- [ ] **Step 1: Write the failing test**

Both this task and Task 7 need these two helpers. Put them in the test class (copy them into Task 7's class too — the engineer may read tasks out of order). The fixture follows `tests/Feature/Inventory/SupplierPaymentSeparationTest.php::payable()`:

```php
    /** @return array{0: \App\Models\ReceivedStock, 1: \App\Models\ReceivedStockPayment} */
    private function supplierPayment(string $mode, float $amount): array
    {
        $user = User::factory()->create();

        $supplier = \App\Models\ListSupplier::create([
            'name' => 'Test Supplier ' . uniqid(), 'address' => 'Zamboanga City',
            'contact_person' => 'Roberto Cruz', 'contact_number' => '09170000000',
            'email' => uniqid() . '@example.com', 'tin' => '000-000-000',
            'is_active' => 1, 'is_blacklisted' => 0,
        ]);

        $po = \App\Models\PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-' . uniqid(),
            'po_date' => now()->toDateString(),
        ]);

        $stock = \App\Models\ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplier->id,
            'received_no' => 'RS-' . uniqid(), 'received_date' => now(),
            'payment_mode' => 'Credit', 'amount_paid' => 0, 'received_by_id' => $user->id,
        ]);

        $this->bankAccount = \App\Models\BankAccount::firstOrCreate(
            ['gl_code' => '1020'],
            ['bank_name' => 'BDO', 'account_name' => 'BRT']
        );

        $payment = \App\Models\ReceivedStockPayment::create([
            'received_stock_id' => $stock->id,
            'payment_date' => now()->toDateString(),
            'payment_mode' => $mode,
            'amount_paid' => $amount,
            'bank_account_id' => $this->bankAccount->id,
            'bank_name' => 'BDO',
            'reference_number' => 'CHK-' . uniqid(),
            'created_by_id' => $user->id,
        ]);

        return [$stock, $payment];
    }

    /** Net balance of the bank GL account the fixture draws on. */
    private function bankBalance(): float
    {
        $account = \App\Models\Account::where('code', '1020')->first();
        if (!$account) {
            return 0.0;   // the GL account is created lazily on first posting
        }
        $debit = (float) \App\Models\JournalEntryLine::where('account_id', $account->id)->where('line_type', 'debit')->sum('amount');
        $credit = (float) \App\Models\JournalEntryLine::where('account_id', $account->id)->where('line_type', 'credit')->sum('amount');

        return round($debit - $credit, 2);
    }
```

Declare `private ?\App\Models\BankAccount $bankAccount = null;` as a property on each test class that uses these.

```php
    public function test_a_supplier_check_posts_nothing_and_leaves_the_bank_alone(): void
    {
        [$stock, $payment] = $this->supplierPayment('Check', 1000000);

        $entry = app(JournalEntryService::class)->recordReceivedStockPaymentEntry($stock, $payment);

        $this->assertNull($entry);
        $this->assertSame(0.0, $this->bankBalance(), 'A post-dated check must not drive the bank negative.');
    }

    public function test_a_bank_transfer_still_posts_immediately(): void
    {
        [$stock, $payment] = $this->supplierPayment('Bank Transfer', 5000);

        $this->assertNotNull(app(JournalEntryService::class)->recordReceivedStockPaymentEntry($stock, $payment));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=IssuedCheckPostingTest`
Expected: FAIL — the check payment posts and the bank goes to −1,000,000.

- [ ] **Step 3: Defer the posting**

At the top of `recordReceivedStockPaymentEntry()`, after the `$amount <= 0` guard:

```php
        // The owner post-dates supplier checks to the day she expects funds, so
        // posting now would credit the bank weeks before the money leaves —
        // routinely driving the account negative. The register holds it until
        // someone marks it cleared, which is when this entry gets posted.
        if (strcasecmp((string) $payment->payment_mode, 'Check') === 0) {
            return null;
        }
```

- [ ] **Step 4: Run test to verify it passes, then the full suite**

Run: `php artisan test --filter=IssuedCheckPostingTest` then `php artisan test`
Expected: both green. Inventory and accounts-payable tests asserting immediate posting for checks will need their expectations updated to match the spec — read each before changing it.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Accounting/JournalEntryService.php tests/Feature/Checks/IssuedCheckPostingTest.php
git commit -m "Hold a supplier check until it is cashed"
```

---

### Task 7: Clearing an issued check posts the payment

**Files:**
- Modify: `app/Services/Modules/CheckRegisterClass.php`
- Test: `tests/Feature/Checks/IssuedCheckClearingTest.php`

**Interfaces:**
- Consumes: `markCleared()` from Task 5, `registerIssued()` from Task 4.
- Produces: `markCleared()` handles both directions — received posts the collection entry via `ArInvoiceClass::confirmCheck`, issued posts DR Accounts Payable / CR Bank.

- [ ] **Step 1: Write the failing test**

```php
    public function test_clearing_an_issued_check_posts_the_supplier_payment(): void
    {
        [$stock, $payment] = $this->supplierPayment('Check', 1000000);
        $check = app(CheckRegisterClass::class)->registerIssued($payment);

        app(CheckRegisterClass::class)->markCleared($check);

        $this->assertSame(Check::STATUS_CLEARED, $check->fresh()->status);
        $this->assertSame(-1000000.0, $this->bankBalance(), 'The money leaves when the check is cashed.');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=IssuedCheckClearingTest`
Expected: FAIL — no entry is posted.

- [ ] **Step 3: Implement `markCleared`**

```php
    public function markCleared(Check $check): Check
    {
        $this->assertPending($check);

        DB::transaction(function () use ($check) {
            if ($check->direction === Check::DIRECTION_ISSUED) {
                $payment = ReceivedStockPayment::with('receivedStock')->findOrFail($check->source_id);
                app(JournalEntryService::class)->postClearedSupplierCheck($payment->receivedStock, $payment, $check->check_date);
            } else {
                app(ArInvoiceClass::class)->confirmCheck($check->source_id, $check->bank_name, $check->check_date?->toDateString());
            }

            $check->update([
                'status' => Check::STATUS_CLEARED,
                'cleared_at' => now(),
                'cleared_by_id' => Auth::id(),
            ]);
        });

        return $check;
    }
```

Add `postClearedSupplierCheck(ReceivedStock $stock, ReceivedStockPayment $payment, $clearedDate)` to `JournalEntryService`: identical body to `recordReceivedStockPaymentEntry()` but without the check guard and dated `$clearedDate`. Extract the shared body into a private method rather than copying it — the two must never drift.

- [ ] **Step 4: Run test, full suite, commit**

```bash
php artisan test --filter=IssuedCheckClearingTest
php artisan test
git add app/Services/Modules/CheckRegisterClass.php app/Services/Accounting/JournalEntryService.php tests/Feature/Checks/IssuedCheckClearingTest.php
git commit -m "Post a supplier check when it is cashed"
```

---

### Task 8: Deposited checks stop posting on a date

**Files:**
- Modify: `app/Console/Commands/PostDueCheckDeposits.php`
- Modify: `app/Services/Accounting/CashManagementService.php`
- Modify: `app/Services/Modules/CheckRegisterClass.php` (the deposit-link step below)
- Test: `tests/Feature/Accounting/BankDepositCheckTest.php` (existing — update it)

**Interfaces:**
- Consumes: nothing new.
- Produces: the command posts nothing; `postBankDeposit()` is called only from a confirmation path.

- [ ] **Step 1: Update the existing tests to the new contract**

`tests/Feature/Accounting/BankDepositCheckTest.php` currently asserts the command posts a due check. Per the spec that is wrong — a check can bounce. Rewrite those two tests:

```php
    public function test_the_command_never_posts_a_due_check(): void
    {
        $deposit = $this->service()->createBankDeposit($this->payload([
            'deposit_type' => 'check',
            'check_date' => now()->addDays(3)->toDateString(),
            'check_number' => 'CHK-003',
        ]));

        $this->travelTo(now()->addDays(3));
        $this->artisan('deposits:post-due-checks')->assertSuccessful();

        $deposit->refresh();
        $this->assertSame(BankDeposit::STATUS_PENDING, $deposit->status, 'Only a person confirms money arrived.');
        $this->assertCount(0, $this->entriesFor($deposit));
    }
```

Delete `test_command_posts_the_check_once_its_date_arrives`, `test_command_is_idempotent` and `test_posted_check_entry_carries_the_check_date`, which all encode the superseded behaviour.

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=BankDepositCheckTest`
Expected: FAIL — the command still posts.

- [ ] **Step 3: Strip the posting from the command**

Replace the `handle()` body so it notifies rather than posts:

```php
    public function handle(): int
    {
        $due = BankDeposit::dueForPosting(now()->toDateString())->get();

        if ($due->isEmpty()) {
            $this->info('No check deposits are awaiting confirmation.');

            return self::SUCCESS;
        }

        // Deliberately posts nothing. A check whose date has arrived has not
        // necessarily cleared — it can still bounce — so a person confirms the
        // money arrived and that confirmation is what posts.
        $this->info("{$due->count()} check deposit(s) are due for confirmation.");

        return self::SUCCESS;
    }
```

Leave `postBankDeposit()` in place; it is now called from confirmation only.

Then close the seam the spec calls for — the deposit and the check must not hold two independent pending states. In `CheckRegisterClass::markCleared()`, after the received-check branch confirms the receipt, post any check-type deposit carrying it:

```php
            // Spec: "a check-type bank deposit stays pending until its underlying
            // check is confirmed cleared, and confirming the check is what posts
            // the deposit." Without this the two pending states drift apart.
            if ($check->direction === Check::DIRECTION_RECEIVED) {
                $deposit = \App\Models\BankDeposit::where('deposit_type', 'check')
                    ->where('status', \App\Models\BankDeposit::STATUS_PENDING)
                    ->where('check_number', $check->check_number)
                    ->first();

                $deposit && app(\App\Services\Accounting\CashManagementService::class)->postBankDeposit($deposit);
            }
```

Add a test asserting that confirming a received check posts its pending deposit, and that bouncing leaves the deposit pending.

- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=BankDepositCheckTest
php artisan test
git add app/Console/Commands/PostDueCheckDeposits.php tests/Feature/Accounting/BankDepositCheckTest.php
git commit -m "Require a person to confirm a deposited check cleared"
```

---

### Task 9: Reject a duplicate issued check number

The spec requires an issued check number to be unique per bank account. It is enforced in validation, not by a unique index: received checks have a null `bank_account_id`, and MySQL treats nulls as distinct, so an index would silently fail to cover exactly the rows it appeared to.

**Files:**
- Modify: `app/Services/Modules/CheckRegisterClass.php` (`registerIssued`)
- Test: `tests/Feature/Checks/DuplicateCheckNumberTest.php`

**Interfaces:**
- Consumes: `registerIssued()` from Task 4.
- Produces: `registerIssued()` throws `ValidationException` on a duplicate.

- [ ] **Step 1: Write the failing test**

```php
    public function test_the_same_account_cannot_issue_one_number_twice(): void
    {
        [$stock, $payment] = $this->supplierPayment('Check', 1000);
        app(CheckRegisterClass::class)->registerIssued($payment, ['check_number' => '0012345']);

        [$stock2, $payment2] = $this->supplierPayment('Check', 2000);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CheckRegisterClass::class)->registerIssued($payment2, ['check_number' => '0012345']);
    }
```

Note the fixture reuses `gl_code` 1020, so both payments draw on the same bank account — which is what makes them a duplicate.

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=DuplicateCheckNumberTest`
Expected: FAIL — no exception, two rows created.

- [ ] **Step 3: Add the guard at the top of `registerIssued()`**

```php
        $number = $attributes['check_number'] ?? $payment->reference_number;

        $duplicate = Check::issued()
            ->where('check_number', $number)
            ->where('bank_account_id', $payment->bank_account_id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'check_number' => 'Check ' . $number . ' has already been issued from this account.',
            ]);
        }
```

- [ ] **Step 4: Run test, full suite, commit**

```bash
php artisan test --filter=DuplicateCheckNumberTest
php artisan test
git add app/Services/Modules/CheckRegisterClass.php tests/Feature/Checks/DuplicateCheckNumberTest.php
git commit -m "Reject a duplicate issued check number"
```

---

### Task 10: Backfill the register

**Files:**
- Create: `database/migrations/2026_09_14_000002_backfill_checks_register.php`
- Test: `tests/Feature/Checks/CheckBackfillTest.php`

**Interfaces:**
- Consumes: the `checks` table from Task 1.
- Produces: nothing later tasks depend on.

- [ ] **Step 1: Write the failing test**

```php
    public function test_an_unconfirmed_check_receipt_becomes_a_pending_register_row(): void
    {
        $receipt = $this->receipt('Check');   // confirmed_at is null

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_09_14_000002_backfill_checks_register.php']);

        $check = Check::where('source_type', Receipt::class)->where('source_id', $receipt->id)->first();
        $this->assertNotNull($check);
        $this->assertSame(Check::STATUS_PENDING, $check->status);
    }
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=CheckBackfillTest`
Expected: FAIL — the migration does not exist.

- [ ] **Step 3: Write the backfill**

```php
public function up(): void
{
    $now = now();

    foreach (DB::table('receipts')->where('payment_mode', 'Check')->get() as $r) {
        if (DB::table('checks')->where('source_type', 'App\Models\Receipt')->where('source_id', $r->id)->exists()) {
            continue;
        }
        $repId = DB::table('ar_invoices as i')
            ->join('sales_orders as so', 'i.sales_order_id', '=', 'so.id')
            ->where('i.id', $r->ar_invoice_id)
            ->value('so.sales_rep_id');

        DB::table('checks')->insert([
            'direction' => 'received',
            'check_number' => $r->reference_number ?: '(unknown)',
            'check_date' => $r->check_date ?: $r->receipt_date,
            'amount' => $r->amount_paid,
            'bank_name' => $r->bank_name,
            'status' => $r->confirmed_at ? 'cleared' : 'pending',
            'cleared_at' => $r->confirmed_at,
            'cleared_by_id' => $r->confirmed_by_id,
            'source_type' => 'App\Models\Receipt',
            'source_id' => $r->id,
            'customer_id' => $r->customer_id,
            'received_by_id' => $repId,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    foreach (DB::table('received_stock_payments')->where('payment_mode', 'Check')->get() as $p) {
        if (DB::table('checks')->where('source_type', 'App\Models\ReceivedStockPayment')->where('source_id', $p->id)->exists()) {
            continue;
        }
        $supplierId = DB::table('received_stocks')->where('id', $p->received_stock_id)->value('supplier_id');

        DB::table('checks')->insert([
            'direction' => 'issued',
            // Historic rows predate the check columns: payment_date and
            // reference_number are the best available stand-ins. Both rows are
            // already cleared, so neither affects the forecast.
            'check_number' => $p->reference_number ?: '(unknown)',
            'check_date' => $p->payment_date,
            'amount' => $p->amount_paid,
            'bank_name' => $p->bank_name,
            'bank_account_id' => $p->bank_account_id,
            'status' => 'cleared',
            'cleared_at' => $p->created_at,
            'source_type' => 'App\Models\ReceivedStockPayment',
            'source_id' => $p->id,
            'supplier_id' => $supplierId,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}

public function down(): void
{
    DB::table('checks')->truncate();
}
```

The `exists()` guards make it re-runnable, which matters because a half-applied production migration must be repeatable.

- [ ] **Step 4: Run test, full suite, commit**

```bash
php artisan test --filter=CheckBackfillTest
php artisan test
git add database/migrations/2026_09_14_000002_backfill_checks_register.php tests/Feature/Checks/CheckBackfillTest.php
git commit -m "Backfill the check register from existing records"
```

---

## Done when

- `php artisan test` is green.
- No check, in either direction, posts to the ledger while pending.
- Confirming a received check posts the collection entry and reduces `balance_due` in one transaction.
- Clearing an issued check posts DR Accounts Payable / CR Bank dated the clearing.
- Bouncing posts nothing, leaves the invoice owed, and notifies the receiving rep.
- `deposits:post-due-checks` posts nothing.
- The register holds a row for every existing check receipt and supplier check payment.

Production migration and deploy are **not** part of this plan. Both migrations are applied deliberately, after a verified dump, per `feedback_never_migrate_production`.
