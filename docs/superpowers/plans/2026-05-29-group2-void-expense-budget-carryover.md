# Group 2: Void Expense & Budget Carryover — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `voided` expense status that restores fund balance, and an artisan command that rolls unused monthly budgets forward automatically.

**Architecture:** Void follows the same `approve()` pattern already in `ExpenseClass` — soft error guard + status update + side effect (balance restore). Budget carryover is a standalone artisan command registered in `routes/console.php`, mirroring the existing `MarkOverdueInvoices` and `credit:monthly` commands. Both features are independent and can be implemented in sequence.

**Tech Stack:** Laravel 11, Vue 3 (Options API), Pest (tests), Axios (frontend HTTP), Carbon (date math in command)

**Spec:** `docs/superpowers/specs/2026-05-29-group2-void-expense-budget-carryover-design.md`

---

## File Map

**Create:**
- `tests/Feature/Expenses/ExpenseVoidTest.php`
- `tests/Feature/Expenses/BudgetCarryoverTest.php`
- `app/Console/Commands/CarryBudgetCommand.php`
- `database/migrations/2026_05_29_000002_add_voided_status_to_expenses.php`

**Modify:**
- `app/Http/Requests/Modules/ExpenseRequest.php` — add `voided` to status `in:` rule
- `app/Services/Modules/ExpenseClass.php` — add `void($id)` method
- `app/Http/Controllers/Modules/ExpenseController.php` — add `void($id)` method
- `routes/web.php` — add `PATCH /expenses/{id}/void` route
- `routes/console.php` — register `expense:carry-budget` monthly schedule
- `resources/js/Pages/Modules/Expenses/Index.vue` — add Void button + `canVoid` computed + `voidExpense()` method

---

## Task 1: Void Expense — Failing Tests

**Files:**
- Create: `tests/Feature/Expenses/ExpenseVoidTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\Modules\ExpenseClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseVoidTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeFund(float $balance = 1000.0): PettyCashFund
    {
        static $seq = 0;
        return PettyCashFund::create([
            'name'    => 'Fund',
            'gl_code' => 'PCF-V-' . (++$seq),
            'balance' => $balance,
        ]);
    }

    private function makeExpense(string $status, ?PettyCashFund $fund = null): Expense
    {
        return Expense::create([
            'fund_id'      => $fund?->id,
            'expense_type' => 'operational',
            'amount'       => 100,
            'expense_date' => now()->toDateString(),
            'status'       => $status,
            'added_by_id'  => $this->user->id,
        ]);
    }

    public function test_recorded_expense_can_be_voided_and_balance_restored(): void
    {
        $fund    = $this->makeFund(900);
        $expense = $this->makeExpense('recorded', $fund);
        $service = app(ExpenseClass::class);

        $result = $service->void($expense->id);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('voided', $expense->fresh()->status);
        $this->assertEquals(1000.0, $fund->fresh()->balance);
    }

    public function test_approved_expense_can_be_voided(): void
    {
        $fund    = $this->makeFund(900);
        $expense = $this->makeExpense('approved', $fund);
        $service = app(ExpenseClass::class);

        $result = $service->void($expense->id);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('voided', $expense->fresh()->status);
        $this->assertEquals(1000.0, $fund->fresh()->balance);
    }

    public function test_released_expense_returns_soft_error(): void
    {
        $expense = $this->makeExpense('released');
        $service = app(ExpenseClass::class);

        $result = $service->void($expense->id);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('released', $expense->fresh()->status);
    }

    public function test_void_without_fund_succeeds_without_error(): void
    {
        $expense = $this->makeExpense('recorded', null);
        $service = app(ExpenseClass::class);

        $result = $service->void($expense->id);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('voided', $expense->fresh()->status);
    }

    public function test_void_endpoint_returns_json_success(): void
    {
        $expense = $this->makeExpense('recorded');

        $this->withoutMiddleware()
            ->patch("/expenses/{$expense->id}/void")
            ->assertJson(['status' => 'success']);

        $this->assertEquals('voided', $expense->fresh()->status);
    }
}
```

- [ ] **Step 2: Run — confirm all 5 fail**

```bash
./vendor/bin/pest --filter=ExpenseVoidTest 2>&1 | tail -10
```

Expected: 5 failed (`void` method does not exist on `ExpenseClass`)

---

## Task 2: Void Expense — Backend

**Files:**
- Create: `database/migrations/2026_05_29_000002_add_voided_status_to_expenses.php`
- Modify: `app/Http/Requests/Modules/ExpenseRequest.php`
- Modify: `app/Services/Modules/ExpenseClass.php`
- Modify: `app/Http/Controllers/Modules/ExpenseController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create migration to add `voided` to the MySQL ENUM**

```bash
php artisan make:migration add_voided_status_to_expenses
```

Open the generated file (in `database/migrations/`) and replace its content with:

```php
<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (\DB::connection()->getDriverName() !== 'sqlite') {
            \DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending','approved','rejected','released','recorded','submitted','reimbursed','voided') DEFAULT 'recorded'");
        }
    }

    public function down(): void
    {
        if (\DB::connection()->getDriverName() !== 'sqlite') {
            \DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending','approved','rejected','released','recorded','submitted','reimbursed') DEFAULT 'recorded'");
        }
    }
};
```

Run it:

```bash
php artisan migrate
```

- [ ] **Step 2: Add `voided` to `ExpenseRequest` status rule**

In `app/Http/Requests/Modules/ExpenseRequest.php`, change:

```php
'status' => 'nullable|in:pending,approved,rejected,released,recorded,submitted,reimbursed',
```

to:

```php
'status' => 'nullable|in:pending,approved,rejected,released,recorded,submitted,reimbursed,voided',
```

- [ ] **Step 3: Add `void()` to `ExpenseClass`**

In `app/Services/Modules/ExpenseClass.php`, add this method after `approve()` (around line 235):

```php
    public function void($id): array
    {
        $data = Expense::findOrFail($id);

        if (! in_array($data->status, ['recorded', 'approved', 'submitted'])) {
            return [
                'data'    => new ExpenseResource($data->fresh(['added_by'])),
                'message' => 'Only recorded, approved, or submitted expenses can be voided.',
                'status'  => 'error',
            ];
        }

        if ($data->fund_id) {
            PettyCashFund::where('id', $data->fund_id)->increment('balance', (float) $data->amount);
        }

        $data->update(['status' => 'voided']);

        return [
            'data'    => new ExpenseResource($data->fresh(['added_by'])),
            'message' => 'Expense voided successfully!',
            'info'    => "You've successfully voided the expense.",
            'status'  => 'success',
        ];
    }
```

- [ ] **Step 4: Add `void()` to `ExpenseController`**

In `app/Http/Controllers/Modules/ExpenseController.php`, add after `approve()` (around line 123):

```php
    public function void($id)
    {
        $result = $this->handleTransaction(fn() => $this->expense->void($id));

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
            'data'    => $result['data'] ?? null,
        ]);
    }
```

- [ ] **Step 5: Add route to `routes/web.php`**

In `routes/web.php`, find the line that registers the approve route:

```php
        Route::patch('/expenses/{id}/approve', [App\Http\Controllers\Modules\ExpenseController::class, 'approve']);
```

Add immediately after it:

```php
        Route::patch('/expenses/{id}/void', [App\Http\Controllers\Modules\ExpenseController::class, 'void']);
```

- [ ] **Step 6: Run — confirm all 5 tests pass**

```bash
./vendor/bin/pest --filter=ExpenseVoidTest 2>&1 | tail -10
```

Expected: 5 passed

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_05_29_000002_add_voided_status_to_expenses.php \
        app/Http/Requests/Modules/ExpenseRequest.php \
        app/Services/Modules/ExpenseClass.php \
        app/Http/Controllers/Modules/ExpenseController.php \
        routes/web.php \
        tests/Feature/Expenses/ExpenseVoidTest.php
git commit -m "feat: add void expense endpoint with fund balance restore"
```

---

## Task 3: Budget Carryover — Failing Tests

**Files:**
- Create: `tests/Feature/Expenses/BudgetCarryoverTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCarryoverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeBudget(string $type, int $month, int $year, float $amount): ExpenseBudget
    {
        return ExpenseBudget::create([
            'expense_type'  => $type,
            'month'         => $month,
            'year'          => $year,
            'amount'        => $amount,
            'created_by_id' => $this->user->id,
        ]);
    }

    private function makeReleasedExpense(string $type, int $month, int $year, float $amount): Expense
    {
        return Expense::create([
            'expense_type' => $type,
            'amount'       => $amount,
            'expense_date' => date('Y-m-d', mktime(0, 0, 0, $month, 15, $year)),
            'status'       => 'released',
            'added_by_id'  => $this->user->id,
        ]);
    }

    public function test_unused_budget_carries_over_to_next_month(): void
    {
        $this->makeBudget('operational', 4, 2026, 10000);
        $this->makeReleasedExpense('operational', 4, 2026, 7000);

        $this->artisan('expense:carry-budget', ['--month' => 5, '--year' => 2026])
             ->assertExitCode(0);

        $this->assertDatabaseHas('expense_budgets', [
            'expense_type' => 'operational',
            'month'        => 5,
            'year'         => 2026,
            'amount'       => 3000,
        ]);
    }

    public function test_no_carryover_when_budget_fully_used(): void
    {
        $this->makeBudget('utilities', 4, 2026, 5000);
        $this->makeReleasedExpense('utilities', 4, 2026, 5000);

        $this->artisan('expense:carry-budget', ['--month' => 5, '--year' => 2026])
             ->assertExitCode(0);

        $this->assertDatabaseMissing('expense_budgets', [
            'expense_type' => 'utilities',
            'month'        => 5,
            'year'         => 2026,
        ]);
    }

    public function test_carryover_skips_existing_current_month_budget(): void
    {
        $this->makeBudget('supplies', 4, 2026, 8000);
        $this->makeBudget('supplies', 5, 2026, 2000); // pre-set

        $this->artisan('expense:carry-budget', ['--month' => 5, '--year' => 2026])
             ->assertExitCode(0);

        // Amount must remain 2000, not overwritten with carryover
        $this->assertDatabaseHas('expense_budgets', [
            'expense_type' => 'supplies',
            'month'        => 5,
            'year'         => 2026,
            'amount'       => 2000,
        ]);
        $this->assertEquals(1, ExpenseBudget::where('expense_type', 'supplies')->where('month', 5)->where('year', 2026)->count());
    }

    public function test_carryover_skips_type_with_no_prior_budget(): void
    {
        // No prior budget for 'transportation'
        $this->artisan('expense:carry-budget', ['--month' => 5, '--year' => 2026])
             ->assertExitCode(0);

        $this->assertDatabaseMissing('expense_budgets', [
            'expense_type' => 'transportation',
            'month'        => 5,
            'year'         => 2026,
        ]);
    }

    public function test_command_handles_january_by_targeting_december_prior_year(): void
    {
        $this->makeBudget('maintenance', 12, 2025, 6000);
        $this->makeReleasedExpense('maintenance', 12, 2025, 1000);

        $this->artisan('expense:carry-budget', ['--month' => 1, '--year' => 2026])
             ->assertExitCode(0);

        $this->assertDatabaseHas('expense_budgets', [
            'expense_type' => 'maintenance',
            'month'        => 1,
            'year'         => 2026,
            'amount'       => 5000,
        ]);
    }
}
```

- [ ] **Step 2: Run — confirm all 5 fail**

```bash
./vendor/bin/pest --filter=BudgetCarryoverTest 2>&1 | tail -10
```

Expected: 5 failed (command `expense:carry-budget` not found)

---

## Task 4: Budget Carryover — Artisan Command

**Files:**
- Create: `app/Console/Commands/CarryBudgetCommand.php`
- Modify: `routes/console.php`

- [ ] **Step 1: Create `app/Console/Commands/CarryBudgetCommand.php`**

```php
<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\ExpenseBudget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CarryBudgetCommand extends Command
{
    protected $signature   = 'expense:carry-budget {--month=} {--year=}';
    protected $description = 'Carry unused expense budget from prior month into current month';

    private const TYPES = ['operational', 'utilities', 'supplies', 'transportation', 'maintenance', 'others'];

    public function handle(): int
    {
        $month = (int) ($this->option('month') ?? now()->month);
        $year  = (int) ($this->option('year') ?? now()->year);

        if ($month === 1) {
            $priorMonth = 12;
            $priorYear  = $year - 1;
        } else {
            $priorMonth = $month - 1;
            $priorYear  = $year;
        }

        DB::transaction(function () use ($month, $year, $priorMonth, $priorYear) {
            foreach (self::TYPES as $type) {
                $priorBudget = ExpenseBudget::where('expense_type', $type)
                    ->where('month', $priorMonth)
                    ->where('year', $priorYear)
                    ->first();

                if (! $priorBudget || $priorBudget->amount <= 0) {
                    $this->line("Skipped {$type} — no prior budget");
                    continue;
                }

                $spent = (float) Expense::where('expense_type', $type)
                    ->where('status', 'released')
                    ->whereMonth('expense_date', $priorMonth)
                    ->whereYear('expense_date', $priorYear)
                    ->sum('amount');

                $remainder = round($priorBudget->amount - $spent, 2);

                if ($remainder <= 0) {
                    $this->line("Skipped {$type} — budget fully used");
                    continue;
                }

                $exists = ExpenseBudget::where('expense_type', $type)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->exists();

                if ($exists) {
                    $this->line("Skipped {$type} — budget already set");
                    continue;
                }

                ExpenseBudget::create([
                    'expense_type'  => $type,
                    'month'         => $month,
                    'year'          => $year,
                    'amount'        => $remainder,
                    'created_by_id' => null,
                ]);

                $this->line("Carried ₱{$remainder} from {$type} ({$priorYear}-{$priorMonth}) → ({$year}-{$month})");
            }
        });

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Register the schedule in `routes/console.php`**

Find the existing schedule entries in `routes/console.php`:

```php
Schedule::command('credit:monthly')->monthlyOn(1, '01:00');
Schedule::command('credit:annual')->yearlyOn(1, 1, '01:00');
Schedule::command('invoices:mark-overdue')->dailyAt('00:05');
```

Add after the last entry:

```php
Schedule::command('expense:carry-budget')->monthlyOn(1, '00:05');
```

- [ ] **Step 3: Run — confirm all 5 tests pass**

```bash
./vendor/bin/pest --filter=BudgetCarryoverTest 2>&1 | tail -10
```

Expected: 5 passed

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/CarryBudgetCommand.php \
        routes/console.php \
        tests/Feature/Expenses/BudgetCarryoverTest.php
git commit -m "feat: add expense:carry-budget command with monthly scheduler"
```

---

## Task 5: Void Button — Frontend

**Files:**
- Modify: `resources/js/Pages/Modules/Expenses/Index.vue`

- [ ] **Step 1: Add `canVoid` computed property**

In `resources/js/Pages/Modules/Expenses/Index.vue`, find the `computed` section (it already has `canApprove`). Add `canVoid` alongside it:

```js
canVoid() {
    const roles = this.$page?.props?.roles || [];
    return ['Administrator', 'Top Management'].some(r => roles.includes(r));
},
```

- [ ] **Step 2: Add `voidExpense()` method**

In the `methods` section (alongside `approveExpense`), add:

```js
voidExpense(expense) {
    this.$swal?.fire?.({
        title: 'Void expense?',
        text: 'This will cancel the expense and restore the fund balance. This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, void it',
        confirmButtonColor: '#dc3545',
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.patch(`/expenses/${expense.id}/void`)
            .then(res => {
                if (res.data.status === 'error') {
                    this.$swal?.fire?.('Notice', res.data.message, 'warning');
                    return;
                }
                const idx = this.lists.findIndex(e => e.id === expense.id);
                if (idx !== -1) this.lists[idx].status = 'voided';
            })
            .catch(() => {
                this.$swal?.fire?.('Error', 'Failed to void expense.', 'error');
            });
    });
},
```

- [ ] **Step 3: Add Void button to the table row actions**

Find the existing Approve button in the `<tbody>` action buttons area:

```html
<button
    v-if="list.status === 'recorded' && canApprove"
    @click.stop="approveExpense(list)"
    class="action-icon-btn approve"
    title="Approve"
>
    <i class="ri-check-double-line"></i>
</button>
```

Add a Void button immediately after it:

```html
<button
    v-if="['recorded', 'approved', 'submitted'].includes(list.status) && canVoid"
    @click.stop="voidExpense(list)"
    class="action-icon-btn"
    style="background:#fee2e2;color:#dc3545;border:1px solid #fca5a5"
    title="Void"
>
    <i class="ri-close-circle-line"></i>
</button>
```

- [ ] **Step 4: Add Void button to the expanded row detail card**

Find the expanded row section (it has the `system-action-btn system-action-success` Approve Expense button added in Group 1). Add a Void button alongside it:

```html
<button
    v-if="['recorded', 'approved', 'submitted'].includes(list.status) && canVoid"
    @click="voidExpense(list)"
    class="system-action-btn system-action-danger"
>
    <i class="ri-close-circle-line me-1"></i>
    Void Expense
</button>
```

- [ ] **Step 5: Build**

```bash
npm run build 2>&1 | tail -5
```

Expected: Build completes with no errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Modules/Expenses/Index.vue
git commit -m "feat: add Void button to expense list rows"
```

---

## Task 6: Final Verification

- [ ] **Step 1: Run the full test suite**

```bash
./vendor/bin/pest 2>&1 | tail -10
```

Expected new passing tests:
- `ExpenseVoidTest` — 5 passed
- `BudgetCarryoverTest` — 5 passed

The pre-existing 8 Auth/Profile failures remain — those are unrelated.

- [ ] **Step 2: Verify routes registered**

```bash
php artisan route:list --path=expenses 2>&1 | grep -E "void|approve"
```

Expected: both `expenses/{id}/approve` and `expenses/{id}/void` listed.

- [ ] **Step 3: Verify command registered**

```bash
php artisan list | grep carry
```

Expected: `expense:carry-budget` listed.

- [ ] **Step 4: Final commit (if any stray changes)**

```bash
git status
```

If clean, nothing to do. If there are unstaged files, commit them with:

```bash
git add -A
git commit -m "feat: complete Group 2 - void expense and budget carryover"
```
