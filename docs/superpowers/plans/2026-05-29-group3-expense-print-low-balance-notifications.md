# Group 3: Expense List Print & Low-Balance Notifications — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a filterable PDF expense report and real-time low-balance fund alerts (WebSocket via Reverb, persisted in DB) to the petty cash workflow.

**Architecture:** The print feature extends the existing `PrintClass` + standalone Blade template pattern used by payroll and purchase orders; filtering logic is extracted into a public `queryExpensesForPrint()` method for testability. The notification feature uses Laravel's built-in `Notifiable` trait (already on `User`), a `notifications` DB table, a `LowBalanceFundNotification` class that writes to both `database` and `broadcast` channels, triggered by a threshold-crossing check inside `ExpenseClass::save()`.

**Tech Stack:** PHP 8.x / Laravel 11, `barryvdh/laravel-dompdf` (`\PDF::` facade), Laravel Notifications + Broadcasting (Reverb), Vue 3 Options API, Bootstrap Vue `BDropdown`, `window.Echo` (already global), `window.axios` (already global).

---

## File Map

**Feature 1 — Expense List Print**

| Action | Path |
|--------|------|
| Modify | `app/Services/PrintClass.php` |
| Modify | `app/Http/Controllers/Modules/ExpenseController.php` |
| Modify | `routes/web.php` |
| Create | `resources/views/prints/expense-list.blade.php` |
| Create | `resources/js/Pages/Modules/Expenses/Modals/PrintReport.vue` |
| Modify | `resources/js/Pages/Modules/Expenses/Index.vue` |
| Create | `tests/Feature/Expenses/ExpensePrintTest.php` |

**Feature 2 — Low-Balance Notifications**

| Action | Path |
|--------|------|
| Create | `database/migrations/2026_05_29_100000_add_low_balance_threshold_to_petty_cash_funds.php` |
| Create | `database/migrations/2026_05_29_100001_create_notifications_table.php` (via artisan) |
| Modify | `app/Models/PettyCashFund.php` |
| Modify | `app/Http/Requests/Libraries/FundRequest.php` |
| Modify | `app/Services/Libraries/FundClass.php` |
| Modify | `app/Http/Resources/Libraries/FundResource.php` |
| Modify | `resources/js/Pages/Modules/Libraries/Funds/Modals/Create.vue` |
| Create | `app/Notifications/LowBalanceFundNotification.php` |
| Modify | `app/Services/Modules/ExpenseClass.php` |
| Create | `app/Http/Controllers/NotificationController.php` |
| Modify | `routes/web.php` |
| Modify | `resources/js/Shared/Layouts/Components/Navigation.vue` |
| Create | `tests/Feature/Expenses/LowBalanceNotificationTest.php` |
| Create | `tests/Feature/NotificationControllerTest.php` |

---

## Task 1: Expense Print — Tests + PrintClass + Controller + Route

**Files:**
- Create: `tests/Feature/Expenses/ExpensePrintTest.php`
- Modify: `app/Services/PrintClass.php`
- Modify: `app/Http/Controllers/Modules/ExpenseController.php`
- Modify: `routes/web.php`

---

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Expenses/ExpensePrintTest.php`:

```php
<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\PrintClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ExpensePrintTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeFund(): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create([
            'name'    => 'Test Fund',
            'gl_code' => 'PCF-PR-' . (++$seq),
            'balance' => 5000,
        ]);
    }

    private function makeExpense(array $attrs = []): Expense
    {
        return Expense::create(array_merge([
            'fund_id'      => $this->makeFund()->id,
            'expense_type' => 'operational',
            'amount'       => 100,
            'expense_date' => '2026-05-15',
            'status'       => 'recorded',
            'added_by_id'  => $this->user->id,
        ], $attrs));
    }

    public function test_print_returns_pdf_response(): void
    {
        $this->withoutMiddleware()
            ->get('/expenses/print')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_print_filtered_by_date_range(): void
    {
        $inRange  = $this->makeExpense(['expense_date' => '2026-05-15']);
        $outRange = $this->makeExpense(['expense_date' => '2026-04-15']);

        $request  = Request::create('/expenses/print', 'GET', [
            'date_from' => '2026-05-01',
            'date_to'   => '2026-05-31',
        ]);
        $expenses = app(PrintClass::class)->queryExpensesForPrint($request);

        $this->assertTrue($expenses->contains('id', $inRange->id));
        $this->assertFalse($expenses->contains('id', $outRange->id));
    }

    public function test_print_filtered_by_status(): void
    {
        $released = $this->makeExpense(['status' => 'released']);
        $recorded = $this->makeExpense(['status' => 'recorded']);

        $request  = Request::create('/expenses/print', 'GET', ['status' => 'released']);
        $expenses = app(PrintClass::class)->queryExpensesForPrint($request);

        $this->assertTrue($expenses->contains('id', $released->id));
        $this->assertFalse($expenses->contains('id', $recorded->id));
    }

    public function test_print_filtered_by_fund(): void
    {
        $fund1 = $this->makeFund();
        $fund2 = $this->makeFund();
        $e1    = $this->makeExpense(['fund_id' => $fund1->id]);
        $e2    = $this->makeExpense(['fund_id' => $fund2->id]);

        $request  = Request::create('/expenses/print', 'GET', ['fund_id' => $fund1->id]);
        $expenses = app(PrintClass::class)->queryExpensesForPrint($request);

        $this->assertTrue($expenses->contains('id', $e1->id));
        $this->assertFalse($expenses->contains('id', $e2->id));
    }

    public function test_print_filtered_by_expense_type(): void
    {
        $operational = $this->makeExpense(['expense_type' => 'operational']);
        $utilities   = $this->makeExpense(['expense_type' => 'utilities']);

        $request  = Request::create('/expenses/print', 'GET', ['expense_type' => 'operational']);
        $expenses = app(PrintClass::class)->queryExpensesForPrint($request);

        $this->assertTrue($expenses->contains('id', $operational->id));
        $this->assertFalse($expenses->contains('id', $utilities->id));
    }

    public function test_print_with_no_filters_returns_all_expenses(): void
    {
        $e1 = $this->makeExpense();
        $e2 = $this->makeExpense(['expense_type' => 'utilities']);

        $request  = Request::create('/expenses/print', 'GET', []);
        $expenses = app(PrintClass::class)->queryExpensesForPrint($request);

        $this->assertTrue($expenses->contains('id', $e1->id));
        $this->assertTrue($expenses->contains('id', $e2->id));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/radzmilalvarez/Desktop/laravel_projects/brt-software
php artisan test --filter=ExpensePrintTest
```

Expected: All 6 tests FAIL — `queryExpensesForPrint` does not exist, `printExpenses` does not exist, route 404.

- [ ] **Step 3: Add `queryExpensesForPrint` and `printExpenseList` to PrintClass**

Open `app/Services/PrintClass.php`. Add these two methods inside the `PrintClass` class, after the existing `printPayroll` method:

```php
public function queryExpensesForPrint(\Illuminate\Http\Request $request): \Illuminate\Database\Eloquent\Collection
{
    return \App\Models\Expense::with(['added_by', 'fund'])
        ->when(
            $request->date_from && $request->date_to,
            fn($q) => $q->whereBetween('expense_date', [$request->date_from, $request->date_to])
        )
        ->when($request->status,       fn($q, $s) => $q->where('status',       $s))
        ->when($request->fund_id,      fn($q, $id) => $q->where('fund_id',     $id))
        ->when($request->expense_type, fn($q, $t) => $q->where('expense_type', $t))
        ->orderBy('expense_date', 'ASC')
        ->get();
}

public function printExpenseList(\Illuminate\Http\Request $request)
{
    $expenses = $this->queryExpensesForPrint($request);
    $total    = $expenses->sum('amount');

    $filters = [
        'date_from'    => $request->date_from,
        'date_to'      => $request->date_to,
        'status'       => $request->status,
        'fund_name'    => $request->fund_id
            ? \App\Models\PettyCashFund::find($request->fund_id)?->name
            : null,
        'expense_type' => $request->expense_type,
    ];

    $pdf = \PDF::loadView('prints.expense-list', compact('expenses', 'total', 'filters'))
        ->setPaper('A4', 'landscape');

    return $pdf->stream('expense-list-' . now()->format('Ymd') . '.pdf');
}
```

- [ ] **Step 4: Update `ExpenseController` — inject `PrintClass`, add `printExpenses`**

Open `app/Http/Controllers/Modules/ExpenseController.php`.

Replace the constructor with:

```php
use App\Services\PrintClass;

// inside the class:

public function __construct(
    protected ExpenseClass $expense,
    protected DropdownClass $dropdown,
    protected PrintClass $print
) {}
```

Add the `use App\Services\PrintClass;` import at the top of the file (after the existing `use` statements).

Add the `printExpenses` method after the `void` method:

```php
public function printExpenses(Request $request)
{
    return $this->print->printExpenseList($request);
}
```

- [ ] **Step 5: Add the print route to `routes/web.php`**

In `routes/web.php`, inside the `role:Administrator` middleware group (around line 140), add the print route **before** `Route::resource('/expenses', ...)` so it is not caught by the `{expense}` wildcard:

```php
Route::get('/expenses/print', [App\Http\Controllers\Modules\ExpenseController::class, 'printExpenses']);
Route::resource('/expenses', App\Http\Controllers\Modules\ExpenseController::class);
Route::patch('/expenses/{id}/approve', [App\Http\Controllers\Modules\ExpenseController::class, 'approve']);
Route::patch('/expenses/{id}/void', [App\Http\Controllers\Modules\ExpenseController::class, 'void']);
```

(The resource and patch lines are already there — only move and add the print line above them.)

- [ ] **Step 6: Run the 5 filter tests to verify they pass (PDF test still fails — no blade yet)**

```bash
php artisan test --filter=ExpensePrintTest
```

Expected: 5 filter tests PASS; `test_print_returns_pdf_response` still FAILs with a missing view error — that is fine, the Blade template is in Task 2.

- [ ] **Step 7: Commit**

```bash
git add app/Services/PrintClass.php \
        app/Http/Controllers/Modules/ExpenseController.php \
        routes/web.php \
        tests/Feature/Expenses/ExpensePrintTest.php
git commit -m "feat: add expense list print backend (PrintClass, route, 5/6 tests passing)"
```

---

## Task 2: Expense Print — Blade Template

**Files:**
- Create: `resources/views/prints/expense-list.blade.php`

---

- [ ] **Step 1: Create the Blade template**

Create `resources/views/prints/expense-list.blade.php`:

```html
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expense List Report</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #333; line-height: 1.4; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .company-name  { font-size: 18px; font-weight: bold; color: #1a1a1a; }
        .report-title  { font-size: 22px; font-weight: bold; color: #16322e; }
        .report-meta   { font-size: 10px; color: #6b8c85; }
        .filter-bar    { background: #f0f7f5; border: 1px solid #c4d9d2; padding: 6px 10px; margin-bottom: 12px; font-size: 9px; color: #6b8c85; }
        table          { width: 100%; border-collapse: collapse; }
        thead th       { background: #D5DBDB; border: 1px solid #BDC3C7; padding: 6px 8px; text-align: left; font-weight: bold; }
        tbody td       { padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        tfoot td       { padding: 6px 8px; font-weight: bold; background: #D5DBDB; border-top: 2px solid #BDC3C7; }
        .text-right    { text-align: right; }
        .empty-row td  { text-align: center; padding: 20px; color: #999; }
        .footer        { margin-top: 12px; font-size: 8px; color: #aaa; text-align: right; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td><span class="company-name">BRT Accounting System</span></td>
            <td style="text-align:right">
                <span class="report-title">Expense List Report</span><br>
                <span class="report-meta">Generated: {{ now()->format('M d, Y h:i A') }}</span>
            </td>
        </tr>
    </table>

    <div class="filter-bar">
        <strong>Filters:</strong>
        @if($filters['date_from'] && $filters['date_to'])
            Period: {{ \Carbon\Carbon::parse($filters['date_from'])->format('M d, Y') }} — {{ \Carbon\Carbon::parse($filters['date_to'])->format('M d, Y') }}
            @if($filters['fund_name'] || $filters['status'] || $filters['expense_type']) | @endif
        @endif
        @if($filters['fund_name'])
            Fund: {{ $filters['fund_name'] }}
            @if($filters['status'] || $filters['expense_type']) | @endif
        @endif
        @if($filters['status'])
            Status: {{ ucfirst($filters['status']) }}
            @if($filters['expense_type']) | @endif
        @endif
        @if($filters['expense_type'])
            Type: {{ ucfirst($filters['expense_type']) }}
        @endif
        @if(!$filters['date_from'] && !$filters['fund_name'] && !$filters['status'] && !$filters['expense_type'])
            All expenses (no filters applied)
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:9%">Date</th>
                <th style="width:13%">Type</th>
                <th style="width:15%">Fund</th>
                <th>Description</th>
                <th style="width:11%;text-align:right">Amount</th>
                <th style="width:9%">Status</th>
                <th style="width:16%">Recorded By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expenses as $expense)
            <tr>
                <td>{{ \Carbon\Carbon::parse($expense->expense_date)->format('M d, Y') }}</td>
                <td>{{ ucfirst($expense->expense_type) }}</td>
                <td>{{ $expense->fund?->name ?? '—' }}</td>
                <td>{{ $expense->description ?? '—' }}</td>
                <td class="text-right">₱{{ number_format($expense->amount, 2) }}</td>
                <td>{{ ucfirst($expense->status) }}</td>
                <td>{{ $expense->added_by?->name ?? $expense->added_by?->username ?? '—' }}</td>
            </tr>
            @empty
            <tr class="empty-row">
                <td colspan="7">No expenses found matching the selected filters.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right">Grand Total ({{ $expenses->count() }} record{{ $expenses->count() === 1 ? '' : 's' }}):</td>
                <td class="text-right">₱{{ number_format($total, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">BRT Accounting System — Printed {{ now()->format('M d, Y h:i A') }}</div>

</body>
</html>
```

- [ ] **Step 2: Run all print tests to verify all 6 pass**

```bash
php artisan test --filter=ExpensePrintTest
```

Expected: All 6 tests PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/views/prints/expense-list.blade.php
git commit -m "feat: add expense list print Blade template (all 6 tests passing)"
```

---

## Task 3: Expense Print — Frontend Modal + Index.vue Wiring

**Files:**
- Create: `resources/js/Pages/Modules/Expenses/Modals/PrintReport.vue`
- Modify: `resources/js/Pages/Modules/Expenses/Index.vue`

---

- [ ] **Step 1: Create `PrintReport.vue`**

Create `resources/js/Pages/Modules/Expenses/Modals/PrintReport.vue`:

```vue
<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" style="max-width:520px" @click.stop>
            <div class="modal-header">
                <div class="modal-header-icon"><i class="ri-printer-line"></i></div>
                <div>
                    <h2>Print Expense Report</h2>
                    <p class="modal-header-kicker">Select filters for the report</p>
                </div>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Date From</label>
                        <input type="date" v-model="form.date_from" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Date To</label>
                        <input type="date" v-model="form.date_to" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Status <span class="text-muted">(optional)</span></label>
                        <select v-model="form.status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="recorded">Recorded</option>
                            <option value="approved">Approved</option>
                            <option value="submitted">Submitted</option>
                            <option value="released">Released</option>
                            <option value="reimbursed">Reimbursed</option>
                            <option value="voided">Voided</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Fund <span class="text-muted">(optional)</span></label>
                        <select v-model="form.fund_id" class="form-select">
                            <option value="">All Funds</option>
                            <option v-for="f in funds" :key="f.id" :value="f.id">{{ f.name }}</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Expense Type <span class="text-muted">(optional)</span></label>
                        <select v-model="form.expense_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="operational">Operational</option>
                            <option value="utilities">Utilities</option>
                            <option value="supplies">Supplies</option>
                            <option value="transportation">Transportation</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="others">Others</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide">
                    <i class="ri-close-line"></i> Cancel
                </button>
                <button type="button" class="btn btn-save" @click="print">
                    <i class="ri-printer-line"></i> Print Report
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: ['funds'],
    data() {
        return {
            showModal: false,
            form: {
                date_from:    '',
                date_to:      '',
                status:       '',
                fund_id:      '',
                expense_type: '',
            },
        };
    },
    methods: {
        show() {
            const now     = new Date();
            const y       = now.getFullYear();
            const m       = String(now.getMonth() + 1).padStart(2, '0');
            const lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
            this.form.date_from    = `${y}-${m}-01`;
            this.form.date_to      = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
            this.form.status       = '';
            this.form.fund_id      = '';
            this.form.expense_type = '';
            this.showModal = true;
        },
        hide() {
            this.showModal = false;
        },
        print() {
            const params = new URLSearchParams();
            if (this.form.date_from)    params.set('date_from',    this.form.date_from);
            if (this.form.date_to)      params.set('date_to',      this.form.date_to);
            if (this.form.status)       params.set('status',       this.form.status);
            if (this.form.fund_id)      params.set('fund_id',      this.form.fund_id);
            if (this.form.expense_type) params.set('expense_type', this.form.expense_type);
            window.open(`/expenses/print?${params.toString()}`, '_blank');
            this.hide();
        },
    },
};
</script>
```

- [ ] **Step 2: Wire `PrintReport.vue` into `Index.vue`**

Open `resources/js/Pages/Modules/Expenses/Index.vue`.

**2a. Add import** — in the `<script>` block, add after the existing `import Create from './Modals/Create.vue';` line:

```js
import PrintReport from './Modals/PrintReport.vue';
```

**2b. Add to components** — change the `components` declaration to include `PrintReport`:

```js
components: { PageHeader, Pagination, Create, DeleteModal, PrintReport },
```

**2c. Add ref to template** — find the `<Create ...` component tag near the bottom of `<template>` and add the `PrintReport` component beside it:

```html
<Create @add="fetch()" @update="fetch()" @close="selectedRow = null" :dropdowns="dropdowns" ref="create" />
<PrintReport :funds="dropdowns.funds" ref="printReport" />
```

**2d. Add "Print Report" button** — in the page header area (find the section that has the "New Expense" or search controls), add the print button. Look for the tab header / controls row and add:

```html
<button class="btn btn-outline-secondary btn-sm" @click="$refs.printReport.show()" title="Print Report">
    <i class="ri-printer-line"></i> Print Report
</button>
```

- [ ] **Step 3: Build assets to verify no compile errors**

```bash
npm run build
```

Expected: Build succeeds with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Modules/Expenses/Modals/PrintReport.vue \
        resources/js/Pages/Modules/Expenses/Index.vue \
        public/build/manifest.json
git commit -m "feat: add expense list print modal (PrintReport.vue)"
```

---

## Task 4: Fund Threshold — Migrations + PettyCashFund Model

**Files:**
- Create: `database/migrations/2026_05_29_100000_add_low_balance_threshold_to_petty_cash_funds.php`
- Create: notifications table migration (via `php artisan notifications:table`)
- Modify: `app/Models/PettyCashFund.php`

---

- [ ] **Step 1: Create the `low_balance_threshold` migration**

```bash
php artisan make:migration add_low_balance_threshold_to_petty_cash_funds --table=petty_cash_funds
```

Open the generated file and replace its `up` and `down` bodies:

```php
public function up(): void
{
    Schema::table('petty_cash_funds', function (Blueprint $table) {
        $table->decimal('low_balance_threshold', 10, 2)->nullable()->after('weekly_budget');
    });
}

public function down(): void
{
    Schema::table('petty_cash_funds', function (Blueprint $table) {
        $table->dropColumn('low_balance_threshold');
    });
}
```

- [ ] **Step 2: Generate the Laravel notifications table migration**

```bash
php artisan notifications:table
```

This creates a migration file like `database/migrations/YYYY_MM_DD_HHMMSS_create_notifications_table.php`. Do not edit it — it contains the standard Laravel notifications schema.

- [ ] **Step 3: Run both migrations**

```bash
php artisan migrate
```

Expected output includes lines for both the new `petty_cash_funds` column and the `notifications` table.

- [ ] **Step 4: Update `PettyCashFund` model**

Open `app/Models/PettyCashFund.php`. Make these changes:

**Add to `$fillable`:**
```php
protected $fillable = ['name', 'gl_code', 'balance', 'weekly_budget', 'low_balance_threshold', 'is_active', 'created_by_id'];
```

**Add to `$casts`:**
```php
protected $casts = [
    'is_active'             => 'boolean',
    'balance'               => 'float',
    'weekly_budget'         => 'float',
    'low_balance_threshold' => 'float',
];
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/PettyCashFund.php
git commit -m "feat: add low_balance_threshold to petty_cash_funds, add notifications table"
```

---

## Task 5: Fund Service Layer — FundRequest + FundClass + FundResource

**Files:**
- Modify: `app/Http/Requests/Libraries/FundRequest.php`
- Modify: `app/Services/Libraries/FundClass.php`
- Modify: `app/Http/Resources/Libraries/FundResource.php`

---

- [ ] **Step 1: Add `low_balance_threshold` to `FundRequest`**

Open `app/Http/Requests/Libraries/FundRequest.php`. Add to the `rules()` array:

```php
'low_balance_threshold' => 'nullable|numeric|min:0',
```

The full `rules()` becomes:

```php
public function rules(): array
{
    $id = $this->input('id');
    return [
        'name'                  => 'required|string|max:255',
        'gl_code'               => 'required|string|max:50|unique:petty_cash_funds,gl_code' . ($id ? ",{$id}" : ''),
        'weekly_budget'         => 'nullable|numeric|min:0',
        'low_balance_threshold' => 'nullable|numeric|min:0',
    ];
}
```

- [ ] **Step 2: Persist `low_balance_threshold` in `FundClass`**

Open `app/Services/Libraries/FundClass.php`.

In `save()`, add `low_balance_threshold` to the create array:

```php
public function save($request, $userId = null)
{
    $data = PettyCashFund::create([
        'name'                  => $request->name,
        'gl_code'               => $request->gl_code,
        'balance'               => 0,
        'weekly_budget'         => $request->weekly_budget ?? null,
        'low_balance_threshold' => $request->low_balance_threshold ?? null,
        'is_active'             => true,
        'created_by_id'         => $userId ?: auth()->id(),
    ]);

    return [
        'data'    => new FundResource($data),
        'message' => 'Fund created successfully!',
        'info'    => "Petty cash fund '{$data->name}' has been created.",
    ];
}
```

In `update()`, add `low_balance_threshold` to the update array:

```php
public function update($request)
{
    $data = PettyCashFund::findOrFail($request->id);
    $data->update([
        'name'                  => $request->name,
        'gl_code'               => $request->gl_code,
        'weekly_budget'         => $request->weekly_budget ?? null,
        'low_balance_threshold' => $request->low_balance_threshold ?? null,
    ]);

    return [
        'data'    => new FundResource($data->fresh()),
        'message' => 'Fund updated successfully!',
        'info'    => "Petty cash fund '{$data->name}' has been updated.",
    ];
}
```

- [ ] **Step 3: Add `low_balance_threshold` to `FundResource`**

Open `app/Http/Resources/Libraries/FundResource.php`. Add to the `toArray()` return:

```php
public function toArray(Request $request): array
{
    return [
        'id'                    => $this->id,
        'name'                  => $this->name,
        'gl_code'               => $this->gl_code,
        'balance'               => (float) $this->balance,
        'weekly_budget'         => $this->weekly_budget ? (float) $this->weekly_budget : null,
        'low_balance_threshold' => $this->low_balance_threshold ? (float) $this->low_balance_threshold : null,
        'is_active'             => (bool) $this->is_active,
        'created_at'            => $this->created_at,
    ];
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Requests/Libraries/FundRequest.php \
        app/Services/Libraries/FundClass.php \
        app/Http/Resources/Libraries/FundResource.php
git commit -m "feat: persist and expose low_balance_threshold in fund service layer"
```

---

## Task 6: Fund Create.vue — Add Threshold Field

**Files:**
- Modify: `resources/js/Pages/Modules/Libraries/Funds/Modals/Create.vue`

---

- [ ] **Step 1: Add `low_balance_threshold` to the form and template**

Open `resources/js/Pages/Modules/Libraries/Funds/Modals/Create.vue`.

**In the `data()` — `form` object**, add `low_balance_threshold: null` to the `useForm({...})` call:

```js
form: useForm({ id: null, name: null, gl_code: null, weekly_budget: null, low_balance_threshold: null }),
```

**In `show()` and defaults**, add `low_balance_threshold: null`:

```js
show() {
    this.form.defaults({
        id: null, name: null, gl_code: null, weekly_budget: null, low_balance_threshold: null,
    }).reset();
    // ...
},
```

**In `edit(fund)`**, add:

```js
edit(fund) {
    this.form.id                    = fund.id;
    this.form.name                  = fund.name;
    this.form.gl_code               = fund.gl_code;
    this.form.weekly_budget         = fund.weekly_budget;
    this.form.low_balance_threshold = fund.low_balance_threshold;
    this.editable   = true;
    this.saveSuccess = false;
    this.showModal   = true;
},
```

**In the template**, add the threshold input below the `weekly_budget` field:

```html
<div class="form-group mb-3">
    <label class="form-label">Low Balance Threshold <span class="text-muted">(optional)</span></label>
    <div class="input-group">
        <span class="input-group-text">₱</span>
        <input type="number" v-model="form.low_balance_threshold" class="form-control"
               placeholder="e.g. 500.00" min="0" step="0.01">
    </div>
    <small class="text-muted">Alert will fire when fund balance drops below this amount.</small>
</div>
```

- [ ] **Step 2: Build assets to verify no compile errors**

```bash
npm run build
```

Expected: Build succeeds.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Modules/Libraries/Funds/Modals/Create.vue \
        public/build/manifest.json
git commit -m "feat: add low_balance_threshold field to fund create/edit modal"
```

---

## Task 7: LowBalanceFundNotification Class + Tests

**Files:**
- Create: `app/Notifications/LowBalanceFundNotification.php`
- Create: `tests/Feature/Expenses/LowBalanceNotificationTest.php`

---

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Expenses/LowBalanceNotificationTest.php`:

```php
<?php

namespace Tests\Feature\Expenses;

use App\Models\ListRole;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
use App\Services\Modules\ExpenseClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowBalanceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user  = User::factory()->create();
        $adminRole   = ListRole::create(['name' => 'Administrator', 'type' => 'system', 'definition' => '', 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id);
        $this->actingAs($this->user);
    }

    private function makeFund(float $balance, ?float $threshold = null): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create([
            'name'                  => 'Fund',
            'gl_code'               => 'PCF-NT-' . (++$seq),
            'balance'               => $balance,
            'is_active'             => true,
            'low_balance_threshold' => $threshold,
        ]);
    }

    private function saveExpense(PettyCashFund $fund, float $amount): array
    {
        $request = new Request();
        $request->merge([
            'fund_id'      => $fund->id,
            'expense_type' => 'operational',
            'amount'       => $amount,
            'expense_date' => now()->toDateString(),
            'status'       => 'recorded',
        ]);
        return app(ExpenseClass::class)->save($request, $this->user->id);
    }

    public function test_notification_fired_when_balance_crosses_threshold(): void
    {
        Notification::fake();

        $fund = $this->makeFund(balance: 600, threshold: 500);
        $this->saveExpense($fund, 200); // 600 → 400, crosses 500

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
    }

    public function test_notification_not_fired_when_already_below_threshold(): void
    {
        Notification::fake();

        $fund = $this->makeFund(balance: 400, threshold: 500); // already below
        $this->saveExpense($fund, 50); // 400 → 350, no crossing event

        Notification::assertNothingSent();
    }

    public function test_notification_not_fired_when_no_threshold_set(): void
    {
        Notification::fake();

        $fund = $this->makeFund(balance: 600, threshold: null);
        $this->saveExpense($fund, 200);

        Notification::assertNothingSent();
    }

    public function test_notification_fires_again_after_replenishment_crosses_threshold(): void
    {
        Notification::fake();

        $fund = $this->makeFund(balance: 600, threshold: 500);
        $this->saveExpense($fund, 200); // 600 → 400, fires

        // Replenish: bring balance back above threshold
        $fund->update(['balance' => 700]);

        $this->saveExpense($fund, 250); // 700 → 450, crosses again

        Notification::assertSentToTimes($this->admin, LowBalanceFundNotification::class, 2);
    }

    public function test_notification_sent_to_administrator_only_not_regular_users(): void
    {
        Notification::fake();

        $nonAdmin = User::factory()->create(); // no role

        $fund = $this->makeFund(balance: 600, threshold: 500);
        $this->saveExpense($fund, 200);

        Notification::assertSentTo($this->admin, LowBalanceFundNotification::class);
        Notification::assertNotSentTo($nonAdmin, LowBalanceFundNotification::class);
        Notification::assertNotSentTo($this->user, LowBalanceFundNotification::class);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=LowBalanceNotificationTest
```

Expected: All 5 tests FAIL — `LowBalanceFundNotification` class does not exist.

- [ ] **Step 3: Create `LowBalanceFundNotification`**

Create `app/Notifications/LowBalanceFundNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\PettyCashFund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class LowBalanceFundNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(public PettyCashFund $fund) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'      => 'low_balance',
            'fund_id'   => $this->fund->id,
            'fund_name' => $this->fund->name,
            'balance'   => (float) $this->fund->balance,
            'threshold' => (float) $this->fund->low_balance_threshold,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

The notification broadcasts on the standard `App.Models.User.{id}` private channel, which is already authorized in `routes/channels.php`.

- [ ] **Step 4: Run the notification class tests** (the trigger tests still fail — that's Task 8)

```bash
php artisan test --filter=LowBalanceNotificationTest
```

Expected: Tests that check `assertNothingSent` (tests 2, 3) still FAIL because `ExpenseClass::save()` does not yet contain the trigger. Tests 1, 4, 5 also FAIL (trigger not in place). All 5 FAIL — expected.

- [ ] **Step 5: Commit the notification class**

```bash
git add app/Notifications/LowBalanceFundNotification.php \
        tests/Feature/Expenses/LowBalanceNotificationTest.php
git commit -m "feat: add LowBalanceFundNotification class and notification tests (failing until trigger added)"
```

---

## Task 8: ExpenseClass — Add Threshold Trigger

**Files:**
- Modify: `app/Services/Modules/ExpenseClass.php`

---

- [ ] **Step 1: Add imports to `ExpenseClass`**

Open `app/Services/Modules/ExpenseClass.php`. Add these two `use` statements at the top of the file, with the existing imports:

```php
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
```

- [ ] **Step 2: Add threshold crossing check in `save()`**

In `ExpenseClass::save()`, locate the `if ($request->fund_id)` block. Replace the entire block with:

```php
if ($request->fund_id) {
    $fund = PettyCashFund::findOrFail($request->fund_id);

    if (! $fund->is_active) {
        throw ValidationException::withMessages([
            'fund_id' => "The selected fund '{$fund->name}' is inactive and cannot receive new expenses.",
        ]);
    }

    if ($fund->balance < $amount) {
        throw ValidationException::withMessages([
            'amount' => "Amount (₱" . number_format($amount, 2) . ") exceeds available fund balance (₱" . number_format($fund->balance, 2) . ").",
        ]);
    }

    $previousBalance = (float) $fund->balance;
    $fund->decrement('balance', $amount);
    $newBalance = $previousBalance - $amount;

    if (
        $fund->low_balance_threshold !== null
        && $previousBalance >= (float) $fund->low_balance_threshold
        && $newBalance < (float) $fund->low_balance_threshold
    ) {
        $notifyFund = $fund->fresh();
        User::whereHas('roles', fn($q) =>
            $q->whereIn('name', ['Administrator', 'Top Management'])
        )->each(fn($u) => $u->notify(new LowBalanceFundNotification($notifyFund)));
    }
}
```

- [ ] **Step 3: Run notification tests to verify all 5 pass**

```bash
php artisan test --filter=LowBalanceNotificationTest
```

Expected: All 5 tests PASS.

- [ ] **Step 4: Run the full expense test suite to verify no regressions**

```bash
php artisan test --filter=ExpenseModuleTest,ExpenseApprovalTest,ExpenseVoidTest,BudgetCarryoverTest,ExpensePrintTest,LowBalanceNotificationTest
```

Expected: All tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Modules/ExpenseClass.php
git commit -m "feat: add low-balance threshold crossing notification trigger in ExpenseClass::save()"
```

---

## Task 9: NotificationController + Routes + Tests

**Files:**
- Create: `app/Http/Controllers/NotificationController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/NotificationControllerTest.php`

---

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/NotificationControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function insertNotification(bool $read = false): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id'              => $id,
            'type'            => 'App\Notifications\LowBalanceFundNotification',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id'   => $this->user->id,
            'data'            => json_encode([
                'type'      => 'low_balance',
                'fund_id'   => 1,
                'fund_name' => 'Main Fund',
                'balance'   => 400.0,
                'threshold' => 500.0,
            ]),
            'read_at'    => $read ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $id;
    }

    public function test_index_returns_notifications_and_unread_count(): void
    {
        $this->insertNotification(read: false);
        $this->insertNotification(read: false);
        $this->insertNotification(read: true);

        $this->withoutMiddleware()
            ->getJson('/notifications')
            ->assertStatus(200)
            ->assertJsonStructure(['notifications', 'unread_count'])
            ->assertJsonPath('unread_count', 2);
    }

    public function test_mark_read_sets_read_at(): void
    {
        $id = $this->insertNotification(read: false);

        $this->withoutMiddleware()
            ->patchJson("/notifications/{$id}/read")
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertNotNull(
            DB::table('notifications')->where('id', $id)->value('read_at')
        );
    }

    public function test_mark_all_read_clears_all_unread(): void
    {
        $this->insertNotification(read: false);
        $this->insertNotification(read: false);
        $this->insertNotification(read: false);

        $this->withoutMiddleware()
            ->patchJson('/notifications/read-all')
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $unread = DB::table('notifications')
            ->where('notifiable_id', $this->user->id)
            ->whereNull('read_at')
            ->count();
        $this->assertEquals(0, $unread);
    }
}
```

- [ ] **Step 2: Run to verify tests fail**

```bash
php artisan test --filter=NotificationControllerTest
```

Expected: All 3 tests FAIL — controller and routes do not exist (404).

- [ ] **Step 3: Create `NotificationController`**

Create `app/Http/Controllers/NotificationController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $type = \App\Notifications\LowBalanceFundNotification::class;

        $notifications = $request->user()
            ->notifications()
            ->where('type', $type)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'data'       => $n->data,
                'read_at'    => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        $unreadCount = $request->user()
            ->unreadNotifications()
            ->where('type', $type)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    public function markRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['status' => 'success']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()
            ->unreadNotifications()
            ->where('type', \App\Notifications\LowBalanceFundNotification::class)
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'success']);
    }
}
```

- [ ] **Step 4: Add notification routes to `routes/web.php`**

In `routes/web.php`, inside the outer `auth` middleware group (line 13), add these three routes **before** any role-specific sub-groups (around line 37, before the first `Route::middleware(['role:...'])` block):

```php
// Notification endpoints — available to all authenticated users
Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
Route::patch('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead']);
Route::patch('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead']);
```

**Important:** `read-all` must be listed BEFORE `{id}/read` to avoid `read-all` being treated as a notification ID.

- [ ] **Step 5: Run notification controller tests to verify all 3 pass**

```bash
php artisan test --filter=NotificationControllerTest
```

Expected: All 3 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/NotificationController.php \
        routes/web.php \
        tests/Feature/NotificationControllerTest.php
git commit -m "feat: add NotificationController with index, markRead, markAllRead endpoints"
```

---

## Task 10: Navigation.vue — Notification Bell

**Files:**
- Modify: `resources/js/Shared/Layouts/Components/Navigation.vue`

---

- [ ] **Step 1: Add `notifications` and `unreadCount` to `data()`**

Open `resources/js/Shared/Layouts/Components/Navigation.vue`.

In the Options API `<script>` block, find the `data()` method and add:

```js
data() {
    return {
        currentUrl: window.location.origin,
        text: null,
        value: null,
        myVar: 1,
        notifications: [],
        unreadCount:   0,
    };
},
```

- [ ] **Step 2: Add `fetchNotifications`, `markAllRead`, and `timeAgo` to `methods`**

In the `methods` object, add after the existing methods:

```js
fetchNotifications() {
    axios.get('/notifications')
        .then(res => {
            this.notifications = res.data.notifications;
            this.unreadCount   = res.data.unread_count;
        })
        .catch(() => {});
},
markAllRead() {
    axios.patch('/notifications/read-all')
        .then(() => {
            this.unreadCount = 0;
            this.notifications.forEach(n => { n.read_at = new Date().toISOString(); });
        })
        .catch(() => {});
},
timeAgo(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1)  return 'just now';
    if (mins < 60) return `${mins} minute${mins !== 1 ? 's' : ''} ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24)  return `${hrs} hour${hrs !== 1 ? 's' : ''} ago`;
    const days = Math.floor(hrs / 24);
    return `${days} day${days !== 1 ? 's' : ''} ago`;
},
```

- [ ] **Step 3: Add Echo subscription + initial fetch to `mounted()`**

In the `mounted()` method, add after the existing listeners:

```js
mounted() {
    // ... existing scroll and hamburger listeners ...

    this.fetchNotifications();

    const userId = this.$page?.props?.user?.data?.id;
    if (window.Echo && userId) {
        window.Echo.private('App.Models.User.' + userId)
            .notification((notification) => {
                if (notification.type === 'low_balance') {
                    this.unreadCount++;
                    this.notifications.unshift({
                        id:         notification.id,
                        data:       notification,
                        read_at:    null,
                        created_at: new Date().toISOString(),
                    });
                }
            });
    }
},
```

- [ ] **Step 4: Add the notification bell to the template**

In the `<template>` section, locate the topbar controls area (around the fullscreen button and user dropdown). Add the bell dropdown **between** the fullscreen button and the user dropdown:

```html
<!-- Notification Bell -->
<BDropdown
    variant="link"
    class="ms-1 header-item d-none d-sm-flex topbar-head-dropdown"
    toggle-class="btn btn-icon btn-topbar rounded-circle arrow-none position-relative"
    menu-class="dropdown-menu-end p-0"
    :offset="{ alignmentAxis: -14, crossAxis: 0, mainAxis: 0 }">
    <template #button-content>
        <i class="bx bxs-bell fs-22"></i>
        <span v-if="unreadCount > 0"
              class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
              style="font-size:9px;padding:3px 5px">
            {{ unreadCount > 9 ? '9+' : unreadCount }}
        </span>
    </template>

    <div style="min-width:280px;max-width:320px">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-semibold fs-14">Notifications</h6>
            <button v-if="unreadCount > 0"
                    class="btn btn-sm btn-link p-0 text-muted fs-12"
                    @click="markAllRead">
                Mark all read
            </button>
        </div>

        <div v-if="notifications.length === 0"
             class="p-4 text-center text-muted"
             style="font-size:12px">
            No notifications
        </div>

        <div v-for="n in notifications"
             :key="n.id"
             class="p-3 border-bottom"
             :style="n.read_at ? 'background:#fff' : 'background:#fffbeb'"
             style="font-size:12px">
            <div class="d-flex align-items-start gap-2">
                <i class="ri-alert-line text-warning mt-1 fs-14 flex-shrink-0"></i>
                <div>
                    <strong>{{ n.data.fund_name }}</strong>
                    balance dropped to
                    ₱{{ Number(n.data.balance).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                    <span class="text-muted">(threshold: ₱{{ Number(n.data.threshold).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }})</span>
                    <br>
                    <small class="text-muted">{{ timeAgo(n.created_at) }}</small>
                </div>
            </div>
        </div>
    </div>
</BDropdown>
```

- [ ] **Step 5: Build assets to verify no compile errors**

```bash
npm run build
```

Expected: Build succeeds with no errors.

- [ ] **Step 6: Run the full test suite to confirm no regressions**

```bash
php artisan test --filter=ExpensePrintTest,LowBalanceNotificationTest,NotificationControllerTest,ExpenseModuleTest,ExpenseApprovalTest,ExpenseVoidTest,BudgetCarryoverTest
```

Expected: All tests PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Shared/Layouts/Components/Navigation.vue \
        public/build/manifest.json
git commit -m "feat: add notification bell with low-balance alerts to Navigation.vue"
```

---

## Final Checklist

After all tasks are complete, verify:

- [ ] `php artisan test --filter=ExpensePrintTest` → 6/6 PASS
- [ ] `php artisan test --filter=LowBalanceNotificationTest` → 5/5 PASS
- [ ] `php artisan test --filter=NotificationControllerTest` → 3/3 PASS
- [ ] `GET /expenses/print?date_from=2026-05-01&date_to=2026-05-31` opens a PDF in the browser
- [ ] `PrintReport.vue` modal opens from the Expenses page with current-month defaults
- [ ] Creating an expense against a fund whose balance crosses the threshold fires a notification
- [ ] Bell icon shows unread count badge; dropdown lists fund name + balance + time ago
- [ ] "Mark all read" clears the badge and updates row backgrounds
- [ ] Fund create/edit modal shows the low balance threshold field
