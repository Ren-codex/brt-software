# Group 1: Fund Management & Expense Approval — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add petty cash fund CRUD (at `/libraries/funds`) and restore the individual expense approval workflow (`PATCH /expenses/{id}/approve`).

**Architecture:** Follows the existing Libraries CRUD pattern exactly (BrandController → BrandClass → BrandRequest). Fund management lives under the `role:Administrator` Libraries route group. Expense approval restores a removed route and adds an inline button to the Expenses Index. Replenishment approval is updated to bulk-promote any remaining `recorded` expenses to `reimbursed`.

**Tech Stack:** Laravel 11, Vue 3 (Options API), Inertia.js, Bootstrap 5, Pest (tests), Axios (frontend HTTP)

**Spec:** `docs/superpowers/specs/2026-05-29-group1-fund-management-expense-approval-design.md`

---

## File Map

**Create:**
- `app/Http/Requests/Libraries/FundRequest.php`
- `app/Http/Resources/Libraries/FundResource.php`
- `app/Services/Libraries/FundClass.php`
- `app/Http/Controllers/Libraries/FundController.php`
- `resources/js/Pages/Modules/Libraries/Funds/Index.vue`
- `resources/js/Pages/Modules/Libraries/Funds/Modals/Create.vue`
- `resources/js/Pages/Modules/Libraries/Funds/Modals/TopUp.vue`
- `resources/js/Pages/Modules/Libraries/Funds/Modals/AdjustBalance.vue`
- `tests/Feature/Libraries/FundManagementTest.php`
- `tests/Feature/Expenses/ExpenseApprovalTest.php`

**Modify:**
- `routes/web.php` — add fund routes + restore expense approve route
- `app/Http/Controllers/Modules/ExpenseController.php` — restore `approve()` method
- `app/Services/Modules/ExpenseClass.php` — restore `approve()` + inactive fund guard in `save()`
- `app/Services/Modules/ReplenishmentService.php` — bulk-promote `recorded` expenses in `approve()`
- `resources/js/Shared/Layouts/Components/Menu.vue` — add Funds nav link
- `resources/js/Pages/Modules/Expenses/Index.vue` — add Approve button to expense rows

---

## Task 1: FundManagement — Failing Tests

Write all backend tests before writing any implementation code.

**Files:**
- Create: `tests/Feature/Libraries/FundManagementTest.php`

- [ ] **Step 1: Create test file**

```php
<?php

namespace Tests\Feature\Libraries;

use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\User;
use App\Services\Libraries\FundClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FundManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeFund(array $overrides = []): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create(array_merge([
            'name'          => 'Test Fund',
            'gl_code'       => 'PCF-' . (++$seq),
            'balance'       => 500.00,
            'weekly_budget' => null,
            'is_active'     => true,
            'created_by_id' => $this->user->id,
        ], $overrides));
    }

    // --- create ---

    public function test_fund_can_be_created(): void
    {
        $service = app(FundClass::class);
        $request = \Illuminate\Http\Request::create('/libraries/funds', 'POST', [
            'name'          => 'Main Fund',
            'gl_code'       => 'PCF-001',
            'weekly_budget' => 1000,
        ]);

        $result = $service->save($request, $this->user->id);

        $this->assertEquals('Main Fund', $result['data']->name);
        $this->assertEquals(0.0, $result['data']->balance);
        $this->assertDatabaseHas('petty_cash_funds', ['gl_code' => 'PCF-001', 'balance' => 0]);
    }

    // --- top-up ---

    public function test_top_up_increments_balance_and_records_transaction(): void
    {
        $fund    = $this->makeFund(['balance' => 100]);
        $service = app(FundClass::class);
        $request = \Illuminate\Http\Request::create('/libraries/funds/1/top-up', 'POST', [
            'amount'      => 500,
            'date'        => now()->toDateString(),
            'description' => 'Monthly replenishment',
        ]);

        $service->topUp($fund->id, $request);

        $this->assertEquals(600.0, $fund->fresh()->balance);
        $this->assertDatabaseHas('petty_cash_transactions', [
            'fund_id' => $fund->id,
            'type'    => 'replenishment',
            'amount'  => 500,
        ]);
    }

    // --- adjust balance ---

    public function test_adjust_balance_sets_balance_directly_without_transaction(): void
    {
        $fund    = $this->makeFund(['balance' => 100]);
        $service = app(FundClass::class);
        $request = \Illuminate\Http\Request::create('/libraries/funds/1/balance', 'PATCH', [
            'balance' => 250,
            'reason'  => 'Cash count correction',
        ]);

        $service->adjustBalance($fund->id, $request);

        $this->assertEquals(250.0, $fund->fresh()->balance);
        $this->assertEquals(0, PettyCashTransaction::where('fund_id', $fund->id)->count());
    }

    // --- toggle active ---

    public function test_toggle_active_deactivates_fund(): void
    {
        $fund    = $this->makeFund(['is_active' => true]);
        $service = app(FundClass::class);

        $service->toggleActive($fund->id, false);

        $this->assertFalse($fund->fresh()->is_active);
    }

    // --- inactive fund guard in ExpenseClass ---

    public function test_recording_expense_on_inactive_fund_throws_validation_exception(): void
    {
        $fund = $this->makeFund(['balance' => 1000, 'is_active' => false]);

        $request = \Illuminate\Http\Request::create('/expenses', 'POST', [
            'fund_id'      => $fund->id,
            'expense_type' => 'operational',
            'amount'       => '100',
            'expense_date' => now()->toDateString(),
            'status'       => 'pending',
        ]);

        $this->expectException(ValidationException::class);

        app(\App\Services\Modules\ExpenseClass::class)->save($request, $this->user->id);
    }
}
```

- [ ] **Step 2: Run tests — confirm all fail**

```bash
./vendor/bin/pest --filter=FundManagementTest 2>&1 | grep -E "PASS|FAIL|ERROR|Tests:"
```

Expected: 5 failed (classes not yet defined)

---

## Task 2: FundRequest + FundResource

**Files:**
- Create: `app/Http/Requests/Libraries/FundRequest.php`
- Create: `app/Http/Resources/Libraries/FundResource.php`

- [ ] **Step 1: Create FundRequest**

```php
<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class FundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->input('id');
        return [
            'name'          => 'required|string|max:255',
            'gl_code'       => 'required|string|max:50|unique:petty_cash_funds,gl_code' . ($id ? ",{$id}" : ''),
            'weekly_budget' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Fund name is required.',
            'gl_code.required'  => 'GL code is required.',
            'gl_code.unique'    => 'This GL code is already in use.',
        ];
    }
}
```

- [ ] **Step 2: Create FundResource**

```php
<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'gl_code'       => $this->gl_code,
            'balance'       => (float) $this->balance,
            'weekly_budget' => $this->weekly_budget ? (float) $this->weekly_budget : null,
            'is_active'     => (bool) $this->is_active,
            'created_at'    => $this->created_at,
        ];
    }
}
```

---

## Task 3: FundClass Service

**Files:**
- Create: `app/Services/Libraries/FundClass.php`

- [ ] **Step 1: Create FundClass**

```php
<?php

namespace App\Services\Libraries;

use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Http\Resources\Libraries\FundResource;
use Illuminate\Validation\ValidationException;

class FundClass
{
    public function lists($request)
    {
        return FundResource::collection(
            PettyCashFund::when($request->keyword, fn($q, $kw) => $q->where('name', 'LIKE', "%{$kw}%"))
                ->orderBy('name')
                ->paginate($request->count ?? 15)
        );
    }

    public function save($request, $userId = null)
    {
        $data = PettyCashFund::create([
            'name'          => $request->name,
            'gl_code'       => $request->gl_code,
            'balance'       => 0,
            'weekly_budget' => $request->weekly_budget ?? null,
            'is_active'     => true,
            'created_by_id' => $userId ?: auth()->id(),
        ]);

        return [
            'data'    => new FundResource($data),
            'message' => 'Fund created successfully!',
            'info'    => "Petty cash fund '{$data->name}' has been created.",
        ];
    }

    public function update($request)
    {
        $data = PettyCashFund::findOrFail($request->id);
        $data->update([
            'name'          => $request->name,
            'gl_code'       => $request->gl_code,
            'weekly_budget' => $request->weekly_budget ?? null,
        ]);

        return [
            'data'    => new FundResource($data->fresh()),
            'message' => 'Fund updated successfully!',
            'info'    => "Petty cash fund '{$data->name}' has been updated.",
        ];
    }

    public function topUp($id, $request)
    {
        $amount = (float) $request->amount;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Top-up amount must be greater than zero.',
            ]);
        }

        $fund = PettyCashFund::findOrFail($id);

        PettyCashTransaction::create([
            'fund_id'          => $fund->id,
            'type'             => 'replenishment',
            'amount'           => $amount,
            'transaction_date' => $request->date ?? now()->toDateString(),
            'description'      => $request->description ?? null,
            'transaction_no'   => 'TU-' . strtoupper(uniqid()),
            'created_by_id'    => auth()->id(),
        ]);

        $fund->increment('balance', $amount);

        return [
            'data'    => new FundResource($fund->fresh()),
            'message' => 'Fund topped up successfully!',
            'info'    => "₱" . number_format($amount, 2) . " added to '{$fund->name}'.",
        ];
    }

    public function adjustBalance($id, $request)
    {
        $fund = PettyCashFund::findOrFail($id);
        $fund->update(['balance' => (float) $request->balance]);

        return [
            'data'    => new FundResource($fund->fresh()),
            'message' => 'Balance adjusted successfully!',
            'info'    => "Balance set to ₱" . number_format($fund->balance, 2) . ".",
        ];
    }

    public function toggleActive($id, bool $isActive)
    {
        $fund = PettyCashFund::findOrFail($id);
        $fund->update(['is_active' => $isActive]);

        return [
            'data'    => new FundResource($fund->fresh()),
            'message' => $isActive ? 'Fund activated.' : 'Fund deactivated.',
            'info'    => "Fund '{$fund->name}' is now " . ($isActive ? 'active' : 'inactive') . ".",
        ];
    }
}
```

- [ ] **Step 2: Run fund service tests — confirm they pass**

```bash
./vendor/bin/pest --filter=FundManagementTest 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: 4 of 5 pass. The 5th (inactive fund guard) still fails — implemented in Task 5.

---

## Task 4: Inactive Fund Guard in ExpenseClass

**Files:**
- Modify: `app/Services/Modules/ExpenseClass.php` (the `save()` method, after the existing balance check)

- [ ] **Step 1: Add inactive fund guard**

Open `app/Services/Modules/ExpenseClass.php`. In `save()`, update the fund block (currently around lines 107–116) to add the inactive check:

```php
    public function save($request, $userId = null)
    {
        $amount = (float) $request->amount;

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

            $fund->decrement('balance', $amount);
        }

        $data = Expense::create([
            'fund_id'      => $request->fund_id ?? null,
            'expense_type' => $request->expense_type,
            'amount'       => $amount,
            'expense_date' => $request->expense_date,
            'description'  => $request->description,
            'receipt_path' => $request->receipt_path ?? null,
            'status'       => $request->status ?? 'recorded',
            'added_by_id'  => $userId ?: auth()->id(),
        ]);

        return [
            'data'    => new ExpenseResource($data),
            'message' => 'Expense saved successfully!',
            'info'    => "You've successfully saved the expense",
        ];
    }
```

- [ ] **Step 2: Run all fund tests — confirm all 5 pass**

```bash
./vendor/bin/pest --filter=FundManagementTest 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: 5 passed

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/Libraries/FundRequest.php \
        app/Http/Resources/Libraries/FundResource.php \
        app/Services/Libraries/FundClass.php \
        app/Services/Modules/ExpenseClass.php \
        tests/Feature/Libraries/FundManagementTest.php
git commit -m "feat: add FundClass service with inactive fund guard"
```

---

## Task 5: FundController + Routes

**Files:**
- Create: `app/Http/Controllers/Libraries/FundController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create FundController**

```php
<?php

namespace App\Http\Controllers\Libraries;

use Illuminate\Http\Request;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Libraries\FundClass;
use App\Http\Requests\Libraries\FundRequest;

class FundController extends Controller
{
    use HandlesTransaction;

    public function __construct(protected FundClass $fund) {}

    public function index(Request $request)
    {
        if ($request->option === 'lists') {
            return $this->fund->lists($request);
        }

        return inertia('Modules/Libraries/Funds/Index');
    }

    public function store(FundRequest $request)
    {
        $result = $this->handleTransaction(fn() => $this->fund->save($request, auth()->id()));

        return back()->with([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
        ]);
    }

    public function update(FundRequest $request, $id)
    {
        $request->merge(['id' => $id]);
        $result = $this->handleTransaction(fn() => $this->fund->update($request));

        return back()->with([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
        ]);
    }

    public function topUp(Request $request, $id)
    {
        $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'date'        => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        $result = $this->handleTransaction(fn() => $this->fund->topUp($id, $request));

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
            'data'    => $result['data'],
        ]);
    }

    public function adjustBalance(Request $request, $id)
    {
        $request->validate([
            'balance' => 'required|numeric|min:0',
            'reason'  => 'required|string|max:500',
        ]);

        $result = $this->handleTransaction(fn() => $this->fund->adjustBalance($id, $request));

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
            'data'    => $result['data'],
        ]);
    }

    public function toggleActive(Request $request, $id)
    {
        $request->validate(['is_active' => 'required|boolean']);

        $result = $this->handleTransaction(fn() => $this->fund->toggleActive($id, (bool) $request->is_active));

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
            'data'    => $result['data'],
        ]);
    }
}
```

- [ ] **Step 2: Add routes to `routes/web.php`**

Find the existing `role:Administrator` Libraries group (around line 53) and add the fund routes after the existing entries, before the closing `});`:

```php
        Route::resource('/libraries/funds', App\Http\Controllers\Libraries\FundController::class)->only(['index', 'store', 'update']);
        Route::post('/libraries/funds/{id}/top-up', [App\Http\Controllers\Libraries\FundController::class, 'topUp']);
        Route::patch('/libraries/funds/{id}/balance', [App\Http\Controllers\Libraries\FundController::class, 'adjustBalance']);
        Route::patch('/libraries/funds/{id}/toggle-active', [App\Http\Controllers\Libraries\FundController::class, 'toggleActive']);
```

Also, restore the expense approve route inside the `role:Administrator,Top Management,Area Business Manager,Super Admin` group (where the old approve/release routes were):

```php
        Route::patch('/expenses/{id}/approve', [App\Http\Controllers\Modules\ExpenseController::class, 'approve']);
```

- [ ] **Step 3: Verify routes registered**

```bash
php artisan route:list --path=libraries/funds 2>&1 | grep -v "vendor"
php artisan route:list --path=expenses 2>&1 | grep approve
```

Expected: fund routes listed, `expenses/{id}/approve` listed with `PATCH` method.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Libraries/FundController.php routes/web.php
git commit -m "feat: add FundController and fund routes, restore expense approve route"
```

---

## Task 6: Expense Approval — Backend

**Files:**
- Create: `tests/Feature/Expenses/ExpenseApprovalTest.php`
- Modify: `app/Http/Controllers/Modules/ExpenseController.php`
- Modify: `app/Services/Modules/ExpenseClass.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\Modules\ExpenseClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseApprovalTest extends TestCase
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
            'name'    => 'Fund',
            'gl_code' => 'PCF-AP-' . (++$seq),
            'balance' => 1000,
        ]);
    }

    private function makeExpense(string $status = 'recorded'): Expense
    {
        return Expense::create([
            'fund_id'      => $this->makeFund()->id,
            'expense_type' => 'operational',
            'amount'       => 100,
            'expense_date' => now()->toDateString(),
            'status'       => $status,
            'added_by_id'  => $this->user->id,
        ]);
    }

    public function test_recorded_expense_can_be_approved(): void
    {
        $expense = $this->makeExpense('recorded');
        $service = app(ExpenseClass::class);

        $result = $service->approve($expense->id);

        $this->assertEquals('approved', $expense->fresh()->status);
        $this->assertEquals('success', $result['status']);
    }

    public function test_non_recorded_expense_returns_soft_error(): void
    {
        $expense = $this->makeExpense('submitted');
        $service = app(ExpenseClass::class);

        $result = $service->approve($expense->id);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('submitted', $expense->fresh()->status);
    }

    public function test_approve_endpoint_returns_json_success(): void
    {
        $expense = $this->makeExpense('recorded');

        $this->withoutMiddleware()
            ->patch("/expenses/{$expense->id}/approve")
            ->assertJson(['status' => 'success']);

        $this->assertEquals('approved', $expense->fresh()->status);
    }
}
```

- [ ] **Step 2: Run — confirm all fail**

```bash
./vendor/bin/pest --filter=ExpenseApprovalTest 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: 3 failed

- [ ] **Step 3: Restore `approve()` in `ExpenseClass`**

Add this method to `app/Services/Modules/ExpenseClass.php`, before `release()`:

```php
    public function approve($id): array
    {
        $data = Expense::findOrFail($id);

        if ($data->status !== 'recorded') {
            return [
                'data'    => new ExpenseResource($data->fresh(['added_by'])),
                'message' => 'Only recorded expenses can be approved.',
                'status'  => 'error',
            ];
        }

        $data->update(['status' => 'approved']);

        return [
            'data'    => new ExpenseResource($data->fresh(['added_by'])),
            'message' => 'Expense approved successfully!',
            'info'    => "You've successfully approved the expense.",
            'status'  => 'success',
        ];
    }
```

- [ ] **Step 4: Restore `approve()` in `ExpenseController`**

Add this method to `app/Http/Controllers/Modules/ExpenseController.php`:

```php
    public function approve($id)
    {
        $result = $this->handleTransaction(fn() => $this->expense->approve($id));

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
            'data'    => $result['data'] ?? null,
        ]);
    }
```

- [ ] **Step 5: Run — confirm all 3 pass**

```bash
./vendor/bin/pest --filter=ExpenseApprovalTest 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: 3 passed

- [ ] **Step 6: Commit**

```bash
git add app/Services/Modules/ExpenseClass.php \
        app/Http/Controllers/Modules/ExpenseController.php \
        tests/Feature/Expenses/ExpenseApprovalTest.php
git commit -m "feat: restore expense approve endpoint and service method"
```

---

## Task 7: Replenishment Bulk Promotion

**Files:**
- Modify: `app/Services/Modules/ReplenishmentService.php`

- [ ] **Step 1: Update `approve()` in ReplenishmentService**

Find the `approve()` method (around line 101). The current line that updates expense status is:

```php
// current (only handles 'submitted' expenses):
$replenishment->expenses()
    ->update(['status' => 'reimbursed']);
```

Replace that block with:

```php
        // Promote submitted expenses to reimbursed
        $replenishment->expenses()
            ->where('status', 'submitted')
            ->update(['status' => 'reimbursed']);

        // Bulk-promote any recorded expenses still linked to this request
        $replenishment->expenses()
            ->where('status', 'recorded')
            ->update(['status' => 'reimbursed']);
```

- [ ] **Step 2: Run full test suite — confirm no regressions**

```bash
./vendor/bin/pest --filter="FundManagementTest|ExpenseApprovalTest|DamagedItemsReturnTest|ExpenseModuleTest" 2>&1 | grep -E "PASS|FAIL|Tests:"
```

Expected: all pass

- [ ] **Step 3: Commit**

```bash
git add app/Services/Modules/ReplenishmentService.php
git commit -m "feat: bulk-promote recorded expenses to reimbursed on replenishment approval"
```

---

## Task 8: Fund Management — Frontend (Index + Modals)

**Files:**
- Create: `resources/js/Pages/Modules/Libraries/Funds/Index.vue`
- Create: `resources/js/Pages/Modules/Libraries/Funds/Modals/Create.vue`
- Create: `resources/js/Pages/Modules/Libraries/Funds/Modals/TopUp.vue`
- Create: `resources/js/Pages/Modules/Libraries/Funds/Modals/AdjustBalance.vue`

- [ ] **Step 1: Create `Index.vue`**

```vue
<template>
<Head title="Petty Cash Funds"/>
    <PageHeader title="Fund Management" pageTitle="Libraries" />
    <BRow>
        <div class="col-md-12">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon">
                                <i class="ri-safe-line"></i>
                            </div>
                            <div>
                                <h4 class="header-title mb-1">Petty Cash Funds</h4>
                                <p class="header-subtitle mb-0">Manage petty cash funds, balances, and top-ups</p>
                            </div>
                        </div>
                        <button class="create-btn" @click="openCreate">
                            <i class="ri-add-line"></i>
                            <span>Add Fund</span>
                        </button>
                    </div>
                </div>

                <div class="library-card-body">
                    <div class="search-section">
                        <div class="search-wrapper">
                            <i class="ri-search-line search-icon"></i>
                            <input type="text" v-model="filter.keyword" placeholder="Search funds..." class="search-input">
                        </div>
                    </div>

                    <div class="table-section">
                        <div class="table-responsive">
                            <table class="table align-middle table-centered mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>GL Code</th>
                                        <th>Balance</th>
                                        <th>Weekly Budget</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(list, index) in lists" :key="list.id"
                                        @click="selectRow(index)"
                                        :class="{
                                            'bg-info-subtle': index === selectedRow,
                                            'bg-danger-subtle': !list.is_active && index !== selectedRow
                                        }">
                                        <td>{{ index + 1 }}</td>
                                        <td>{{ list.name }}</td>
                                        <td><code>{{ list.gl_code }}</code></td>
                                        <td>₱{{ Number(list.balance).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
                                        <td>{{ list.weekly_budget ? '₱' + Number(list.weekly_budget).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—' }}</td>
                                        <td>
                                            <span class="badge" :class="list.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                                                {{ list.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button @click.stop="openTopUp(list)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Top-up">
                                                    <i class="ri-add-circle-line"></i>
                                                </button>
                                                <button @click.stop="openAdjust(list)" class="action-btn" style="color:#6c757d;background:#f8f9fa;border:1px solid #dee2e6" v-b-tooltip.hover title="Adjust Balance">
                                                    <i class="ri-scales-line"></i>
                                                </button>
                                                <button @click.stop="openEdit(list)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Edit">
                                                    <i class="ri-pencil-line"></i>
                                                </button>
                                                <button @click.stop="toggleActive(list)" class="action-btn" :class="list.is_active ? 'action-btn-delete' : 'action-btn-edit'" v-b-tooltip.hover :title="list.is_active ? 'Deactivate' : 'Activate'">
                                                    <i :class="list.is_active ? 'ri-forbid-line' : 'ri-check-line'"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pagination-section">
                        <Pagination v-if="meta" @fetch="fetch" :lists="lists.length" :links="links" :pagination="meta" />
                    </div>
                </div>
            </div>
        </div>
    </BRow>

    <Create ref="create" @add="fetch()" @update="fetch()" />
    <TopUp ref="topup" @done="fetch()" />
    <AdjustBalance ref="adjust" @done="fetch()" />
</template>

<script>
import _ from 'lodash';
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Pagination from '@/Shared/Components/Pagination.vue';
import Create from './Modals/Create.vue';
import TopUp from './Modals/TopUp.vue';
import AdjustBalance from './Modals/AdjustBalance.vue';
import Swal from 'sweetalert2';

export default {
    components: { PageHeader, Pagination, Create, TopUp, AdjustBalance },
    data() {
        return {
            lists: [],
            meta: {},
            links: {},
            filter: { keyword: null },
            selectedRow: null,
        };
    },
    watch: {
        'filter.keyword'(val) { this.checkSearchStr(val); },
    },
    created() { this.fetch(); },
    methods: {
        checkSearchStr: _.debounce(function() { this.fetch(); }, 300),
        fetch(page_url) {
            page_url = page_url || '/libraries/funds';
            axios.get(page_url, { params: { option: 'lists', keyword: this.filter.keyword } })
                .then(res => {
                    this.lists = res.data.data;
                    this.meta  = res.data.meta;
                    this.links = res.data.links;
                });
        },
        selectRow(index) { this.selectedRow = index; },
        openCreate() { this.$refs.create.show(); },
        openEdit(fund) { this.$refs.create.edit(fund); },
        openTopUp(fund) { this.$refs.topup.show(fund); },
        openAdjust(fund) { this.$refs.adjust.show(fund); },
        toggleActive(fund) {
            const action = fund.is_active ? 'deactivate' : 'activate';
            Swal.fire({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} fund?`,
                text: `Are you sure you want to ${action} "${fund.name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes',
            }).then(result => {
                if (!result.isConfirmed) return;
                axios.patch(`/libraries/funds/${fund.id}/toggle-active`, { is_active: !fund.is_active })
                    .then(() => this.fetch())
                    .catch(err => Swal.fire('Error', err.response?.data?.message || 'Failed', 'error'));
            });
        },
    },
};
</script>
```

- [ ] **Step 2: Create `Modals/Create.vue`**

```vue
<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <div class="modal-header-icon"><i class="ri-safe-line"></i></div>
                <div>
                    <h2>{{ editable ? 'Edit Fund' : 'New Petty Cash Fund' }}</h2>
                    <p class="modal-header-kicker">{{ editable ? 'Update fund details' : 'Create a new petty cash fund' }}</p>
                </div>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                    <div class="form-group mb-3">
                        <label class="form-label">Fund Name <span class="text-danger">*</span></label>
                        <input type="text" v-model="form.name" class="form-control" :class="{ 'is-invalid': form.errors.name }" placeholder="e.g. Main Office Fund">
                        <div class="invalid-feedback" v-if="form.errors.name">{{ form.errors.name }}</div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">GL Code <span class="text-danger">*</span></label>
                        <input type="text" v-model="form.gl_code" class="form-control" :class="{ 'is-invalid': form.errors.gl_code }" placeholder="e.g. PCF-001">
                        <div class="invalid-feedback" v-if="form.errors.gl_code">{{ form.errors.gl_code }}</div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Weekly Budget <span class="text-muted">(optional)</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" v-model="form.weekly_budget" class="form-control" placeholder="0.00" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Fund saved successfully!</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide"><i class="ri-close-line"></i> Cancel</button>
                <button type="button" class="btn btn-save" :disabled="form.processing" @click="submit">
                    <i class="ri-save-line" v-if="!form.processing"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ form.processing ? 'Saving...' : 'Save Fund' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
    emits: ['add', 'update'],
    data() {
        return {
            form: useForm({ id: null, name: null, gl_code: null, weekly_budget: null }),
            showModal: false,
            editable: false,
            saveSuccess: false,
        };
    },
    methods: {
        show() {
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(fund) {
            this.form.id            = fund.id;
            this.form.name          = fund.name;
            this.form.gl_code       = fund.gl_code;
            this.form.weekly_budget = fund.weekly_budget;
            this.editable  = true;
            this.saveSuccess = false;
            this.showModal  = true;
        },
        hide() { this.showModal = false; },
        submit() {
            if (this.editable) {
                this.form.put(`/libraries/funds/${this.form.id}`, {
                    onSuccess: () => { this.saveSuccess = true; this.$emit('update'); setTimeout(() => this.hide(), 1200); },
                });
            } else {
                this.form.post('/libraries/funds', {
                    onSuccess: () => { this.saveSuccess = true; this.$emit('add'); setTimeout(() => this.hide(), 1200); },
                });
            }
        },
    },
};
</script>
```

- [ ] **Step 3: Create `Modals/TopUp.vue`**

```vue
<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" style="max-width:480px" @click.stop>
            <div class="modal-header">
                <div class="modal-header-icon"><i class="ri-add-circle-line"></i></div>
                <div>
                    <h2>Top-up Fund</h2>
                    <p class="modal-header-kicker">{{ fund ? fund.name : '' }}</p>
                </div>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 p-3 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
                    <div class="text-muted fs-12">Current Balance</div>
                    <div class="fw-bold fs-18">₱{{ fund ? Number(fund.balance).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '0.00' }}</div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" v-model="form.amount" class="form-control" :class="{ 'is-invalid': errors.amount }" placeholder="0.00" min="0.01" step="0.01">
                    </div>
                    <div class="invalid-feedback d-block" v-if="errors.amount">{{ errors.amount[0] }}</div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" v-model="form.date" class="form-control" :class="{ 'is-invalid': errors.date }">
                    <div class="invalid-feedback" v-if="errors.date">{{ errors.date[0] }}</div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" v-model="form.description" class="form-control" placeholder="e.g. Monthly replenishment">
                </div>
                <div class="alert alert-success" v-if="success">{{ success }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide"><i class="ri-close-line"></i> Cancel</button>
                <button type="button" class="btn btn-save" :disabled="saving" @click="submit">
                    <i class="ri-add-circle-line" v-if="!saving"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ saving ? 'Processing...' : 'Top-up' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    emits: ['done'],
    data() {
        return {
            fund: null,
            form: { amount: null, date: new Date().toISOString().split('T')[0], description: null },
            errors: {},
            saving: false,
            success: null,
            showModal: false,
        };
    },
    methods: {
        show(fund) {
            this.fund    = fund;
            this.form    = { amount: null, date: new Date().toISOString().split('T')[0], description: null };
            this.errors  = {};
            this.success = null;
            this.showModal = true;
        },
        hide() { this.showModal = false; },
        submit() {
            this.saving = true;
            this.errors = {};
            axios.post(`/libraries/funds/${this.fund.id}/top-up`, this.form)
                .then(res => {
                    this.success = res.data.message;
                    this.$emit('done');
                    setTimeout(() => this.hide(), 1200);
                })
                .catch(err => { this.errors = err.response?.data?.errors || {}; })
                .finally(() => { this.saving = false; });
        },
    },
};
</script>
```

- [ ] **Step 4: Create `Modals/AdjustBalance.vue`**

```vue
<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" style="max-width:480px" @click.stop>
            <div class="modal-header">
                <div class="modal-header-icon"><i class="ri-scales-line"></i></div>
                <div>
                    <h2>Adjust Balance</h2>
                    <p class="modal-header-kicker">{{ fund ? fund.name : '' }} — correction only</p>
                </div>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning fs-12 mb-3">
                    <i class="ri-alert-line me-1"></i>
                    This directly overrides the fund balance with no transaction record. Use only for cash count corrections.
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">New Balance <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" v-model="form.balance" class="form-control" :class="{ 'is-invalid': errors.balance }" placeholder="0.00" min="0" step="0.01">
                    </div>
                    <div class="invalid-feedback d-block" v-if="errors.balance">{{ errors.balance[0] }}</div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                    <input type="text" v-model="form.reason" class="form-control" :class="{ 'is-invalid': errors.reason }" placeholder="e.g. Cash count on 2026-05-29 showed ₱250">
                    <div class="invalid-feedback" v-if="errors.reason">{{ errors.reason[0] }}</div>
                </div>
                <div class="alert alert-success" v-if="success">{{ success }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide"><i class="ri-close-line"></i> Cancel</button>
                <button type="button" class="btn btn-save" :disabled="saving" @click="submit">
                    <i class="ri-scales-line" v-if="!saving"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ saving ? 'Saving...' : 'Apply Adjustment' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    emits: ['done'],
    data() {
        return {
            fund: null,
            form: { balance: null, reason: null },
            errors: {},
            saving: false,
            success: null,
            showModal: false,
        };
    },
    methods: {
        show(fund) {
            this.fund    = fund;
            this.form    = { balance: fund.balance, reason: null };
            this.errors  = {};
            this.success = null;
            this.showModal = true;
        },
        hide() { this.showModal = false; },
        submit() {
            this.saving = true;
            this.errors = {};
            axios.patch(`/libraries/funds/${this.fund.id}/balance`, this.form)
                .then(res => {
                    this.success = res.data.message;
                    this.$emit('done');
                    setTimeout(() => this.hide(), 1200);
                })
                .catch(err => { this.errors = err.response?.data?.errors || {}; })
                .finally(() => { this.saving = false; });
        },
    },
};
</script>
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Modules/Libraries/Funds/
git commit -m "feat: add Fund management Vue pages (Index, Create, TopUp, AdjustBalance modals)"
```

---

## Task 9: Menu Nav Link

**Files:**
- Modify: `resources/js/Shared/Layouts/Components/Menu.vue`

- [ ] **Step 1: Add Funds link to Libraries submenu**

In `Menu.vue`, find the last `</li>` inside the Libraries submenu (currently after the Positions entry around line 192). Insert the new item before the closing `</ul>`:

```html
                            <li class="nav-item submenu-item">
                                <Link href="/libraries/funds" class="nav-link submenu-link"
                                    :class="{ 'active': $page.url === '/libraries/funds' }" data-key="t-basic">
                                <span class="submenu-text">Petty Cash Funds</span>
                                </Link>
                            </li>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Shared/Layouts/Components/Menu.vue
git commit -m "feat: add Petty Cash Funds link to Libraries nav menu"
```

---

## Task 10: Expense Approve Button — Frontend

**Files:**
- Modify: `resources/js/Pages/Modules/Expenses/Index.vue`

- [ ] **Step 1: Add approve button to expense table rows**

In `Index.vue`, find the section that renders the expense row action buttons inside the `<tbody>` (look for the Edit and Delete buttons that are conditionally shown). Add the Approve button before the Edit button:

```html
<!-- Approve button — visible when status is 'recorded' and user is admin/manager -->
<button
    v-if="list.status === 'recorded' && canApprove"
    @click.stop="approveExpense(list)"
    class="action-icon-btn approve"
    title="Approve"
>
    <i class="ri-check-double-line"></i>
</button>
```

- [ ] **Step 2: Add `canApprove` computed and `approveExpense` method**

In the component's `computed` section (or add one if it doesn't exist), add:

```js
computed: {
    canApprove() {
        const roles = this.$page?.props?.roles || [];
        return ['Administrator', 'Top Management', 'Area Business Manager', 'Super Admin']
            .some(r => roles.includes(r));
    },
},
```

In the `methods` section, add:

```js
        approveExpense(expense) {
            axios.patch(`/expenses/${expense.id}/approve`)
                .then(res => {
                    if (res.data.status === 'error') {
                        this.$swal?.fire?.('Notice', res.data.message, 'warning');
                        return;
                    }
                    const idx = this.lists.findIndex(e => e.id === expense.id);
                    if (idx !== -1) this.lists[idx].status = 'approved';
                })
                .catch(() => {
                    this.$swal?.fire?.('Error', 'Failed to approve expense.', 'error');
                });
        },
```

- [ ] **Step 3: Add the same Approve button in the expanded row detail card**

Find the `collapse-actions` section (or the expanded row buttons section). Add the Approve button there too, following the same `v-if` condition:

```html
<button
    v-if="list.status === 'recorded' && canApprove"
    @click="approveExpense(list)"
    class="system-action-btn system-action-success"
>
    <i class="ri-check-double-line me-1"></i>
    Approve Expense
</button>
```

- [ ] **Step 4: Build assets and verify**

```bash
npm run build 2>&1 | tail -5
```

Expected: Build completes with no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Modules/Expenses/Index.vue
git commit -m "feat: add Approve button to expense list rows"
```

---

## Task 11: Final Verification

- [ ] **Step 1: Run the full test suite**

```bash
./vendor/bin/pest 2>&1 | grep -E "PASS|FAIL|Tests:" | tail -10
```

Expected: `FundManagementTest` (5 passed), `ExpenseApprovalTest` (3 passed), `ExpenseModuleTest` (3 passed), `DamagedItemsReturnTest` (2 passed). The pre-existing 8 Auth/Profile failures remain — those are unrelated.

- [ ] **Step 2: Manual smoke test (if dev server available)**

1. Navigate to `/libraries/funds` — page loads, table visible
2. Click "Add Fund" — modal opens, create a fund with name + GL code
3. Click the Top-up button — modal opens, enter amount + date, submit — balance increases
4. Click the Adjust Balance button — set balance directly, confirm change
5. Click the toggle button — fund deactivates (row turns red), reactivate
6. Navigate to `/expenses` — for a `recorded` expense, Approve button is visible for admin users
7. Click Approve — status changes to `approved` inline, no page reload

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "feat: complete Group 1 - fund management and expense approval"
```
