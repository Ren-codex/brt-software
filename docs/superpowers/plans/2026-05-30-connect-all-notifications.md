# Connect All to Notification — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire `LowBalanceFundNotification` to every fund-balance-decreasing path, and add `LowStockNotification` + `OverdueInvoiceNotification` dispatched from `InventoryService` and `MarkOverdueInvoices`.

**Architecture:** All dispatch logic lives in three new methods on `NotificationService` (`checkAndNotifyLowBalance`, `checkAndNotifyLowStock`, `notifyOverdueInvoices`). Each affected service injects `NotificationService` and calls the relevant method. The frontend `Navigation.vue` is extended to render the two new notification types.

**Tech Stack:** Laravel 11, Vue 3, Inertia.js, Laravel Echo (WebSockets via Reverb), Pest tests.

---

## File Map

**Create:**
- `app/Notifications/LowStockNotification.php`
- `app/Notifications/OverdueInvoiceNotification.php`
- `tests/Feature/Notifications/LowStockNotificationTest.php`
- `tests/Feature/Notifications/OverdueInvoiceNotificationTest.php`
- `tests/Feature/Notifications/LowBalanceCashManagementTest.php`
- `tests/Feature/Notifications/LowBalanceFundClassTest.php`

**Modify:**
- `app/Services/NotificationService.php` — add 3 dispatcher methods
- `app/Services/Modules/ExpenseClass.php` — replace inline dispatch; add update() check
- `app/Services/Accounting/CashManagementService.php` — wire disbursement + reversal
- `app/Services/Libraries/FundClass.php` — wire adjustBalance()
- `app/Services/Modules/InventoryService.php` — wire deductStock() + recordLossOrDamage()
- `app/Services/System/PurchaseOrder/StockReturnClass.php` — wire stock return approval
- `app/Console/Commands/MarkOverdueInvoices.php` — notify after marking
- `resources/js/Shared/Layouts/Components/Navigation.vue` — render new types + extend Echo handler
- `tests/Feature/Expenses/LowBalanceNotificationTest.php` — add update() test

---

## Task 1: Create LowStockNotification

**Files:**
- Create: `app/Notifications/LowStockNotification.php`
- Create: `tests/Feature/Notifications/LowStockNotificationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Notifications/LowStockNotificationTest.php

namespace Tests\Feature\Notifications;

use App\Models\ListBrand;
use App\Models\ListUnit;
use App\Models\Product;
use App\Notifications\LowStockNotification;
use Tests\TestCase;

class LowStockNotificationTest extends TestCase
{
    public function test_notification_has_correct_type_and_channels(): void
    {
        $brand   = ListBrand::create(['name' => 'BrandA']);
        $unit    = ListUnit::create(['name' => 'pcs']);
        $product = Product::create([
            'brand_id'      => $brand->id,
            'pack_size'     => '500mg',
            'unit_id'       => $unit->id,
            'is_active'     => true,
            'minimum_stock' => 10,
        ]);

        $product->load(['brand', 'unit']);
        $notification = new LowStockNotification($product, 3);
        $data = $notification->toDatabase(null);

        $this->assertSame('low_stock', $data['type']);
        $this->assertSame($product->id, $data['product_id']);
        $this->assertSame(3, $data['current_stock']);
        $this->assertSame(10, $data['minimum_stock']);
        $this->assertContains('database', $notification->via(null));
        $this->assertContains('broadcast', $notification->via(null));
        $this->assertSame('low_stock', $notification->broadcastType());
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --filter="LowStockNotificationTest" 2>&1 | grep -E "PASS|FAIL|ERROR|Tests:"
```

Expected: `FAIL` — class not found.

- [ ] **Step 3: Create the notification class**

```php
<?php
// app/Notifications/LowStockNotification.php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public Product $product,
        public int $currentStock
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'low_stock';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'          => 'low_stock',
            'product_id'    => $this->product->id,
            'product_name'  => $this->product->brand->name . ' ' . $this->product->pack_size . ' ' . $this->product->unit->name,
            'current_stock' => $this->currentStock,
            'minimum_stock' => $this->product->minimum_stock,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
php artisan test --filter="LowStockNotificationTest" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: `PASS  Tests\Feature\Notifications\LowStockNotificationTest — 1 passed`

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/LowStockNotification.php tests/Feature/Notifications/LowStockNotificationTest.php
git commit -m "feat: add LowStockNotification class"
```

---

## Task 2: Create OverdueInvoiceNotification

**Files:**
- Create: `app/Notifications/OverdueInvoiceNotification.php`
- Create: `tests/Feature/Notifications/OverdueInvoiceNotificationTest.php` (test class structure only — wiring test added in Task 9)

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Notifications/OverdueInvoiceNotificationTest.php

namespace Tests\Feature\Notifications;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use App\Notifications\OverdueInvoiceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverdueInvoiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_has_correct_type_and_channels(): void
    {
        $status   = ListStatus::create(['name' => 'Overdue', 'slug' => 'overdue', 'type' => 'sales_order']);
        $customer = Customer::create(['name' => 'Juan dela Cruz', 'is_active' => true]);
        $so       = SalesOrder::create([
            'so_number'   => 'SO-TEST-0001',
            'order_date'  => now()->toDateString(),
            'customer_id' => $customer->id,
            'status_id'   => $status->id,
            'total_amount'=> 5000,
            'added_by_id' => 1,
        ]);
        $invoice = ArInvoice::create([
            'sales_order_id' => $so->id,
            'status_id'      => $status->id,
            'invoice_number' => 'AR-202505-0001',
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->subDay()->toDateString(),
            'balance_due'    => 4500.00,
            'amount_paid'    => 500.00,
            'total_discount' => 0,
        ]);
        $invoice->load('sales_order.customer');

        $notification = new OverdueInvoiceNotification($invoice);
        $data = $notification->toDatabase(null);

        $this->assertSame('overdue_invoice', $data['type']);
        $this->assertSame($invoice->id, $data['invoice_id']);
        $this->assertSame('AR-202505-0001', $data['invoice_number']);
        $this->assertSame('Juan dela Cruz', $data['customer_name']);
        $this->assertSame(4500.0, $data['balance_due']);
        $this->assertContains('database', $notification->via(null));
        $this->assertContains('broadcast', $notification->via(null));
        $this->assertSame('overdue_invoice', $notification->broadcastType());
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test --filter="OverdueInvoiceNotificationTest::test_notification_has_correct_type_and_channels" 2>&1 | grep -E "PASS|FAIL|ERROR|Tests:"
```

Expected: `FAIL` — class not found.

- [ ] **Step 3: Create the notification class**

Check `ArInvoice::$fillable` — `due_date` is present in the migration but not in `$fillable`. Add it now:

In `app/Models/ArInvoice.php`, update `$fillable` and `$casts`:

```php
protected $fillable = [
    'sales_order_id',
    'status_id',
    'invoice_number',
    'invoice_date',
    'due_date',
    'amount_paid',
    'balance_due',
    'total_discount',
    'created_by',
];

protected $casts = [
    'invoice_date' => 'date',
    'due_date'     => 'date',
    'amount_paid'  => 'decimal:2',
    'balance_due'  => 'decimal:2',
    'total_discount' => 'decimal:2',
];
```

Then create the notification:

```php
<?php
// app/Notifications/OverdueInvoiceNotification.php

namespace App\Notifications;

use App\Models\ArInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class OverdueInvoiceNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(public ArInvoice $invoice) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'overdue_invoice';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'           => 'overdue_invoice',
            'invoice_id'     => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'customer_name'  => $this->invoice->sales_order->customer->name,
            'balance_due'    => (float) $this->invoice->balance_due,
            'due_date'       => $this->invoice->due_date->toDateString(),
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
php artisan test --filter="OverdueInvoiceNotificationTest::test_notification_has_correct_type_and_channels" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: `PASS — 1 passed`

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/OverdueInvoiceNotification.php app/Models/ArInvoice.php tests/Feature/Notifications/OverdueInvoiceNotificationTest.php
git commit -m "feat: add OverdueInvoiceNotification class"
```

---

## Task 3: Add Dispatcher Methods to NotificationService

**Files:**
- Modify: `app/Services/NotificationService.php`

- [ ] **Step 1: Write failing tests for checkAndNotifyLowBalance**

Add to `tests/Feature/Expenses/LowBalanceNotificationTest.php` — add this test **to the existing class** (do not replace the file):

```php
public function test_check_and_notify_low_balance_fires_notification_on_threshold_crossing(): void
{
    Notification::fake();

    $fund    = $this->makeFund(['balance' => 600, 'low_balance_threshold' => 500]);
    $service = app(\App\Services\NotificationService::class);

    $service->checkAndNotifyLowBalance($fund, 600.0, 400.0);

    Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
    Notification::assertSentTo($this->topManagement, LowBalanceFundNotification::class);
}

public function test_check_and_notify_low_balance_does_not_fire_when_already_below(): void
{
    Notification::fake();

    $fund    = $this->makeFund(['balance' => 300, 'low_balance_threshold' => 500]);
    $service = app(\App\Services\NotificationService::class);

    $service->checkAndNotifyLowBalance($fund, 300.0, 200.0);

    Notification::assertNothingSent();
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test --filter="LowBalanceNotificationTest::test_check_and_notify" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: `FAIL` — method not found on NotificationService.

- [ ] **Step 3: Replace NotificationService with updated version**

```php
<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\InventoryStocks;
use App\Models\PettyCashFund;
use App\Models\Product;
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\OverdueInvoiceNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function markRead(Request $request, string $id): array
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return ['message' => 'Notification marked as read.', 'status' => true];
    }

    public function markAllRead(Request $request): array
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ['message' => 'All notifications marked as read.', 'status' => true];
    }

    public function checkAndNotifyLowBalance(PettyCashFund $fund, float $previousBalance, float $newBalance): void
    {
        if (
            $fund->low_balance_threshold !== null
            && $previousBalance >= (float) $fund->low_balance_threshold
            && $newBalance < (float) $fund->low_balance_threshold
        ) {
            $fundId = $fund->id;
            DB::afterCommit(function () use ($fundId) {
                $notifyFund = PettyCashFund::find($fundId);
                if ($notifyFund) {
                    User::whereHas('roles', fn($q) => $q->whereIn('name', ['Administrator', 'Top Management']))
                        ->each(fn($u) => $u->notify(new LowBalanceFundNotification($notifyFund)));
                }
            });
        }
    }

    public function checkAndNotifyLowStock(int $productId, int $previousTotal): void
    {
        DB::afterCommit(function () use ($productId, $previousTotal) {
            $product = Product::with(['brand', 'unit'])->find($productId);
            if (!$product || $product->minimum_stock === null) {
                return;
            }

            $newTotal = (int) InventoryStocks::whereHas(
                'receivedItem', fn($q) => $q->where('product_id', $productId)
            )->sum('quantity');

            if ($previousTotal >= $product->minimum_stock && $newTotal < $product->minimum_stock) {
                User::whereHas('roles', fn($q) => $q->whereIn('name', ['Administrator', 'Top Management']))
                    ->each(fn($u) => $u->notify(new LowStockNotification($product, $newTotal)));
            }
        });
    }

    public function notifyOverdueInvoices(Collection $invoices): void
    {
        if ($invoices->isEmpty()) {
            return;
        }

        $users = User::whereHas(
            'roles', fn($q) => $q->whereIn('name', ['Administrator', 'Top Management'])
        )->get();

        foreach ($invoices as $invoice) {
            foreach ($users as $user) {
                $user->notify(new OverdueInvoiceNotification($invoice));
            }
        }
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test --filter="LowBalanceNotificationTest::test_check_and_notify" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: `PASS — 2 passed`

- [ ] **Step 5: Commit**

```bash
git add app/Services/NotificationService.php tests/Feature/Expenses/LowBalanceNotificationTest.php
git commit -m "feat: add checkAndNotifyLowBalance, checkAndNotifyLowStock, notifyOverdueInvoices to NotificationService"
```

---

## Task 4: Wire ExpenseClass — Replace Inline Block + Update Check

**Files:**
- Modify: `app/Services/Modules/ExpenseClass.php`
- Modify: `tests/Feature/Expenses/LowBalanceNotificationTest.php`

- [ ] **Step 1: Write failing test for update() crossing threshold**

Add to the existing `LowBalanceNotificationTest` class:

```php
public function test_notification_fired_when_expense_update_increases_amount_crossing_threshold(): void
{
    Notification::fake();

    // Fund at 600, threshold 500. Existing expense was 50, so fund balance already reflects that.
    $fund    = $this->makeFund(['balance' => 550, 'low_balance_threshold' => 500]);
    $expense = Expense::create([
        'fund_id'      => $fund->id,
        'expense_type' => 'operational',
        'amount'       => 50.0,
        'expense_date' => now()->toDateString(),
        'status'       => 'recorded',
        'added_by_id'  => $this->admin->id,
    ]);

    // Increase amount by 200 — fund goes from 550 to 350 (crosses 500 threshold)
    $this->actingAs($this->admin);
    $request = Request::create('/expenses/' . $expense->id, 'PATCH', [
        'id'           => $expense->id,
        'expense_type' => 'operational',
        'amount'       => 250.0,
        'expense_date' => now()->toDateString(),
        'status'       => 'recorded',
    ]);
    app(\App\Services\Modules\ExpenseClass::class)->update($request);

    Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
}
```

- [ ] **Step 2: Run to confirm it fails**

```bash
php artisan test --filter="LowBalanceNotificationTest::test_notification_fired_when_expense_update" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: `FAIL` — no notification sent.

- [ ] **Step 3: Update ExpenseClass**

In `app/Services/Modules/ExpenseClass.php`:

**a) Update the constructor** to inject `NotificationService`:

```php
use App\Services\NotificationService;

// Replace:
public function __construct(protected JournalEntryService $journalEntryService)
// With:
public function __construct(
    protected JournalEntryService $journalEntryService,
    protected NotificationService $notificationService,
) {}
```

**b) In `save()`, replace the inline dispatch block** (lines 128–141) with a service call:

```php
// Remove this entire block:
if (
    $fund->low_balance_threshold !== null
    && $previousBalance >= (float) $fund->low_balance_threshold
    && $newBalance < (float) $fund->low_balance_threshold
) {
    $fundId = $fund->id;
    \DB::afterCommit(function () use ($fundId) {
        $notifyFund = PettyCashFund::find($fundId);
        if ($notifyFund) {
            User::whereHas('roles', fn($q) => $q->whereIn('name', ['Administrator', 'Top Management']))
                ->each(fn($u) => $u->notify(new LowBalanceFundNotification($notifyFund)));
        }
    });
}

// Replace with:
$this->notificationService->checkAndNotifyLowBalance($fund, $previousBalance, $newBalance);
```

Also remove the now-unused imports from `ExpenseClass` if they are not used elsewhere in the file:
- `use App\Models\User;`
- `use App\Notifications\LowBalanceFundNotification;`

Check first: `grep -n "User::\|LowBalanceFundNotification" app/Services/Modules/ExpenseClass.php` — if those are only used in the removed block, remove the imports.

**c) In `update()`, add the notification check** after the `if ($delta > 0)` decrement block (after line 186):

```php
if ($delta > 0) {
    $fund->decrement('balance', $delta);
    $newBalance = (float) $fund->balance - $delta; // balance before decrement minus delta
    // Note: $fund->balance is the value BEFORE decrement since decrement returns the model
    $previousBalance = (float) $fund->getOriginal('balance') ?? ((float) $fund->balance + $delta);
    $this->notificationService->checkAndNotifyLowBalance($fund, $fund->balance + $delta, $fund->balance);
} else {
    $fund->increment('balance', abs($delta));
}
```

Wait — after `$fund->decrement('balance', $delta)`, the model's `balance` attribute is updated in-memory. So `$fund->balance` is the new value. The previous value is `$fund->balance + $delta`. Replace the `if ($delta > 0)` block with:

```php
if ($delta > 0) {
    $previousBalance = (float) $fund->balance;
    $fund->decrement('balance', $delta);
    $newBalance = $previousBalance - $delta;
    $this->notificationService->checkAndNotifyLowBalance($fund, $previousBalance, $newBalance);
} else {
    $fund->increment('balance', abs($delta));
}
```

- [ ] **Step 4: Run the full LowBalanceNotificationTest**

```bash
php artisan test --filter="LowBalanceNotificationTest" 2>&1 | grep -E "PASS|FAIL|Tests:|✓|✗"
```

Expected: all tests pass (6 tests including the 2 new ones from Task 3 and 1 from this task).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Modules/ExpenseClass.php tests/Feature/Expenses/LowBalanceNotificationTest.php
git commit -m "feat: wire NotificationService into ExpenseClass for low balance checks"
```

---

## Task 5: Wire CashManagementService

**Files:**
- Modify: `app/Services/Accounting/CashManagementService.php`
- Create: `tests/Feature/Notifications/LowBalanceCashManagementTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Notifications/LowBalanceCashManagementTest.php

namespace Tests\Feature\Notifications;

use App\Models\ListRole;
use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
use App\Services\Accounting\CashManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowBalanceCashManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole   = ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => '', 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id, ['added_by_id' => $this->admin->id]);
        $this->actingAs($this->admin);
    }

    private function makeFund(array $attrs = []): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create(array_merge([
            'name'                  => 'Test Fund',
            'gl_code'               => 'PCF-CMS-' . (++$seq),
            'balance'               => 1000,
            'low_balance_threshold' => 500,
        ], $attrs));
    }

    public function test_disbursement_fires_notification_on_threshold_crossing(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 600, 'low_balance_threshold' => 500]);

        app(CashManagementService::class)->addPettyCashTransaction($fund, [
            'type'             => 'disbursement',
            'amount'           => 200,
            'transaction_date' => now()->toDateString(),
        ]);

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
    }

    public function test_disbursement_does_not_fire_when_no_threshold_crossing(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 1000, 'low_balance_threshold' => 500]);

        app(CashManagementService::class)->addPettyCashTransaction($fund, [
            'type'             => 'disbursement',
            'amount'           => 100,
            'transaction_date' => now()->toDateString(),
        ]);

        Notification::assertNothingSent();
    }

    public function test_delete_replenishment_fires_notification_when_balance_crosses_threshold(): void
    {
        Notification::fake();

        $fund = $this->makeFund(['balance' => 600, 'low_balance_threshold' => 500]);
        $txn  = PettyCashTransaction::create([
            'fund_id'          => $fund->id,
            'type'             => 'replenishment',
            'amount'           => 200,
            'transaction_date' => now()->toDateString(),
            'transaction_no'   => 'TXN-TEST-001',
        ]);

        app(CashManagementService::class)->deleteTransaction($txn->id);

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
    }
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test --filter="LowBalanceCashManagementTest" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: all `FAIL`.

- [ ] **Step 3: Update CashManagementService**

In `app/Services/Accounting/CashManagementService.php`:

**a) Add imports and inject NotificationService:**

```php
use App\Services\NotificationService;
```

Update constructor:

```php
public function __construct(
    private SeriesService $series,
    private JournalEntryService $journal,
    private NotificationService $notificationService,
) {}
```

**b) In `addPettyCashTransaction()`**, find the disbursement decrement and wrap it with a balance capture + notification call:

```php
// Find this block (around line 134):
} else {
    $fund->decrement('balance', $amount);
    $txn->load(['fund']);
    $this->journal->recordPettyCashDisbursement($txn);
}

// Replace with:
} else {
    $previousBalance = (float) $fund->balance;
    $fund->decrement('balance', $amount);
    $newBalance = $previousBalance - $amount;
    $txn->load(['fund']);
    $this->journal->recordPettyCashDisbursement($txn);
    $this->notificationService->checkAndNotifyLowBalance($fund, $previousBalance, $newBalance);
}
```

**c) In `deleteTransaction()`**, find the replenishment-reversal decrement and wrap it:

```php
// Find this block (around line 150):
if ($txn->type === 'replenishment') {
    $txn->fund->decrement('balance', $txn->amount);
} else {
    $txn->fund->increment('balance', $txn->amount);
}

// Replace with:
if ($txn->type === 'replenishment') {
    $previousBalance = (float) $txn->fund->balance;
    $txn->fund->decrement('balance', $txn->amount);
    $newBalance = $previousBalance - (float) $txn->amount;
    $this->notificationService->checkAndNotifyLowBalance($txn->fund, $previousBalance, $newBalance);
} else {
    $txn->fund->increment('balance', $txn->amount);
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter="LowBalanceCashManagementTest" 2>&1 | grep -E "PASS|FAIL|Tests:|✓|✗"
```

Expected: all 3 pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Accounting/CashManagementService.php tests/Feature/Notifications/LowBalanceCashManagementTest.php
git commit -m "feat: wire low balance notification into CashManagementService"
```

---

## Task 6: Wire FundClass::adjustBalance

**Files:**
- Modify: `app/Services/Libraries/FundClass.php`
- Create: `tests/Feature/Notifications/LowBalanceFundClassTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Notifications/LowBalanceFundClassTest.php

namespace Tests\Feature\Notifications;

use App\Models\ListRole;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
use App\Services\Libraries\FundClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowBalanceFundClassTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole   = ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => '', 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id, ['added_by_id' => $this->admin->id]);
        $this->actingAs($this->admin);
    }

    public function test_adjust_balance_fires_notification_when_new_balance_crosses_threshold(): void
    {
        Notification::fake();

        $fund    = PettyCashFund::create([
            'name'                  => 'Test Fund',
            'gl_code'               => 'PCF-FC-001',
            'balance'               => 600,
            'low_balance_threshold' => 500,
        ]);
        $request = Request::create('/funds/' . $fund->id . '/adjust', 'PATCH', [
            'id'      => $fund->id,
            'balance' => 300,
        ]);

        app(FundClass::class)->adjustBalance($fund->id, $request);

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
    }

    public function test_adjust_balance_does_not_fire_when_already_below_threshold(): void
    {
        Notification::fake();

        $fund    = PettyCashFund::create([
            'name'                  => 'Test Fund',
            'gl_code'               => 'PCF-FC-002',
            'balance'               => 200,
            'low_balance_threshold' => 500,
        ]);
        $request = Request::create('/funds/' . $fund->id . '/adjust', 'PATCH', [
            'id'      => $fund->id,
            'balance' => 100,
        ]);

        app(FundClass::class)->adjustBalance($fund->id, $request);

        Notification::assertNothingSent();
    }
}
```

- [ ] **Step 2: Check FundClass::adjustBalance signature**

```bash
grep -n "adjustBalance" /Users/radzmilalvarez/Desktop/laravel_projects/brt-software/app/Services/Libraries/FundClass.php
```

If the signature is `adjustBalance($id, $request)`, proceed. If it is `adjustBalance($request)` only, adjust the test to match.

- [ ] **Step 3: Run test to confirm it fails**

```bash
php artisan test --filter="LowBalanceFundClassTest" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: `FAIL`.

- [ ] **Step 4: Update FundClass**

In `app/Services/Libraries/FundClass.php`:

**a) Add imports and constructor:**

```php
use App\Services\NotificationService;

// Add constructor (FundClass currently has no constructor):
public function __construct(protected NotificationService $notificationService) {}
```

**b) Update `adjustBalance()`:**

```php
// Find:
public function adjustBalance($id, $request)
{
    $fund = PettyCashFund::findOrFail($id);
    $fund->update(['balance' => (float) $request->balance]);

// Replace with:
public function adjustBalance($id, $request)
{
    $fund            = PettyCashFund::findOrFail($id);
    $previousBalance = (float) $fund->balance;
    $newBalance      = (float) $request->balance;
    $fund->update(['balance' => $newBalance]);
    $this->notificationService->checkAndNotifyLowBalance($fund, $previousBalance, $newBalance);
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter="LowBalanceFundClassTest" 2>&1 | grep -E "PASS|FAIL|Tests:|✓|✗"
```

Expected: both pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Libraries/FundClass.php tests/Feature/Notifications/LowBalanceFundClassTest.php
git commit -m "feat: wire low balance notification into FundClass::adjustBalance"
```

---

## Task 7: Wire InventoryService

**Files:**
- Modify: `app/Services/Modules/InventoryService.php`
- Create: `tests/Feature/Notifications/LowStockInventoryServiceTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Notifications/LowStockInventoryServiceTest.php

namespace Tests\Feature\Notifications;

use App\Models\InventoryStocks;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\ReceivedItem;
use App\Models\ReceivedStock;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\Modules\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowStockInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole   = ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => '', 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id, ['added_by_id' => $this->admin->id]);
        $this->actingAs($this->admin);
    }

    private function makeProductWithStock(int $quantity, int $minimumStock): array
    {
        static $seq = 0;
        $brand   = ListBrand::create(['name' => 'Brand' . (++$seq)]);
        $unit    = ListUnit::create(['name' => 'pcs']);
        $product = Product::create([
            'brand_id'      => $brand->id,
            'pack_size'     => '100mg',
            'unit_id'       => $unit->id,
            'is_active'     => true,
            'minimum_stock' => $minimumStock,
        ]);
        $received = ReceivedStock::create([
            'received_date'  => now()->toDateString(),
            'received_no'    => 'RS-TEST-' . $seq,
            'received_by_id' => $this->admin->id,
        ]);
        $item = ReceivedItem::create([
            'received_id' => $received->id,
            'product_id'  => $product->id,
            'quantity'    => $quantity,
            'unit_cost'   => 10,
            'total_cost'  => $quantity * 10,
        ]);
        InventoryStocks::create([
            'received_item_id' => $item->id,
            'quantity'         => $quantity,
        ]);
        return [$product, $quantity];
    }

    public function test_deduct_stock_fires_notification_on_minimum_crossing(): void
    {
        Notification::fake();

        [$product] = $this->makeProductWithStock(15, 10);

        // Deduct 10 — stock goes from 15 to 5, crossing below minimum of 10
        app(InventoryService::class)->deductStock($product->id, 10, 'Test sale');

        Notification::assertSentTo($this->admin, LowStockNotification::class);
    }

    public function test_deduct_stock_does_not_fire_when_stock_stays_above_minimum(): void
    {
        Notification::fake();

        [$product] = $this->makeProductWithStock(20, 10);

        // Deduct 5 — stock goes from 20 to 15, still above minimum of 10
        app(InventoryService::class)->deductStock($product->id, 5, 'Test sale');

        Notification::assertNothingSent();
    }

    public function test_deduct_stock_does_not_fire_when_already_below_minimum(): void
    {
        Notification::fake();

        [$product] = $this->makeProductWithStock(5, 10);

        // Already below minimum — deducting more does not re-notify
        app(InventoryService::class)->deductStock($product->id, 2, 'Test sale');

        Notification::assertNothingSent();
    }

    public function test_record_loss_fires_notification_on_minimum_crossing(): void
    {
        Notification::fake();

        [$product] = $this->makeProductWithStock(15, 10);

        app(InventoryService::class)->recordLossOrDamage($product->id, 10, 'Damage', null, 'damage');

        Notification::assertSentTo($this->admin, LowStockNotification::class);
    }
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test --filter="LowStockInventoryServiceTest" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: all `FAIL`.

- [ ] **Step 3: Update InventoryService**

In `app/Services/Modules/InventoryService.php`:

**a) Add imports and inject NotificationService:**

```php
use App\Services\NotificationService;
```

Add constructor:

```php
public function __construct(protected NotificationService $notificationService) {}
```

**b) In `deductStock()`**, capture the previous total before the loop, then call the dispatcher after:

```php
public function deductStock($productId, $quantity, $reason, $batchCode = null)
{
    $previousTotal     = (int) $this->getCurrentStock($productId); // no batchCode = all batches
    $remainingQuantity = $quantity;

    // ... existing query and foreach loop unchanged ...

    if ($remainingQuantity > 0) {
        throw new \Exception('Insufficient stock to deduct the requested quantity.');
    }

    $this->notificationService->checkAndNotifyLowStock($productId, $previousTotal);
}
```

Place the `checkAndNotifyLowStock` call **after** the `if ($remainingQuantity > 0)` throw, so it only fires on successful deductions.

**c) In `recordLossOrDamage()`**, same pattern — add before the method's closing brace, after the `if ($remainingQuantity > 0)` throw:

```php
    if ($remainingQuantity > 0) {
        throw new \Exception('Insufficient stock to classify requested quantity as loss/damaged.');
    }

    $this->notificationService->checkAndNotifyLowStock($productId, $previousTotal);
}
```

And capture `$previousTotal` at the top of `recordLossOrDamage()`:

```php
public function recordLossOrDamage($productId, $quantity, $reason, $batchCode = null, $type = 'loss')
{
    $previousTotal     = (int) $this->getCurrentStock($productId);
    $remainingQuantity = (int) $quantity;
    // ... rest unchanged ...
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter="LowStockInventoryServiceTest" 2>&1 | grep -E "PASS|FAIL|Tests:|✓|✗"
```

Expected: all 4 pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Modules/InventoryService.php tests/Feature/Notifications/LowStockInventoryServiceTest.php
git commit -m "feat: wire low stock notification into InventoryService"
```

---

## Task 8: Wire StockReturnClass

**Files:**
- Modify: `app/Services/System/PurchaseOrder/StockReturnClass.php`
- Create: `tests/Feature/Notifications/LowStockStockReturnTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Notifications/LowStockStockReturnTest.php

namespace Tests\Feature\Notifications;

use App\Models\InventoryStocks;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivedItem;
use App\Models\ReceivedStock;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\System\PurchaseOrder\StockReturnClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowStockStockReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole   = ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => '', 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id, ['added_by_id' => $this->admin->id]);
        $this->actingAs($this->admin);
    }

    public function test_approving_stock_return_fires_notification_when_stock_crosses_minimum(): void
    {
        Notification::fake();

        // Set up product with stock at 15, minimum at 10
        $brand   = ListBrand::create(['name' => 'TestBrand']);
        $unit    = ListUnit::create(['name' => 'pcs']);
        $product = Product::create([
            'brand_id'      => $brand->id,
            'pack_size'     => '100mg',
            'unit_id'       => $unit->id,
            'is_active'     => true,
            'minimum_stock' => 10,
        ]);

        $pendingStatus  = ListStatus::create(['name' => 'Pending',  'slug' => 'pending',  'type' => 'stock_return']);
        $approvedStatus = ListStatus::create(['name' => 'Approved', 'slug' => 'approved', 'type' => 'stock_return']);

        $po     = PurchaseOrder::create(['po_number' => 'PO-001', 'supplier_id' => 1, 'status_id' => $pendingStatus->id, 'po_date' => now()->toDateString(), 'added_by_id' => $this->admin->id]);
        $poItem = PurchaseOrderItem::create([
            'po_id'             => $po->id,
            'product_id'        => $product->id,
            'quantity'          => 15,
            'received_quantity' => 15,
            'unit_cost'         => 10,
            'total_cost'        => 150,
        ]);

        $received = ReceivedStock::create(['received_date' => now()->toDateString(), 'received_no' => 'RS-001', 'received_by_id' => $this->admin->id]);
        $recvItem = ReceivedItem::create(['received_id' => $received->id, 'product_id' => $product->id, 'quantity' => 15, 'unit_cost' => 10, 'total_cost' => 150, 'po_item_id' => $poItem->id]);
        InventoryStocks::create(['received_item_id' => $recvItem->id, 'quantity' => 15]);

        $stockReturn = StockReturn::create(['return_no' => 'SR-001', 'po_id' => $po->id, 'status_id' => $pendingStatus->id, 'return_date' => now()->toDateString(), 'created_by_id' => $this->admin->id]);
        StockReturnItem::create(['stock_return_id' => $stockReturn->id, 'po_item_id' => $poItem->id, 'return_quantity' => 10, 'status_id' => $pendingStatus->id]);

        $request = Request::create('/stock-returns/' . $stockReturn->id . '/approve', 'PATCH');

        app(StockReturnClass::class)->updateStatus($stockReturn->id, 'approved', $request);

        Notification::assertSentTo($this->admin, LowStockNotification::class);
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

```bash
php artisan test --filter="LowStockStockReturnTest" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: `FAIL`.

- [ ] **Step 3: Update StockReturnClass**

In `app/Services/System/PurchaseOrder/StockReturnClass.php`:

**a) Add import and inject NotificationService:**

```php
use App\Services\NotificationService;
use App\Services\Modules\InventoryService;
```

Update constructor:

```php
public function __construct(
    SeriesService $series_service,
    protected NotificationService $notificationService,
    protected InventoryService $inventoryService,
) {
    $this->series_service = $series_service;
}
```

**b) In the approval section**, locate the `foreach ($inventoryStocks as $inventoryStock)` deduction loop (around line 243). Before that loop, capture the previous product total. After the loop, call the dispatcher.

Find the block (inside `DB::transaction`):

```php
$remainingQty = $returnQty;
foreach ($inventoryStocks as $inventoryStock) {
    if ($remainingQty <= 0) { break; }
    $deductQty = min((int) $inventoryStock->quantity, $remainingQty);
    if ($deductQty > 0) {
        $inventoryStock->decrement('quantity', $deductQty);
        $remainingQty -= $deductQty;
    }
}
$poItem->decrement('received_quantity', $returnQty);
```

Replace with:

```php
$previousTotal = (int) $this->inventoryService->getCurrentStock($poItem->product_id);
$remainingQty = $returnQty;
foreach ($inventoryStocks as $inventoryStock) {
    if ($remainingQty <= 0) { break; }
    $deductQty = min((int) $inventoryStock->quantity, $remainingQty);
    if ($deductQty > 0) {
        $inventoryStock->decrement('quantity', $deductQty);
        $remainingQty -= $deductQty;
    }
}
$poItem->decrement('received_quantity', $returnQty);
$this->notificationService->checkAndNotifyLowStock($poItem->product_id, $previousTotal);
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter="LowStockStockReturnTest" 2>&1 | grep -E "PASS|FAIL|Tests:|✓|✗"
```

Expected: 1 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/System/PurchaseOrder/StockReturnClass.php tests/Feature/Notifications/LowStockStockReturnTest.php
git commit -m "feat: wire low stock notification into StockReturnClass"
```

---

## Task 9: Wire MarkOverdueInvoices Command

**Files:**
- Modify: `app/Console/Commands/MarkOverdueInvoices.php`
- Modify: `tests/Feature/Notifications/OverdueInvoiceNotificationTest.php`

- [ ] **Step 1: Write failing test**

Open `tests/Feature/Notifications/OverdueInvoiceNotificationTest.php`. Add the following `use` imports at the top of the file (after the existing ones):

```php
use App\Models\Customer;
use App\Models\ListRole;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
```

Then add the following property and methods **inside** the existing class body:

```php
// Add these properties and setUp to the class:
private User $admin;

protected function setUp(): void
{
    parent::setUp();
    $adminRole   = ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => '', 'is_active' => true]);
    $this->admin = User::factory()->create();
    $this->admin->roles()->attach($adminRole->id, ['added_by_id' => $this->admin->id]);
}

public function test_mark_overdue_command_sends_notification_for_each_newly_overdue_invoice(): void
{
    Notification::fake();

    $overdueStatus    = ListStatus::create(['name' => 'Overdue',    'slug' => 'overdue',    'type' => 'sales_order']);
    $unpaidStatus     = ListStatus::create(['name' => 'Unpaid',     'slug' => 'unpaid',     'type' => 'sales_order']);
    $cancelledStatus  = ListStatus::create(['name' => 'Cancelled',  'slug' => 'cancelled',  'type' => 'sales_order']);
    $paidStatus       = ListStatus::create(['name' => 'Paid',       'slug' => 'paid',       'type' => 'sales_order']);

    $customer = Customer::create(['name' => 'Test Customer', 'is_active' => true]);
    $so       = SalesOrder::create([
        'so_number'   => 'SO-TEST-0001',
        'order_date'  => now()->toDateString(),
        'customer_id' => $customer->id,
        'status_id'   => $unpaidStatus->id,
        'total_amount'=> 5000,
        'added_by_id' => $this->admin->id,
    ]);
    ArInvoice::create([
        'sales_order_id' => $so->id,
        'status_id'      => $unpaidStatus->id,
        'invoice_number' => 'AR-202505-9001',
        'invoice_date'   => now()->subDays(30)->toDateString(),
        'due_date'       => now()->subDay()->toDateString(),
        'balance_due'    => 4500.00,
        'amount_paid'    => 500.00,
        'total_discount' => 0,
    ]);

    $this->artisan('invoices:mark-overdue');

    Notification::assertSentTo($this->admin, OverdueInvoiceNotification::class);
}

public function test_mark_overdue_command_does_not_notify_when_no_invoices_become_overdue(): void
{
    Notification::fake();

    // Create status rows so the command can query them, but no invoices
    ListStatus::create(['name' => 'Overdue',   'slug' => 'overdue',   'type' => 'sales_order']);
    ListStatus::create(['name' => 'Cancelled', 'slug' => 'cancelled', 'type' => 'sales_order']);
    ListStatus::create(['name' => 'Paid',      'slug' => 'paid',      'type' => 'sales_order']);

    $this->artisan('invoices:mark-overdue');

    Notification::assertNothingSent();
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test --filter="OverdueInvoiceNotificationTest::test_mark_overdue" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: `FAIL`.

- [ ] **Step 3: Update MarkOverdueInvoices**

```php
<?php
// app/Console/Commands/MarkOverdueInvoices.php

namespace App\Console\Commands;

use App\Models\ArInvoice;
use App\Models\ListStatus;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark AR invoices past their due date with an outstanding balance as Overdue';

    public function __construct(private NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $overdueStatusId   = ListStatus::getBySlug('overdue')->id;
        $cancelledStatusId = ListStatus::getBySlug('cancelled')->id;
        $paidStatusId      = ListStatus::getBySlug('paid')->id;

        $affectedIds = ArInvoice::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->where('balance_due', '>', 0)
            ->whereNotIn('status_id', [$cancelledStatusId, $paidStatusId, $overdueStatusId])
            ->pluck('id');

        if ($affectedIds->isEmpty()) {
            $this->info('No invoices to mark as overdue.');
            return;
        }

        ArInvoice::whereIn('id', $affectedIds)->update(['status_id' => $overdueStatusId]);

        $invoices = ArInvoice::with('sales_order.customer')
            ->whereIn('id', $affectedIds)
            ->get();

        $this->notificationService->notifyOverdueInvoices($invoices);

        $this->info("Marked {$invoices->count()} invoice(s) as overdue.");
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter="OverdueInvoiceNotificationTest" 2>&1 | grep -E "PASS|FAIL|Tests:|✓|✗"
```

Expected: all pass (structure test + 2 command tests).

- [ ] **Step 5: Run full test suite to check for regressions**

```bash
php artisan test 2>&1 | grep -E "Tests:|FAIL|ERROR" | tail -5
```

Expected: no failures.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/MarkOverdueInvoices.php tests/Feature/Notifications/OverdueInvoiceNotificationTest.php
git commit -m "feat: wire OverdueInvoiceNotification into MarkOverdueInvoices command"
```

---

## Task 10: Frontend — Navigation.vue

**Files:**
- Modify: `resources/js/Shared/Layouts/Components/Navigation.vue`

No automated tests for this task — verify manually in browser.

- [ ] **Step 1: Extend Echo handler to accept all three notification types**

In `resources/js/Shared/Layouts/Components/Navigation.vue`, find the `.notification()` handler (around line 168):

```js
// Find:
if (notification.type === 'low_balance') {

// Replace with:
if (['low_balance', 'low_stock', 'overdue_invoice'].includes(notification.type)) {
```

- [ ] **Step 2: Replace the hardcoded low_balance template with a type-aware display**

Find the notification list item template (around lines 286–295):

```html
<p class="mb-0 fs-12 fw-semibold text-truncate" style="max-width:240px;">
  <strong>{{ n.data.fund_name }}</strong> balance dropped to
  &#8369;{{ Number(n.data.balance).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
</p>
<p class="mb-0 fs-11 text-muted">
  Threshold: &#8369;{{ Number(n.data.threshold).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
  &middot; {{ timeAgo(n.created_at) }}
</p>
```

Replace with:

```html
<!-- low_balance -->
<template v-if="n.data.type === 'low_balance'">
  <p class="mb-0 fs-12 fw-semibold text-truncate" style="max-width:240px;">
    <strong>{{ n.data.fund_name }}</strong> balance dropped to
    &#8369;{{ Number(n.data.balance).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
  </p>
  <p class="mb-0 fs-11 text-muted">
    Threshold: &#8369;{{ Number(n.data.threshold).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
    &middot; {{ timeAgo(n.created_at) }}
  </p>
</template>

<!-- low_stock -->
<template v-else-if="n.data.type === 'low_stock'">
  <p class="mb-0 fs-12 fw-semibold text-truncate" style="max-width:240px;">
    <strong>{{ n.data.product_name }}</strong> stock is low
  </p>
  <p class="mb-0 fs-11 text-muted">
    {{ n.data.current_stock }} remaining &middot; min {{ n.data.minimum_stock }}
    &middot; {{ timeAgo(n.created_at) }}
  </p>
</template>

<!-- overdue_invoice -->
<template v-else-if="n.data.type === 'overdue_invoice'">
  <p class="mb-0 fs-12 fw-semibold text-truncate" style="max-width:240px;">
    <strong>{{ n.data.invoice_number }}</strong> overdue
  </p>
  <p class="mb-0 fs-11 text-muted">
    {{ n.data.customer_name }} &middot;
    &#8369;{{ Number(n.data.balance_due).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
    &middot; {{ timeAgo(n.created_at) }}
  </p>
</template>
```

- [ ] **Step 3: Build assets**

```bash
npm run build
```

- [ ] **Step 4: Manual verification**

Start the app (`php artisan serve` + `npm run dev`), open the notification bell dropdown. Verify:
- `low_balance` notifications still render fund name and balance
- Any `low_stock` notifications show product name + stock counts
- Any `overdue_invoice` notifications show invoice number + customer + balance

- [ ] **Step 5: Commit**

```bash
git add resources/js/Shared/Layouts/Components/Navigation.vue public/build/manifest.json
git commit -m "feat: extend notification bell to render low_stock and overdue_invoice types"
```
