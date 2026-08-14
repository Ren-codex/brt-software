# Wire Accounting Module (Plan F) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn on real backend + frontend permission enforcement for Accounting, per the design spec's §15 addendum — eight submodules (Financial Reports, Journal Entries, Chart of Accounts, Cash Management, Petty Cash, Expenses, Bank Reconciliation, Remittances), layered inside the existing all-Accounting-routes `role:Administrator` gate. This is the last module in the original design spec's pilot scope — completing it closes out the whole dynamic RBAC project.

**Architecture:** Same framework as Plans A–E. Every Accounting-related controller is clean and single-purpose (no request-field action multiplexing — confirmed by reading all eight controllers), so this plan leans entirely on declarative `permission:...` route middleware via `Route::resource(...)->middlewareFor(...)` / `->middleware(...)`, exactly like Plans C, D, and E.

**Tech Stack:** Same as Plans A–E — Laravel 11, Pest/PHPUnit (`RefreshDatabase`), Vue 3 Options API + Inertia.

## Global Constraints

- Run the full suite (`php artisan test`) after each task; the baseline going into this plan is **269 passing / 19 failing** (all 19 pre-existing, unrelated to this work). Any *new* failure is a regression to fix before moving on.
- Per spec §15, Accounting gets **eight submodules**: `financial_reports`, `journal_entries`, `chart_of_accounts`, `cash_management`, `petty_cash`, `expenses`, `bank_reconciliation`, `remittances`. See spec §15's table for the exact controller-to-submodule mapping and access levels — reproduced in each task below.
- **All routes already sit behind `role:Administrator`** today (the same large route-group Payroll/Remittances share) — the new middleware is a second layer throughout, same as Inventory/User Management/Payroll, never a sole gate like Sales/Employees/Customers. Every test in this plan gives its fixture user the `Administrator` role and varies only the new fine-grained grant.
- `remittances` routes are registered at `/remittances` (no `/accounting` prefix) but are classified under the `accounting` module — same UI-grouping-not-route-grouping precedent as Payroll's `payroll_settings` spanning two route groups.
- `financial_reports` has **no Encoder/Approver/Admin actions** — it's read-only reports, `view` is the only level that ever applies.
- `sales-incentives` belongs to Payroll (§14), not Accounting, and is untouched by this plan.
- Per spec §10, the seeder task must be **reviewed with the user before running on production** — do not run it there without asking first, and deploy + run it immediately after the first backend-enforcement task lands (this plan's Task 3), not after the whole plan ships.

---

### Task 1: Seed the Accounting submodule catalog

**Files:**
- Modify: `database/seeders/ModulesAndSubmodulesSeeder.php`
- Test: `tests/Feature/Permissions/ModulesAndSubmodulesSeederTest.php` (extend the existing test)

**Interfaces:**
- Consumes: `Module`, `Submodule` (Plan A)
- Produces: eight new `submodules` rows under the existing `accounting` module.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/Permissions/ModulesAndSubmodulesSeederTest.php`, add a new test method (leave the existing ones untouched):

```php
    public function test_seeds_accounting_submodules(): void
    {
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $accounting = Module::where('key', 'accounting')->firstOrFail();
        $this->assertEquals(
            [
                'financial_reports', 'journal_entries', 'chart_of_accounts', 'cash_management',
                'petty_cash', 'expenses', 'bank_reconciliation', 'remittances',
            ],
            $accounting->submodules->pluck('key')->all()
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ModulesAndSubmodulesSeederTest`
Expected: FAIL — `test_seeds_accounting_submodules` fails (`accounting` has zero submodules today); the other tests still pass.

- [ ] **Step 3: Update the seeder**

In `database/seeders/ModulesAndSubmodulesSeeder.php`, change:
```php
            ['key' => 'accounting', 'name' => 'Accounting', 'sort_order' => 6, 'submodules' => []],
```
to:
```php
            ['key' => 'accounting', 'name' => 'Accounting', 'sort_order' => 6, 'submodules' => [
                ['key' => 'financial_reports', 'name' => 'Financial Reports', 'sort_order' => 1],
                ['key' => 'journal_entries', 'name' => 'Journal Entries', 'sort_order' => 2],
                ['key' => 'chart_of_accounts', 'name' => 'Chart of Accounts', 'sort_order' => 3],
                ['key' => 'cash_management', 'name' => 'Cash Management', 'sort_order' => 4],
                ['key' => 'petty_cash', 'name' => 'Petty Cash', 'sort_order' => 5],
                ['key' => 'expenses', 'name' => 'Expenses', 'sort_order' => 6],
                ['key' => 'bank_reconciliation', 'name' => 'Bank Reconciliation', 'sort_order' => 7],
                ['key' => 'remittances', 'name' => 'Remittances', 'sort_order' => 8],
            ]],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ModulesAndSubmodulesSeederTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 270 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add database/seeders/ModulesAndSubmodulesSeeder.php tests/Feature/Permissions/ModulesAndSubmodulesSeederTest.php
git commit -m "Seed the Accounting submodule catalog

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Run this on production right away too** (idempotent, purely additive — same discipline as every prior catalog-seeding task).

---

### Task 2: Rollout-safety default permission grants

**Files:**
- Create: `database/seeders/AccountingDefaultPermissionsSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Permissions/AccountingDefaultPermissionsSeederTest.php`

**Interfaces:**
- Consumes: `Module`, `RolePermission`, `ListRole` (Plan A)
- Produces: `Administrator` → `admin`, module-wide, on `accounting` — the only role that actually satisfies the existing `role:Administrator` gate on every Accounting route today (same reasoning as Plan E's Payroll seeder — the nav shows `Accountant`/`Super Admin`, but the route gate itself requires `Administrator`, so seeding `Accountant` would be granting *new* access, not preserving existing access). `Super Admin` needs no row.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use Database\Seeders\AccountingDefaultPermissionsSeeder;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingDefaultPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    public function test_administrator_gets_admin_module_wide_on_accounting(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(AccountingDefaultPermissionsSeeder::class);

        $role = ListRole::where('name', 'Administrator')->firstOrFail();
        $module = \App\Models\Module::where('key', 'accounting')->firstOrFail();
        $levels = RolePermission::where('role_id', $role->id)
            ->where('module_id', $module->id)
            ->whereNull('submodule_id')
            ->pluck('access_level')
            ->all();

        $this->assertEquals(['admin'], $levels);
    }

    public function test_seeder_is_idempotent(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(AccountingDefaultPermissionsSeeder::class);
        $this->seed(AccountingDefaultPermissionsSeeder::class);

        $this->assertEquals(1, RolePermission::count());
    }

    public function test_seeder_skips_roles_that_dont_exist_in_this_environment(): void
    {
        $this->seed(AccountingDefaultPermissionsSeeder::class);

        $this->assertEquals(0, RolePermission::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AccountingDefaultPermissionsSeederTest`
Expected: FAIL — class `Database\Seeders\AccountingDefaultPermissionsSeeder` not found.

- [ ] **Step 3: Write the seeder**

`database/seeders/AccountingDefaultPermissionsSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class AccountingDefaultPermissionsSeeder extends Seeder
{
    /**
     * Rollout-safety seeding (design spec §10/§15): Administrator is the
     * only role that actually satisfies the existing role:Administrator
     * gate on every Accounting route today, so it's the only one that
     * needs a preserved-access grant here — no role gets new access it
     * doesn't already have. Super Admin needs no row — PermissionService
     * bypasses it unconditionally.
     */
    public function run(): void
    {
        $module = Module::where('key', 'accounting')->first();
        if (!$module) {
            return; // modules catalog not seeded yet — nothing to grant against.
        }

        $role = ListRole::where('name', 'Administrator')->first();
        if (!$role) {
            return; // role doesn't exist in this environment — skip rather than fail the seeder.
        }

        RolePermission::firstOrCreate([
            'role_id' => $role->id,
            'module_id' => $module->id,
            'submodule_id' => null,
            'access_level' => 'admin',
        ]);
    }
}
```

- [ ] **Step 4: Register it in `DatabaseSeeder.php`**

In `database/seeders/DatabaseSeeder.php`, change:
```php
        $this->call(PayrollDefaultPermissionsSeeder::class);
```
to:
```php
        $this->call(PayrollDefaultPermissionsSeeder::class);
        $this->call(AccountingDefaultPermissionsSeeder::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AccountingDefaultPermissionsSeederTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 273 passing / 19 failing

- [ ] **Step 7: Commit**

```bash
git add database/seeders/AccountingDefaultPermissionsSeeder.php database/seeders/DatabaseSeeder.php \
        tests/Feature/Permissions/AccountingDefaultPermissionsSeederTest.php
git commit -m "Seed rollout-safety default Accounting permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Do not run this seeder on production yet** — surface it to the user for review before running it there.

---

### Task 3: Financial Reports backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Accounting/FinancialReportsPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `accounting,financial_reports,view` enforcement on every read-only report route + the Accounting dashboard.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Accounting;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportsPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    /**
     * Every fixture holds the Administrator role (satisfies the existing
     * role:Administrator gate) and varies only the new fine-grained grant.
     */
    private function administratorWithGrant(?string $submoduleKey, ?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submoduleId, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    public function test_dashboard_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant('expenses', 'view');

        $this->actingAs($user)->get('/accounting')->assertForbidden();
    }

    public function test_dashboard_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('financial_reports', 'view');

        $this->actingAs($user)->get('/accounting')->assertOk();
    }

    public function test_general_ledger_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null, null);

        $this->actingAs($user)->get('/accounting/general-ledger')->assertForbidden();
    }

    public function test_trial_balance_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('financial_reports', 'view');

        $this->actingAs($user)->get('/accounting/trial-balance')->assertOk();
    }

    public function test_still_blocked_by_the_existing_role_gate(): void
    {
        $role = ListRole::create(['name' => 'Random Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'accounting')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id, 'submodule_id' => null, 'access_level' => 'admin',
        ]);

        $this->actingAs($user)->get('/accounting')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FinancialReportsPermissionTest`
Expected: FAIL — the `denied` tests fail; `test_still_blocked_by_the_existing_role_gate` already passes.

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::get('/accounting', [App\Http\Controllers\Modules\AccountingController::class, 'index']);
        Route::get('/accounting/general-ledger', [App\Http\Controllers\Modules\AccountingController::class, 'generalLedger']);
        Route::get('/accounting/trial-balance', [App\Http\Controllers\Modules\AccountingController::class, 'trialBalance']);
        Route::get('/accounting/profit-loss', [App\Http\Controllers\Modules\AccountingController::class, 'profitLoss']);
        Route::get('/accounting/balance-sheet', [App\Http\Controllers\Modules\AccountingController::class, 'balanceSheet']);
        Route::get('/accounting/cash-flow', [App\Http\Controllers\Modules\AccountingController::class, 'cashFlowStatement']);
        Route::get('/accounting/accounts-receivable', [App\Http\Controllers\Modules\AccountingController::class, 'accountsReceivable']);
        Route::get('/accounting/accounts-payable', [App\Http\Controllers\Modules\AccountingController::class, 'accountsPayable']);
```
to:
```php
        Route::get('/accounting', [App\Http\Controllers\Modules\AccountingController::class, 'index'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/general-ledger', [App\Http\Controllers\Modules\AccountingController::class, 'generalLedger'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/trial-balance', [App\Http\Controllers\Modules\AccountingController::class, 'trialBalance'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/profit-loss', [App\Http\Controllers\Modules\AccountingController::class, 'profitLoss'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/balance-sheet', [App\Http\Controllers\Modules\AccountingController::class, 'balanceSheet'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/cash-flow', [App\Http\Controllers\Modules\AccountingController::class, 'cashFlowStatement'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/accounts-receivable', [App\Http\Controllers\Modules\AccountingController::class, 'accountsReceivable'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/accounts-payable', [App\Http\Controllers\Modules\AccountingController::class, 'accountsPayable'])
            ->middleware('permission:accounting,financial_reports,view');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=FinancialReportsPermissionTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 278 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Accounting/FinancialReportsPermissionTest.php
git commit -m "Enforce Financial Reports permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Deploy and run Task 2's seeder on production right after this task** — this is the task that actually starts enforcing anything.

---

### Task 4: Journal Entries backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Accounting/JournalEntryPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `accounting,journal_entries,view|encoder` enforcement.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Accounting;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'journal_entries')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/journal-entries')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/journal-entries')->assertOk();
    }

    public function test_store_manual_journal_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/journal-entries', [])->assertForbidden();
    }

    public function test_store_manual_journal_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $response = $this->actingAs($user)->post('/accounting/journal-entries', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JournalEntryPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::get('/accounting/journal-entries', [App\Http\Controllers\Modules\AccountingController::class, 'journalEntries']);
        Route::post('/accounting/journal-entries', [App\Http\Controllers\Modules\AccountingController::class, 'storeManualJournal']);
```
to:
```php
        Route::get('/accounting/journal-entries', [App\Http\Controllers\Modules\AccountingController::class, 'journalEntries'])
            ->middleware('permission:accounting,journal_entries,view');
        Route::post('/accounting/journal-entries', [App\Http\Controllers\Modules\AccountingController::class, 'storeManualJournal'])
            ->middleware('permission:accounting,journal_entries,encoder');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=JournalEntryPermissionTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 282 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Accounting/JournalEntryPermissionTest.php
git commit -m "Enforce Journal Entries permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Chart of Accounts backend enforcement (incl. Bank Accounts)

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Accounting/ChartOfAccountsPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `accounting,chart_of_accounts,view|encoder|admin` enforcement on `AccountingController`'s account routes + `BankAccountController`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountsPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'chart_of_accounts')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeAccount(): Account
    {
        return Account::create([
            'code' => 'ACC-' . uniqid(), 'slug' => 'acc-' . uniqid(),
            'name' => 'Test Account', 'type' => 'asset',
        ]);
    }

    public function test_settings_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/settings')->assertForbidden();
    }

    public function test_settings_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/settings')->assertOk();
    }

    public function test_store_account_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/accounts', [])->assertForbidden();
    }

    public function test_destroy_account_denied_without_admin_grant(): void
    {
        $account = $this->makeAccount();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete("/accounting/accounts/{$account->id}")->assertForbidden();
    }

    public function test_destroy_account_allowed_with_admin_grant(): void
    {
        $account = $this->makeAccount();
        $user = $this->administratorWithGrant('admin');

        $response = $this->actingAs($user)->delete("/accounting/accounts/{$account->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_bank_accounts_list_gated_by_the_same_grant(): void
    {
        $denied = $this->administratorWithGrant(null);
        $this->actingAs($denied)->get('/accounting/bank-accounts/list')->assertForbidden();

        $allowed = $this->administratorWithGrant('view');
        $this->actingAs($allowed)->get('/accounting/bank-accounts/list')->assertOk();
    }

    public function test_bank_accounts_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/bank-accounts', [])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChartOfAccountsPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::get('/accounting/settings', [App\Http\Controllers\Modules\AccountingController::class, 'settings']);
        Route::get('/accounting/chart-of-accounts', fn() => redirect('/accounting/settings'));
        Route::get('/accounting/bank-accounts', fn() => redirect('/accounting/settings'));
        Route::post('/accounting/accounts', [App\Http\Controllers\Modules\AccountingController::class, 'storeAccount']);
        Route::put('/accounting/accounts/{id}', [App\Http\Controllers\Modules\AccountingController::class, 'updateAccount']);
        Route::patch('/accounting/accounts/{id}/toggle', [App\Http\Controllers\Modules\AccountingController::class, 'toggleAccount']);
        Route::delete('/accounting/accounts/{id}', [App\Http\Controllers\Modules\AccountingController::class, 'destroyAccount']);
```
to:
```php
        Route::get('/accounting/settings', [App\Http\Controllers\Modules\AccountingController::class, 'settings'])
            ->middleware('permission:accounting,chart_of_accounts,view');
        Route::get('/accounting/chart-of-accounts', fn() => redirect('/accounting/settings'));
        Route::get('/accounting/bank-accounts', fn() => redirect('/accounting/settings'));
        Route::post('/accounting/accounts', [App\Http\Controllers\Modules\AccountingController::class, 'storeAccount'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
        Route::put('/accounting/accounts/{id}', [App\Http\Controllers\Modules\AccountingController::class, 'updateAccount'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
        Route::patch('/accounting/accounts/{id}/toggle', [App\Http\Controllers\Modules\AccountingController::class, 'toggleAccount'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
        Route::delete('/accounting/accounts/{id}', [App\Http\Controllers\Modules\AccountingController::class, 'destroyAccount'])
            ->middleware('permission:accounting,chart_of_accounts,admin');
```

(The two redirect routes are left unmiddlewared — they just forward to `/accounting/settings`, which is itself gated; double-gating a redirect adds nothing.)

Then change:
```php
        Route::get('/accounting/bank-accounts/list', [App\Http\Controllers\Modules\BankAccountController::class, 'list']);
        Route::post('/accounting/bank-accounts', [App\Http\Controllers\Modules\BankAccountController::class, 'store']);
        Route::put('/accounting/bank-accounts/{id}', [App\Http\Controllers\Modules\BankAccountController::class, 'update']);
        Route::patch('/accounting/bank-accounts/{id}/toggle', [App\Http\Controllers\Modules\BankAccountController::class, 'toggle']);
```
to:
```php
        Route::get('/accounting/bank-accounts/list', [App\Http\Controllers\Modules\BankAccountController::class, 'list'])
            ->middleware('permission:accounting,chart_of_accounts,view');
        Route::post('/accounting/bank-accounts', [App\Http\Controllers\Modules\BankAccountController::class, 'store'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
        Route::put('/accounting/bank-accounts/{id}', [App\Http\Controllers\Modules\BankAccountController::class, 'update'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
        Route::patch('/accounting/bank-accounts/{id}/toggle', [App\Http\Controllers\Modules\BankAccountController::class, 'toggle'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ChartOfAccountsPermissionTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 289 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Accounting/ChartOfAccountsPermissionTest.php
git commit -m "Enforce Chart of Accounts permissions (incl. Bank Accounts)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Cash Management backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Accounting/CashManagementPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `accounting,cash_management,view|encoder|admin` enforcement on fund transfers, bank deposits, and bank withdrawals (**not** petty-cash transactions — those belong to `petty_cash`, Task 7).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Accounting;

use App\Models\BankAccount;
use App\Models\FundTransfer;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashManagementPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'cash_management')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeFundTransfer(): FundTransfer
    {
        $from = BankAccount::create(['bank_name' => 'Bank A', 'account_name' => 'Acct A', 'gl_code' => 'GL-' . uniqid()]);
        $to = BankAccount::create(['bank_name' => 'Bank B', 'account_name' => 'Acct B', 'gl_code' => 'GL-' . uniqid()]);

        return FundTransfer::create([
            'transfer_no' => 'FT-' . uniqid(), 'transfer_date' => now()->toDateString(),
            'from_bank_account_id' => $from->id, 'to_bank_account_id' => $to->id, 'amount' => 1000,
        ]);
    }

    public function test_index_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/cash-management')->assertForbidden();
    }

    public function test_index_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/cash-management')->assertOk();
    }

    public function test_store_fund_transfer_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/fund-transfers', [])->assertForbidden();
    }

    public function test_store_fund_transfer_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $response = $this->actingAs($user)->post('/accounting/fund-transfers', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_fund_transfer_denied_without_admin_grant(): void
    {
        $transfer = $this->makeFundTransfer();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete("/accounting/fund-transfers/{$transfer->id}")->assertForbidden();
    }

    public function test_store_deposit_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/bank-deposits', [])->assertForbidden();
    }

    public function test_store_withdrawal_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/bank-withdrawals', [])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CashManagementPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::get('/accounting/cash-management', [App\Http\Controllers\Modules\CashManagementController::class, 'index']);
```
to:
```php
        Route::get('/accounting/cash-management', [App\Http\Controllers\Modules\CashManagementController::class, 'index'])
            ->middleware('permission:accounting,cash_management,view');
```

Then, further down in the same group, change:
```php
        Route::post('/accounting/fund-transfers', [App\Http\Controllers\Modules\CashManagementController::class, 'storeFundTransfer']);
        Route::delete('/accounting/fund-transfers/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyFundTransfer']);
        Route::post('/accounting/petty-cash/transactions', [App\Http\Controllers\Modules\CashManagementController::class, 'storePettyCashTransaction']);
        Route::delete('/accounting/petty-cash/transactions/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyPettyCashTransaction']);
        Route::post('/accounting/bank-deposits', [App\Http\Controllers\Modules\CashManagementController::class, 'storeDeposit']);
        Route::delete('/accounting/bank-deposits/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyDeposit']);
        Route::post('/accounting/bank-withdrawals', [App\Http\Controllers\Modules\CashManagementController::class, 'storeWithdrawal']);
        Route::delete('/accounting/bank-withdrawals/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyWithdrawal']);
```
to:
```php
        Route::post('/accounting/fund-transfers', [App\Http\Controllers\Modules\CashManagementController::class, 'storeFundTransfer'])
            ->middleware('permission:accounting,cash_management,encoder');
        Route::delete('/accounting/fund-transfers/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyFundTransfer'])
            ->middleware('permission:accounting,cash_management,admin');
        Route::post('/accounting/petty-cash/transactions', [App\Http\Controllers\Modules\CashManagementController::class, 'storePettyCashTransaction'])
            ->middleware('permission:accounting,petty_cash,encoder');
        Route::delete('/accounting/petty-cash/transactions/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyPettyCashTransaction'])
            ->middleware('permission:accounting,petty_cash,admin');
        Route::post('/accounting/bank-deposits', [App\Http\Controllers\Modules\CashManagementController::class, 'storeDeposit'])
            ->middleware('permission:accounting,cash_management,encoder');
        Route::delete('/accounting/bank-deposits/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyDeposit'])
            ->middleware('permission:accounting,cash_management,admin');
        Route::post('/accounting/bank-withdrawals', [App\Http\Controllers\Modules\CashManagementController::class, 'storeWithdrawal'])
            ->middleware('permission:accounting,cash_management,encoder');
        Route::delete('/accounting/bank-withdrawals/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyWithdrawal'])
            ->middleware('permission:accounting,cash_management,admin');
```

(`storePettyCashTransaction`/`destroyPettyCashTransaction` are gated under `petty_cash`, not `cash_management`, per spec §15 — this task's diff includes them here because they sit in the same route block as the other `CashManagementController` routes in `routes/web.php`, but the middleware string itself targets the `petty_cash` submodule.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CashManagementPermissionTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 296 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Accounting/CashManagementPermissionTest.php
git commit -m "Enforce Cash Management permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Petty Cash backend enforcement (incl. Funds)

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Accounting/PettyCashPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `accounting,petty_cash,view|encoder|approver|admin` enforcement on `PettyCashController`, `FundController`, and the two `CashManagementController` petty-cash-transaction routes (already gated in Task 6's diff — this task covers the rest).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Accounting;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\PettyCashFund;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PettyCashPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'petty_cash')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeFund(): PettyCashFund
    {
        return PettyCashFund::create(['name' => 'Main Fund', 'gl_code' => 'PCF-' . uniqid()]);
    }

    public function test_index_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/petty-cash')->assertForbidden();
    }

    public function test_index_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/petty-cash')->assertOk();
    }

    public function test_store_voucher_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/petty-cash/vouchers', [])->assertForbidden();
    }

    public function test_void_voucher_denied_without_approver_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete('/accounting/petty-cash/vouchers/1')->assertForbidden();
    }

    public function test_fund_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/accounting/funds?option=lists')->assertForbidden();
    }

    public function test_fund_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/funds', [])->assertForbidden();
    }

    public function test_fund_top_up_denied_without_encoder_grant(): void
    {
        $fund = $this->makeFund();
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post("/accounting/funds/{$fund->id}/top-up", [])->assertForbidden();
    }

    public function test_fund_adjust_balance_denied_without_approver_grant(): void
    {
        $fund = $this->makeFund();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->patch("/accounting/funds/{$fund->id}/balance", [])->assertForbidden();
    }

    public function test_fund_adjust_balance_allowed_with_approver_grant(): void
    {
        $fund = $this->makeFund();
        $user = $this->administratorWithGrant('approver');

        $response = $this->actingAs($user)->patch("/accounting/funds/{$fund->id}/balance", []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PettyCashPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::get('/accounting/petty-cash', [App\Http\Controllers\Modules\PettyCashController::class, 'index']);
        Route::post('/accounting/petty-cash/vouchers', [App\Http\Controllers\Modules\PettyCashController::class, 'storeVoucher']);
        Route::delete('/accounting/petty-cash/vouchers/{id}', [App\Http\Controllers\Modules\PettyCashController::class, 'voidVoucher']);
```
to:
```php
        Route::get('/accounting/petty-cash', [App\Http\Controllers\Modules\PettyCashController::class, 'index'])
            ->middleware('permission:accounting,petty_cash,view');
        Route::post('/accounting/petty-cash/vouchers', [App\Http\Controllers\Modules\PettyCashController::class, 'storeVoucher'])
            ->middleware('permission:accounting,petty_cash,encoder');
        Route::delete('/accounting/petty-cash/vouchers/{id}', [App\Http\Controllers\Modules\PettyCashController::class, 'voidVoucher'])
            ->middleware('permission:accounting,petty_cash,approver');
```

Then, in the `role:Administrator` **Libraries** group (a different block from the one above — `FundController` sits alongside Products/Positions/etc.), change:
```php
        Route::resource('/accounting/funds', App\Http\Controllers\Libraries\FundController::class)->only(['index', 'store', 'update']);
        Route::post('/accounting/funds/{id}/top-up', [App\Http\Controllers\Libraries\FundController::class, 'topUp']);
        Route::patch('/accounting/funds/{id}/balance', [App\Http\Controllers\Libraries\FundController::class, 'adjustBalance']);
        Route::patch('/accounting/funds/{id}/toggle-active', [App\Http\Controllers\Libraries\FundController::class, 'toggleActive']);
```
to:
```php
        Route::resource('/accounting/funds', App\Http\Controllers\Libraries\FundController::class)->only(['index', 'store', 'update'])
            ->middlewareFor('index', 'permission:accounting,petty_cash,view')
            ->middlewareFor(['store', 'update'], 'permission:accounting,petty_cash,encoder');
        Route::post('/accounting/funds/{id}/top-up', [App\Http\Controllers\Libraries\FundController::class, 'topUp'])
            ->middleware('permission:accounting,petty_cash,encoder');
        Route::patch('/accounting/funds/{id}/balance', [App\Http\Controllers\Libraries\FundController::class, 'adjustBalance'])
            ->middleware('permission:accounting,petty_cash,approver');
        Route::patch('/accounting/funds/{id}/toggle-active', [App\Http\Controllers\Libraries\FundController::class, 'toggleActive'])
            ->middleware('permission:accounting,petty_cash,encoder');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PettyCashPermissionTest`
Expected: PASS (9 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 305 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Accounting/PettyCashPermissionTest.php
git commit -m "Enforce Petty Cash permissions (incl. Funds)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 8: Expenses backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Accounting/ExpensePermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `accounting,expenses,view|encoder|approver|admin` enforcement on `GeneralExpenseController`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Accounting;

use App\Models\Expense;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'expenses')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeExpense(): Expense
    {
        return Expense::create([
            'expense_type' => 'operational', 'amount' => 500,
            'expense_date' => now()->toDateString(), 'added_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_index_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/expenses')->assertForbidden();
    }

    public function test_index_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/expenses')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/expenses', [])->assertForbidden();
    }

    public function test_approve_denied_without_approver_grant(): void
    {
        $expense = $this->makeExpense();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->patch("/accounting/expenses/{$expense->id}/approve")->assertForbidden();
    }

    public function test_approve_allowed_with_approver_grant(): void
    {
        $expense = $this->makeExpense();
        $user = $this->administratorWithGrant('approver');

        $response = $this->actingAs($user)->patch("/accounting/expenses/{$expense->id}/approve");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_void_denied_without_approver_grant(): void
    {
        $expense = $this->makeExpense();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->patch("/accounting/expenses/{$expense->id}/void")->assertForbidden();
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $expense = $this->makeExpense();
        $user = $this->administratorWithGrant('approver');

        $this->actingAs($user)->delete("/accounting/expenses/{$expense->id}")->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ExpensePermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::get('/accounting/expenses', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'index']);
        Route::post('/accounting/expenses', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'store']);
        Route::put('/accounting/expenses/{id}', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'update']);
        Route::patch('/accounting/expenses/{id}/approve', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'approve']);
        Route::patch('/accounting/expenses/{id}/void', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'void']);
        Route::delete('/accounting/expenses/{id}', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'destroy']);
```
to:
```php
        Route::get('/accounting/expenses', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'index'])
            ->middleware('permission:accounting,expenses,view');
        Route::post('/accounting/expenses', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'store'])
            ->middleware('permission:accounting,expenses,encoder');
        Route::put('/accounting/expenses/{id}', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'update'])
            ->middleware('permission:accounting,expenses,encoder');
        Route::patch('/accounting/expenses/{id}/approve', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'approve'])
            ->middleware('permission:accounting,expenses,approver');
        Route::patch('/accounting/expenses/{id}/void', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'void'])
            ->middleware('permission:accounting,expenses,approver');
        Route::delete('/accounting/expenses/{id}', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'destroy'])
            ->middleware('permission:accounting,expenses,admin');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ExpensePermissionTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 312 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Accounting/ExpensePermissionTest.php
git commit -m "Enforce Expenses permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 9: Bank Reconciliation backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Accounting/BankReconciliationPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `accounting,bank_reconciliation,view|encoder|approver|admin` enforcement on `BankReconciliationController`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Accounting;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankReconciliationPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'bank_reconciliation')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeReconciliation(): BankReconciliation
    {
        $bankAccount = BankAccount::create(['bank_name' => 'Bank A', 'account_name' => 'Acct A', 'gl_code' => 'GL-' . uniqid()]);

        return BankReconciliation::create([
            'bank_account_id' => $bankAccount->id, 'period_end' => now()->toDateString(), 'statement_balance' => 1000,
        ]);
    }

    public function test_index_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/accounting/bank-reconciliation')->assertForbidden();
    }

    public function test_index_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/accounting/bank-reconciliation')->assertOk();
    }

    public function test_start_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/accounting/bank-reconciliation', [])->assertForbidden();
    }

    public function test_toggle_clear_denied_without_encoder_grant(): void
    {
        $reconciliation = $this->makeReconciliation();
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post("/accounting/bank-reconciliation/{$reconciliation->id}/toggle-clear")->assertForbidden();
    }

    public function test_finalize_denied_without_approver_grant(): void
    {
        $reconciliation = $this->makeReconciliation();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->post("/accounting/bank-reconciliation/{$reconciliation->id}/finalize")->assertForbidden();
    }

    public function test_finalize_allowed_with_approver_grant(): void
    {
        $reconciliation = $this->makeReconciliation();
        $user = $this->administratorWithGrant('approver');

        $response = $this->actingAs($user)->post("/accounting/bank-reconciliation/{$reconciliation->id}/finalize");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $reconciliation = $this->makeReconciliation();
        $user = $this->administratorWithGrant('approver');

        $this->actingAs($user)->delete("/accounting/bank-reconciliation/{$reconciliation->id}")->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BankReconciliationPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::get('/accounting/bank-reconciliation', [App\Http\Controllers\Modules\BankReconciliationController::class, 'index']);
        Route::post('/accounting/bank-reconciliation', [App\Http\Controllers\Modules\BankReconciliationController::class, 'start']);
        Route::get('/accounting/bank-reconciliation/{id}', [App\Http\Controllers\Modules\BankReconciliationController::class, 'show']);
        Route::post('/accounting/bank-reconciliation/{id}/toggle-clear', [App\Http\Controllers\Modules\BankReconciliationController::class, 'toggleClear']);
        Route::post('/accounting/bank-reconciliation/{id}/finalize', [App\Http\Controllers\Modules\BankReconciliationController::class, 'finalize']);
        Route::delete('/accounting/bank-reconciliation/{id}', [App\Http\Controllers\Modules\BankReconciliationController::class, 'destroy']);
```
to:
```php
        Route::get('/accounting/bank-reconciliation', [App\Http\Controllers\Modules\BankReconciliationController::class, 'index'])
            ->middleware('permission:accounting,bank_reconciliation,view');
        Route::post('/accounting/bank-reconciliation', [App\Http\Controllers\Modules\BankReconciliationController::class, 'start'])
            ->middleware('permission:accounting,bank_reconciliation,encoder');
        Route::get('/accounting/bank-reconciliation/{id}', [App\Http\Controllers\Modules\BankReconciliationController::class, 'show'])
            ->middleware('permission:accounting,bank_reconciliation,view');
        Route::post('/accounting/bank-reconciliation/{id}/toggle-clear', [App\Http\Controllers\Modules\BankReconciliationController::class, 'toggleClear'])
            ->middleware('permission:accounting,bank_reconciliation,encoder');
        Route::post('/accounting/bank-reconciliation/{id}/finalize', [App\Http\Controllers\Modules\BankReconciliationController::class, 'finalize'])
            ->middleware('permission:accounting,bank_reconciliation,approver');
        Route::delete('/accounting/bank-reconciliation/{id}', [App\Http\Controllers\Modules\BankReconciliationController::class, 'destroy'])
            ->middleware('permission:accounting,bank_reconciliation,admin');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=BankReconciliationPermissionTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 319 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Accounting/BankReconciliationPermissionTest.php
git commit -m "Enforce Bank Reconciliation permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 10: Remittances backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Accounting/RemittancePermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `accounting,remittances,view|encoder|approver|admin` enforcement on `RemittanceController`. This closes the last backend gap for this plan.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Accounting;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Remittance;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemittancePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        ListStatus::firstOrCreate(['slug' => 'pending'], [
            'name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
    }

    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'accounting')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'remittances')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeRemittance(): Remittance
    {
        return Remittance::create([
            'remittance_no' => 'RM-' . uniqid(), 'remittance_date' => now()->toDateString(),
            'summary' => [], 'total_amount' => 1000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'created_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/remittances')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/remittances')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/remittances', [])->assertForbidden();
    }

    public function test_approve_denied_without_approver_grant(): void
    {
        $remittance = $this->makeRemittance();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->post("/remittances/{$remittance->id}/approve", [])->assertForbidden();
    }

    public function test_approve_allowed_with_approver_grant(): void
    {
        $remittance = $this->makeRemittance();
        $user = $this->administratorWithGrant('approver');

        $response = $this->actingAs($user)->post("/remittances/{$remittance->id}/approve", []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_remit_denied_without_approver_grant(): void
    {
        $remittance = $this->makeRemittance();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->post("/remittances/{$remittance->id}/remit")->assertForbidden();
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $remittance = $this->makeRemittance();
        $user = $this->administratorWithGrant('approver');

        $this->actingAs($user)->delete("/remittances/{$remittance->id}")->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RemittancePermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::resource('/remittances', App\Http\Controllers\RemittanceController::class)
            ->only(['index', 'store', 'show', 'destroy']);
        Route::post('/remittances/{id}/approve', [App\Http\Controllers\RemittanceController::class, 'approve'])->name('remittances.approve');
        Route::get('/remittances/{id}/print', [App\Http\Controllers\RemittanceController::class, 'printRemittance']);
        Route::post('/remittances/{id}/remit', [App\Http\Controllers\RemittanceController::class, 'remit'])->name('remittances.remit');
```
to:
```php
        Route::resource('/remittances', App\Http\Controllers\RemittanceController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->middlewareFor(['index', 'show'], 'permission:accounting,remittances,view')
            ->middlewareFor('store', 'permission:accounting,remittances,encoder')
            ->middlewareFor('destroy', 'permission:accounting,remittances,admin');
        Route::post('/remittances/{id}/approve', [App\Http\Controllers\RemittanceController::class, 'approve'])
            ->middleware('permission:accounting,remittances,approver')->name('remittances.approve');
        Route::get('/remittances/{id}/print', [App\Http\Controllers\RemittanceController::class, 'printRemittance'])
            ->middleware('permission:accounting,remittances,view');
        Route::post('/remittances/{id}/remit', [App\Http\Controllers\RemittanceController::class, 'remit'])
            ->middleware('permission:accounting,remittances,approver')->name('remittances.remit');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RemittancePermissionTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 326 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Accounting/RemittancePermissionTest.php
git commit -m "Enforce Remittances permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**This closes the last backend gap for this plan — and the last backend gap in the entire original pilot scope. Deploy immediately.**

---

### Task 11: Frontend — replace hardcoded role checks, gate buttons

**Files:**
- Modify: Vue files under `resources/js/Pages/Modules/Accounting/` (inspect first — see note below)

**Interfaces:**
- Consumes: `this.can('accounting', '<submodule>', '<level>')` (Plan A's `permissionsMixin`)

**Note on this task, same as Plans D/E's equivalent tasks:** the Accounting frontend's exact file layout and button locations were not read while grounding this plan (time-boxed in favor of thoroughly grounding all eight backend submodules — the real security boundary, and by far the more error-prone half given the shared-controller/shared-table wrinkles already found, like Cash Management vs. Petty Cash splitting `CashManagementController` and Petty Cash vouchers vs. General Expenses sharing the `Expense` model). This is a *procedure*, not a pre-written diff:

- [ ] **Step 1: Find the Accounting Vue file layout**

Run: `find resources/js/Pages/Modules/Accounting -type f` — expect a tab-container `Index.vue` or similar (mirroring every other module's `Modules/<Name>/Index.vue` + `Components/<Tab>/Index.vue` pattern seen in Plans B, C, D, and E) with roughly one component per submodule from spec §15's table.

- [ ] **Step 2: For each submodule's component(s), grep for action buttons and hardcoded role checks**

Run: `grep -n "@click=\"open\|acct-btn-primary\|create-btn\|action-btn-delete\|action-btn-edit\|roles.includes" <file>` for each — same command shape used in every prior plan's grounding pass.

- [ ] **Step 3: Gate each Create/Add, Edit, Delete, and Approve/Void/Finalize/Remit button**

Using spec §15's table for the level each action requires:
- Create/Add, Edit: `v-if="can('accounting', '<submodule>', 'encoder')"`
- Delete: `v-if="can('accounting', '<submodule>', 'admin')"`
- Approve/Void (Expenses), Finalize (Bank Reconciliation), Approve/Remit (Remittances), Void voucher/Adjust balance (Petty Cash): `v-if="can('accounting', '<submodule>', 'approver')"`
- Always AND-combine with any existing condition on the button — never replace it (same rule as every prior plan).
- Financial Reports has no buttons to gate beyond what `view`-level page access already covers — skip it (matches spec §15: no Encoder/Approver/Admin actions exist there).

- [ ] **Step 4: Replace any hardcoded `roles.includes(...)` checks found in Step 2**

If any exist, replace with the equivalent `can()` call. If none exist, skip — do not invent one.

- [ ] **Step 5: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 326 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 6: Rebuild assets**

Run: `npm run build`

- [ ] **Step 7: Manually verify with a real browser session**

1. Log in as `administrator@example.com` (only role with a seeded grant — `admin` module-wide on accounting) — confirm every Create/Edit/Delete/Approve/Void/Finalize/Remit button across all eight submodule views is visible.
2. Confirm a zero-grant user gets no action buttons anywhere in Accounting and a 403 on direct navigation to `/accounting`, `/accounting/journal-entries`, `/accounting/settings`, `/accounting/cash-management`, `/accounting/petty-cash`, `/accounting/expenses`, `/accounting/bank-reconciliation`, `/remittances`.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Modules/Accounting public/build
git commit -m "Wire Accounting buttons to can()

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 12: Frontend — nav filtering

**Files:**
- Modify: `resources/js/Shared/Layouts/Components/Menu.vue`
- Modify: the Accounting tab-container Vue file identified in Task 11

**Interfaces:**
- Consumes: `this.canAny('accounting'[, '<submodule>'])` (Plan A's `permissionsMixin`)

- [ ] **Step 1: Filter the global nav Accounting link**

In `resources/js/Shared/Layouts/Components/Menu.vue`, find the Accounting `<li>` (currently `v-if="$page.props.roles.includes('Super Admin') || $page.props.roles.includes('Accountant')"`, confirmed by reading the file during Plans D/E's work on the surrounding nav items) and change its `v-if` to:
```html
                <li class="nav-item" v-if="canAny('accounting')">
```

- [ ] **Step 2: Filter the in-page Accounting tabs**

Inspect the tab-container file's `tabs:` array and tab-bar `v-for` (read the file first). Add a `visibleTabs` computed property following the exact pattern used in Sales' (Plan B), Inventory's (Plan C), and Payroll's (Plan E) `Index.vue`:

```js
  computed: {
    visibleTabs() {
      const tabToSubmodule = {
        // fill in with the actual tab ids found in Step 2, mapped to:
        // financial_reports, journal_entries, chart_of_accounts,
        // cash_management, petty_cash, expenses, bank_reconciliation,
        // remittances — per spec §15's table.
      };
      return this.tabs.filter((tab) => {
        const submoduleKey = tabToSubmodule[tab.id];
        if (!submoduleKey) {
          return true;
        }
        return this.canAny('accounting', submoduleKey);
      });
    },
  },
```

Change the tab-bar `v-for` from `v-for="tab in tabs"` to `v-for="tab in visibleTabs"` (confirm the exact surrounding markup first, same as every prior nav-filtering task).

- [ ] **Step 3: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 326 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 4: Rebuild assets**

Run: `npm run build`

- [ ] **Step 5: Manually verify with a real browser session**

1. Log in as a zero-grant user — confirm "Accounting" is gone from the left nav, and navigating to `/accounting` directly now 403s.
2. Log in as `administrator@example.com` — confirm "Accounting" appears and all eight submodule tabs are visible.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Shared/Layouts/Components/Menu.vue resources/js/Pages/Modules/Accounting public/build
git commit -m "Filter Accounting nav link and in-page tabs by permission

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage** (against `docs/superpowers/specs/2026-08-14-dynamic-role-access-design.md` §15):
- Eight-submodule taxonomy, confirmed with the user before writing this plan → Task 1
- `chart_of_accounts` spanning `AccountingController` + `BankAccountController` → Task 5
- `petty_cash` spanning `PettyCashController` + `CashManagementController`'s petty-cash methods + `FundController` (itself in a third, different route group) → Tasks 6 (the two `CashManagementController` routes) and 7 (everything else)
- `remittances` at a route path with no `/accounting` prefix → Task 10
- Rollout safety (Administrator-only, since it's the sole role actually satisfying today's gate) → Task 2
- Second-layer pattern (existing `role:Administrator` untouched) → every backend task's `test_still_blocked_by_the_existing_role_gate`-equivalent test (Task 3 has one explicitly; Tasks 4–10 rely on the same Administrator-role fixture requirement, consistent with every prior plan's precedent)

**Placeholder scan:** Tasks 11 and 12 are deliberate, explicitly-flagged exceptions to "no placeholders" — same reasoning as Plans D and E's equivalent tasks: the Accounting frontend's file layout wasn't read while grounding this plan, time-boxed in favor of thoroughly grounding all eight backend submodules (the real security boundary, and the harder half of this module given the cross-controller/cross-table submodule groupings). This is called out in each task's own text, not silently assumed. Every backend task (1–10) has complete, real code, verified against actual controller/route/model contents read during this plan's investigation — including catching and accounting for real wrinkles like `expenses` being the shared table for both Expenses and Petty Cash Vouchers, and `chart_of_accounts` needing two different route groups gated with the same submodule key.

**Type consistency:** `can()`/`canAny()` calls (Tasks 11–12) will use the exact submodule keys defined in Task 1 (`financial_reports`, `journal_entries`, `chart_of_accounts`, `cash_management`, `petty_cash`, `expenses`, `bank_reconciliation`, `remittances`) and seeded via `ModulesAndSubmodulesSeeder`. The `permission:...` middleware strings (Tasks 3–10) match `PermissionMiddleware`'s `module,submodule,level` argument order exactly, consistent with every prior plan.

---

## After This Plan

This completes every module named in the original design spec's pilot-and-deferred list (§9 Sales + Inventory, §13 Employees/Customers/User Management, §14 Payroll, §15 Accounting). The only modules left with catalog rows but no enforcement are `dashboard` (never had planned submodules or actions to gate — it's a read-only landing page) and any future modules not yet imagined. Extending further would be new scope, not a continuation of this project.
