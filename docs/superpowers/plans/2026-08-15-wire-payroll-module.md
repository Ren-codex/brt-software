# Wire Payroll Module (Plan E) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn on real backend + frontend permission enforcement for Payroll, per the design spec's §14 addendum — four submodules (Payroll Processing, Payroll Templates, Loans, Payroll Settings), layered inside the existing all-Payroll-routes `role:Administrator` gate, mirroring Inventory's/User Management's "second layer" pattern rather than Sales'.

**Architecture:** Same framework as Plans A–D. Every Payroll controller is clean and single-purpose (no request-field action multiplexing, confirmed by reading all six controllers), so this plan leans entirely on declarative `permission:...` route middleware via `Route::resource(...)->middlewareFor(...)`, exactly like Plans C and D.

**Tech Stack:** Same as Plans A–D — Laravel 11, Pest/PHPUnit (`RefreshDatabase`), Vue 3 Options API + Inertia.

## Global Constraints

- Run the full suite (`php artisan test`) after each task; the baseline going into this plan is **233 passing / 19 failing** (all 19 pre-existing, unrelated to this work). Any *new* failure is a regression to fix before moving on.
- Per spec §14, Payroll gets **four submodules**: `payroll_processing`, `payroll_templates`, `loans`, `payroll_settings` — unlike Plan D's three module-only areas. `payroll_settings` covers **both** `PayrollSettingController` (`/payroll-settings`) **and** `PayrollItemController` (`/libraries/payroll-items`) — grouped to match the frontend's own `Payroll/Index.vue` tab structure, not the backend's historical route grouping (payroll-items currently sits in the *Libraries* `role:Administrator` group in `routes/web.php`, payroll-settings sits in the *Payroll* one — both keep their existing outer gate unchanged, only the new inner `permission:payroll,payroll_settings,...` layer is shared between them).
- Access levels: Encoder (create/edit), Approver (status updates on Payroll runs and Loans only — no other submodule has an approval workflow), View (list/show/print/available-employees), Admin (delete).
- **All Payroll routes already sit behind `role:Administrator`** — the new middleware is a second layer, same as Inventory/User Management, not a sole gate like Sales/Employees/Customers. Every test in this plan gives its fixture user the `Administrator` role and varies only the new fine-grained grant.
- `sales-incentives` (a tab in `Payroll/Index.vue`, backed by a separate `SalesIncentivesController`) is **out of scope** — same treatment as Sales' `remittance`/`sales-reports` tabs, left unfiltered.
- Per spec §10, the seeder task must be **reviewed with the user before running on production** — do not run it there without asking first, and deploy + run it immediately after the first backend-enforcement task lands (this plan's Task 3), not after the whole plan ships.

---

### Task 1: Seed the Payroll submodule catalog

**Files:**
- Modify: `database/seeders/ModulesAndSubmodulesSeeder.php`
- Test: `tests/Feature/Permissions/ModulesAndSubmodulesSeederTest.php` (extend the existing test)

**Interfaces:**
- Consumes: `Module`, `Submodule` (Plan A)
- Produces: four new `submodules` rows under the existing `payroll` module: `payroll_processing`, `payroll_templates`, `loans`, `payroll_settings`.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/Permissions/ModulesAndSubmodulesSeederTest.php`, add a new test method (leave the existing two untouched):

```php
    public function test_seeds_payroll_submodules(): void
    {
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $payroll = Module::where('key', 'payroll')->firstOrFail();
        $this->assertEquals(
            ['payroll_processing', 'payroll_templates', 'loans', 'payroll_settings'],
            $payroll->submodules->pluck('key')->all()
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ModulesAndSubmodulesSeederTest`
Expected: FAIL — `test_seeds_payroll_submodules` fails (`payroll` has zero submodules today); the two pre-existing tests still pass.

- [ ] **Step 3: Update the seeder**

In `database/seeders/ModulesAndSubmodulesSeeder.php`, change:
```php
            ['key' => 'payroll', 'name' => 'Payroll', 'sort_order' => 3, 'submodules' => []],
```
to:
```php
            ['key' => 'payroll', 'name' => 'Payroll', 'sort_order' => 3, 'submodules' => [
                ['key' => 'payroll_processing', 'name' => 'Payroll Processing', 'sort_order' => 1],
                ['key' => 'payroll_templates', 'name' => 'Payroll Templates', 'sort_order' => 2],
                ['key' => 'loans', 'name' => 'Loans', 'sort_order' => 3],
                ['key' => 'payroll_settings', 'name' => 'Payroll Settings', 'sort_order' => 4],
            ]],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ModulesAndSubmodulesSeederTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 234 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add database/seeders/ModulesAndSubmodulesSeeder.php tests/Feature/Permissions/ModulesAndSubmodulesSeederTest.php
git commit -m "Seed the Payroll submodule catalog

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Run this on production right away too** (idempotent via `firstOrCreate`, purely additive — same discipline as Plan A's original catalog seeding) so the new submodules actually appear in the Manage Permissions screen there.

---

### Task 2: Rollout-safety default permission grants

**Files:**
- Create: `database/seeders/PayrollDefaultPermissionsSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Permissions/PayrollDefaultPermissionsSeederTest.php`

**Interfaces:**
- Consumes: `Module`, `RolePermission`, `ListRole` (Plan A)
- Produces: `Administrator` → `admin`, module-wide (all four submodules) on `payroll` — the only role that actually satisfies the existing `role:Administrator` gate on every Payroll route today. `Super Admin` needs no row.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Database\Seeders\PayrollDefaultPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDefaultPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    public function test_administrator_gets_admin_module_wide_on_payroll(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(PayrollDefaultPermissionsSeeder::class);

        $role = ListRole::where('name', 'Administrator')->firstOrFail();
        $module = \App\Models\Module::where('key', 'payroll')->firstOrFail();
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

        $this->seed(PayrollDefaultPermissionsSeeder::class);
        $this->seed(PayrollDefaultPermissionsSeeder::class);

        $this->assertEquals(1, RolePermission::count());
    }

    public function test_seeder_skips_roles_that_dont_exist_in_this_environment(): void
    {
        $this->seed(PayrollDefaultPermissionsSeeder::class);

        $this->assertEquals(0, RolePermission::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PayrollDefaultPermissionsSeederTest`
Expected: FAIL — class `Database\Seeders\PayrollDefaultPermissionsSeeder` not found.

- [ ] **Step 3: Write the seeder**

`database/seeders/PayrollDefaultPermissionsSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class PayrollDefaultPermissionsSeeder extends Seeder
{
    /**
     * Rollout-safety seeding (design spec §10/§14): Administrator is the
     * only role that actually satisfies the existing role:Administrator
     * gate on every Payroll route today, so it's the only one that needs a
     * preserved-access grant here — no role gets new access it doesn't
     * already have. Super Admin needs no row — PermissionService bypasses
     * it unconditionally.
     */
    public function run(): void
    {
        $module = Module::where('key', 'payroll')->first();
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
        $this->call(EmployeesCustomersUsersDefaultPermissionsSeeder::class);
```
to:
```php
        $this->call(EmployeesCustomersUsersDefaultPermissionsSeeder::class);
        $this->call(PayrollDefaultPermissionsSeeder::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PayrollDefaultPermissionsSeederTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 237 passing / 19 failing

- [ ] **Step 7: Commit**

```bash
git add database/seeders/PayrollDefaultPermissionsSeeder.php database/seeders/DatabaseSeeder.php \
        tests/Feature/Permissions/PayrollDefaultPermissionsSeederTest.php
git commit -m "Seed rollout-safety default Payroll permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Do not run this seeder on production yet** — surface it to the user for review before running it there.

---

### Task 3: Payroll Processing backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Payroll/PayrollProcessingPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `payroll,payroll_processing,view|encoder|approver|admin` enforcement on `/payrolls` and its status/print routes.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Payroll;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Payroll;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollProcessingPermissionTest extends TestCase
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
            $module = Module::where('key', 'payroll')->firstOrFail();
            $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submoduleId, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makePayroll(): Payroll
    {
        return Payroll::create([
            'payroll_no' => 'PR-TEST-' . uniqid(),
            'pay_period_start' => now()->toDateString(),
            'pay_period_end' => now()->addDays(14)->toDateString(),
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant('loans', 'view');

        $this->actingAs($user)->getJson('/payrolls?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('payroll_processing', 'view');

        $this->actingAs($user)->getJson('/payrolls?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('payroll_processing', 'view');

        $this->actingAs($user)->post('/payrolls', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('payroll_processing', 'encoder');

        $response = $this->actingAs($user)->post('/payrolls', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_update_status_denied_without_approver_grant(): void
    {
        $payroll = $this->makePayroll();
        $user = $this->administratorWithGrant('payroll_processing', 'encoder');

        $this->actingAs($user)->put("/payrolls/{$payroll->id}/status")->assertForbidden();
    }

    public function test_update_status_allowed_with_approver_grant(): void
    {
        $payroll = $this->makePayroll();
        $user = $this->administratorWithGrant('payroll_processing', 'approver');

        $response = $this->actingAs($user)->put("/payrolls/{$payroll->id}/status");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $payroll = $this->makePayroll();
        $user = $this->administratorWithGrant('payroll_processing', 'approver');

        $this->actingAs($user)->delete("/payrolls/{$payroll->id}")->assertForbidden();
    }

    public function test_still_blocked_by_the_existing_role_gate(): void
    {
        $role = ListRole::create(['name' => 'Random Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'payroll')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id, 'submodule_id' => null, 'access_level' => 'admin',
        ]);

        $this->actingAs($user)->getJson('/payrolls?option=lists')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PayrollProcessingPermissionTest`
Expected: FAIL — the `denied` tests fail; `test_still_blocked_by_the_existing_role_gate` already passes.

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::resource('/payrolls', App\Http\Controllers\Modules\PayrollController::class);
```
to:
```php
        Route::resource('/payrolls', App\Http\Controllers\Modules\PayrollController::class)
            ->middlewareFor(['index', 'show'], 'permission:payroll,payroll_processing,view')
            ->middlewareFor(['store', 'update'], 'permission:payroll,payroll_processing,encoder')
            ->middlewareFor('destroy', 'permission:payroll,payroll_processing,admin');
```

Then, further down in the same group, change:
```php
        Route::get('/payrolls/{id}/print', [App\Http\Controllers\Modules\PayrollController::class, 'printPayroll']);
        Route::get('/sales-incentives', [App\Http\Controllers\Modules\SalesIncentivesController::class, 'index']);
        Route::put('/payrolls/{id}/status', [App\Http\Controllers\Modules\PayrollController::class, 'updateStatus']);
```
to:
```php
        Route::get('/payrolls/{id}/print', [App\Http\Controllers\Modules\PayrollController::class, 'printPayroll'])
            ->middleware('permission:payroll,payroll_processing,view');
        Route::get('/sales-incentives', [App\Http\Controllers\Modules\SalesIncentivesController::class, 'index']);
        Route::put('/payrolls/{id}/status', [App\Http\Controllers\Modules\PayrollController::class, 'updateStatus'])
            ->middleware('permission:payroll,payroll_processing,approver');
```

(`/sales-incentives` is left untouched — out of scope per this plan's Global Constraints.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PayrollProcessingPermissionTest`
Expected: PASS (8 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 245 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Payroll/PayrollProcessingPermissionTest.php
git commit -m "Enforce Payroll Processing permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Deploy and run Task 2's seeder on production right after this task** — this is the task that actually starts enforcing anything.

---

### Task 4: Payroll Templates backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Payroll/PayrollTemplatePermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `payroll,payroll_templates,view|encoder|admin` enforcement on `/payroll-templates` and its employee-assignment routes.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Payroll;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\PayrollTemplate;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTemplatePermissionTest extends TestCase
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
            $module = Module::where('key', 'payroll')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'payroll_templates')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeTemplate(): PayrollTemplate
    {
        return PayrollTemplate::create([
            'name' => 'Test Template ' . uniqid(),
            'created_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/payroll-templates?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->getJson('/payroll-templates?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/payroll-templates', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $response = $this->actingAs($user)->post('/payroll-templates', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_add_employees_denied_without_encoder_grant(): void
    {
        $template = $this->makeTemplate();
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post("/payroll-templates/{$template->id}/add-employees", [])->assertForbidden();
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $template = $this->makeTemplate();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete("/payroll-templates/{$template->id}")->assertForbidden();
    }

    public function test_destroy_allowed_with_admin_grant(): void
    {
        $template = $this->makeTemplate();
        $user = $this->administratorWithGrant('admin');

        $response = $this->actingAs($user)->delete("/payroll-templates/{$template->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PayrollTemplatePermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::get('/payroll-templates/available-employees', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'getAvailableEmployees']);
        Route::resource('/payroll-templates', App\Http\Controllers\Modules\PayrollTemplateController::class);
        Route::post('/payroll-templates/{templateId}/add-employees', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'addEmployees']);
        Route::delete('/payroll-templates/{templateId}/employees/{employeeId}', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'removeEmployee']);
```
to:
```php
        Route::get('/payroll-templates/available-employees', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'getAvailableEmployees'])
            ->middleware('permission:payroll,payroll_templates,view');
        Route::resource('/payroll-templates', App\Http\Controllers\Modules\PayrollTemplateController::class)
            ->middlewareFor('index', 'permission:payroll,payroll_templates,view')
            ->middlewareFor(['store', 'update'], 'permission:payroll,payroll_templates,encoder')
            ->middlewareFor('destroy', 'permission:payroll,payroll_templates,admin');
        Route::post('/payroll-templates/{templateId}/add-employees', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'addEmployees'])
            ->middleware('permission:payroll,payroll_templates,encoder');
        Route::delete('/payroll-templates/{templateId}/employees/{employeeId}', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'removeEmployee'])
            ->middleware('permission:payroll,payroll_templates,encoder');
```

(`show` isn't in `middlewareFor`'s `index`/`view` group here because `PayrollTemplateController` has no `show()` method — confirmed by reading the controller; the resource's `show`/`create`/`edit` routes would 404/error if ever hit, same as other dead resource actions found in Plans B–D, and are left alone.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PayrollTemplatePermissionTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 252 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Payroll/PayrollTemplatePermissionTest.php
git commit -m "Enforce Payroll Templates permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Loans backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Payroll/LoanPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `payroll,loans,view|encoder|approver|admin` enforcement on `/loans` and `/loan-payments`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\ListRole;
use App\Models\Loan;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanPermissionTest extends TestCase
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
            $module = Module::where('key', 'payroll')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'loans')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeLoan(): Loan
    {
        $employee = Employee::create([
            'lastname' => 'Test', 'firstname' => 'Employee' . uniqid(),
            'mobile' => '09000000000', 'birthdate' => '1990-01-01', 'sex' => 'male', 'religion' => 'n/a',
        ]);

        return Loan::create([
            'employee_id' => $employee->id, 'loan_type' => 'Personal',
            'amount' => 5000, 'interest_rate' => 0, 'term_months' => 6,
            'added_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/loans?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->getJson('/loans?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/loans', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $response = $this->actingAs($user)->post('/loans', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_update_status_denied_without_approver_grant(): void
    {
        $loan = $this->makeLoan();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->put("/loans/{$loan->id}/status")->assertForbidden();
    }

    public function test_update_status_allowed_with_approver_grant(): void
    {
        $loan = $this->makeLoan();
        $user = $this->administratorWithGrant('approver');

        $response = $this->actingAs($user)->put("/loans/{$loan->id}/status");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $loan = $this->makeLoan();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete("/loans/{$loan->id}")->assertForbidden();
    }

    public function test_loan_payments_list_gated_by_the_same_loans_grant(): void
    {
        $denied = $this->administratorWithGrant(null);
        $this->actingAs($denied)->getJson('/loan-payments?option=lists')->assertForbidden();

        $allowed = $this->administratorWithGrant('view');
        $this->actingAs($allowed)->getJson('/loan-payments?option=lists')->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LoanPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::resource('/loans', App\Http\Controllers\Modules\LoanController::class);
        Route::resource('/loan-payments', App\Http\Controllers\Modules\LoanPaymentController::class);
```
to:
```php
        Route::resource('/loans', App\Http\Controllers\Modules\LoanController::class)
            ->middlewareFor('index', 'permission:payroll,loans,view')
            ->middlewareFor(['store', 'update'], 'permission:payroll,loans,encoder')
            ->middlewareFor('destroy', 'permission:payroll,loans,admin');
        Route::resource('/loan-payments', App\Http\Controllers\Modules\LoanPaymentController::class)
            ->middlewareFor('index', 'permission:payroll,loans,view')
            ->middlewareFor(['store', 'update'], 'permission:payroll,loans,encoder')
            ->middlewareFor('destroy', 'permission:payroll,loans,admin');
```

Then, further down in the same group, change:
```php
        Route::put('/loans/{id}/status', [App\Http\Controllers\Modules\LoanController::class, 'updateStatus']);
```
to:
```php
        Route::put('/loans/{id}/status', [App\Http\Controllers\Modules\LoanController::class, 'updateStatus'])
            ->middleware('permission:payroll,loans,approver');
```

(Neither `LoanController` nor `LoanPaymentController` has a `show()` method — confirmed by reading both — so `middlewareFor` here only covers `index`/`store`/`update`/`destroy`, matching the actual controller surface, same reasoning as Task 4's `PayrollTemplateController`.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LoanPermissionTest`
Expected: PASS (8 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 260 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Payroll/LoanPermissionTest.php
git commit -m "Enforce Loans permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Payroll Settings backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Payroll/PayrollSettingsPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `payroll,payroll_settings,view|encoder|admin` enforcement on `/payroll-settings` (Payroll group) and `/libraries/payroll-items` (Libraries group) — two different outer route groups, same inner submodule, per spec §14.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Payroll;

use App\Models\ListRole;
use App\Models\ListPayrollItem;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollSettingsPermissionTest extends TestCase
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
            $module = Module::where('key', 'payroll')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'payroll_settings')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makePayrollItem(): ListPayrollItem
    {
        return ListPayrollItem::create(['slug' => 'test-item-' . uniqid(), 'name' => 'Test Item', 'type' => 'earning']);
    }

    public function test_payroll_settings_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/payroll-settings?option=lists')->assertForbidden();
    }

    public function test_payroll_settings_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->getJson('/payroll-settings?option=lists')->assertOk();
    }

    public function test_payroll_settings_update_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->put('/payroll-settings/1', [])->assertForbidden();
    }

    public function test_payroll_items_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/libraries/payroll-items?option=lists')->assertForbidden();
    }

    public function test_payroll_items_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->getJson('/libraries/payroll-items?option=lists')->assertOk();
    }

    public function test_payroll_items_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/libraries/payroll-items', [])->assertForbidden();
    }

    public function test_payroll_items_toggle_active_denied_without_encoder_grant(): void
    {
        $item = $this->makePayrollItem();
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->patch("/libraries/payroll-items/{$item->id}/toggle-active")->assertForbidden();
    }

    public function test_payroll_items_destroy_denied_without_admin_grant(): void
    {
        $item = $this->makePayrollItem();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->delete("/libraries/payroll-items/{$item->id}")->assertForbidden();
    }

    public function test_payroll_items_destroy_allowed_with_admin_grant(): void
    {
        $item = $this->makePayrollItem();
        $user = $this->administratorWithGrant('admin');

        $response = $this->actingAs($user)->delete("/libraries/payroll-items/{$item->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PayrollSettingsPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::resource('/payroll-settings', App\Http\Controllers\Modules\PayrollSettingController::class);
```
to:
```php
        Route::resource('/payroll-settings', App\Http\Controllers\Modules\PayrollSettingController::class)
            ->middlewareFor('index', 'permission:payroll,payroll_settings,view')
            ->middlewareFor('update', 'permission:payroll,payroll_settings,encoder');
```

(`PayrollSettingController` has only `index()`/`update()` — confirmed by reading it — no `store`/`destroy`/`show`, so those are the only two actions gated.)

Then, in the **Libraries** `role:Administrator` group, change:
```php
        Route::resource('/libraries/payroll-items', App\Http\Controllers\Libraries\PayrollItemController::class);
```
to:
```php
        Route::resource('/libraries/payroll-items', App\Http\Controllers\Libraries\PayrollItemController::class)
            ->middlewareFor('index', 'permission:payroll,payroll_settings,view')
            ->middlewareFor(['store', 'update'], 'permission:payroll,payroll_settings,encoder')
            ->middlewareFor('destroy', 'permission:payroll,payroll_settings,admin');
```

And change:
```php
        Route::patch('/libraries/payroll-items/{id}/toggle-active', [App\Http\Controllers\Libraries\PayrollItemController::class, 'toggleActive']);
```
to:
```php
        Route::patch('/libraries/payroll-items/{id}/toggle-active', [App\Http\Controllers\Libraries\PayrollItemController::class, 'toggleActive'])
            ->middleware('permission:payroll,payroll_settings,encoder');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PayrollSettingsPermissionTest`
Expected: PASS (9 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 269 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Payroll/PayrollSettingsPermissionTest.php
git commit -m "Enforce Payroll Settings permissions (incl. Payroll Items)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**This closes the last backend gap for this plan — deploy immediately.**

---

### Task 7: Frontend — replace hardcoded role checks, gate buttons

**Files:**
- Modify: `resources/js/Pages/Modules/Payroll/Index.vue` (tab-level Create/nav, if any — inspect first)
- Modify: `resources/js/Pages/Modules/Payroll/Components/Payrolls/Index.vue`
- Modify: `resources/js/Pages/Modules/Payroll/Components/Templates/Index.vue`
- Modify: `resources/js/Pages/Modules/Payroll/Components/Loans/Index.vue`
- Modify: `resources/js/Pages/Modules/Payroll/Components/Settings/Index.vue`
- Modify: `resources/js/Pages/Modules/Payroll/Components/Items/Index.vue`

**Interfaces:**
- Consumes: `this.can('payroll', '<submodule>', '<level>')` (Plan A's `permissionsMixin`, 3-argument submodule form — unlike Plan D, Payroll has real submodules)

**Note on this task, same as Plan D's Task 5:** these six files' exact current contents were not read while grounding this plan (time-boxed to the backend). Each step is a *procedure*, not a pre-written diff:

- [ ] **Step 1: For each of the 6 files, grep for its action buttons and any hardcoded role checks**

Run: `grep -n "@click=\"open\|acct-btn-primary\|create-btn\|action-btn-delete\|action-btn-edit\|roles.includes" <file>` for each — same command shape used in every prior plan's grounding pass.

- [ ] **Step 2: Gate each Create/Add, Edit, and Delete button**

Using the submodule mapping from this plan's Global Constraints (`Payrolls/Index.vue` → `payroll_processing`; `Templates/Index.vue` → `payroll_templates`; `Loans/Index.vue` → `loans`; `Settings/Index.vue` and `Items/Index.vue` → `payroll_settings`), add:
- Create/Add buttons: `v-if="can('payroll', '<submodule>', 'encoder')"`
- Edit buttons: `v-if="can('payroll', '<submodule>', 'encoder')"` (AND-combined with any existing condition, never replacing it)
- Delete buttons: `v-if="can('payroll', '<submodule>', 'admin')"` (AND-combined likewise)
- Status-update/Approve buttons found on `Payrolls/Index.vue` or `Loans/Index.vue`: `v-if="can('payroll', 'payroll_processing', 'approver')"` / `v-if="can('payroll', 'loans', 'approver')"` respectively

- [ ] **Step 3: Replace any hardcoded `roles.includes(...)` checks found in Step 1**

If any exist, replace with the equivalent `can()` call. If none exist, skip — do not invent one (matches every prior plan's discipline on this point).

- [ ] **Step 4: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 269 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 5: Rebuild assets**

Run: `npm run build`

- [ ] **Step 6: Manually verify with a real browser session**

1. Log in as `administrator@example.com` (only role with a seeded grant — `admin` module-wide on payroll) — confirm every Create/Edit/Delete/Approve button across all five tabs (Payroll Runs, Templates, Loans, Settings, Items) is visible.
2. Confirm a zero-grant user gets no action buttons on any Payroll tab and a 403 on direct navigation to `/payrolls`, `/payroll-templates`, `/loans`, `/payroll-settings`, `/libraries/payroll-items`.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Modules/Payroll public/build
git commit -m "Wire Payroll buttons to can()

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 8: Frontend — nav filtering

**Files:**
- Modify: `resources/js/Shared/Layouts/Components/Menu.vue`
- Modify: `resources/js/Pages/Modules/Payroll/Index.vue`

**Interfaces:**
- Consumes: `this.canAny('payroll'[, '<submodule>'])` (Plan A's `permissionsMixin`)

- [ ] **Step 1: Filter the global nav Payroll link**

Change:
```html
                <li class="nav-item" v-if="$page.props.roles.includes('Super Admin') || $page.props.roles.includes('HR Manager')">
                    <Link href="/payrolls" class="nav-link menu-link"
```
to:
```html
                <li class="nav-item" v-if="canAny('payroll')">
                    <Link href="/payrolls" class="nav-link menu-link"
```

- [ ] **Step 2: Filter the in-page Payroll tabs**

Inspect `Payroll/Index.vue`'s `tabs:` array and tab-bar `v-for` (read the file first — its exact current contents weren't captured while grounding this plan). Add a `visibleTabs` computed property following the exact pattern used in Sales' `Index.vue` (Plan B, Task 7) and Inventory's `Index.vue` (Plan C, Task 7):

```js
  computed: {
    visibleTabs() {
      // sales_incentives is outside this pilot's submodule catalog (spec
      // §14) — stays visible to anyone who can see Payroll at all.
      const tabToSubmodule = {
        payroll_management: 'payroll_processing',
        payroll_templates: 'payroll_templates',
        loan_management: 'loans',
        payroll_items: 'payroll_settings',
      };
      return this.tabs.filter((tab) => {
        const submoduleKey = tabToSubmodule[tab.id];
        if (!submoduleKey) {
          return true;
        }
        return this.canAny('payroll', submoduleKey);
      });
    },
  },
```

(There is no separate `payroll_settings`-tab-id-to-Settings-component mapping needed beyond `payroll_items` above **unless** `Settings/Index.vue` is reached via its own distinct tab id rather than being nested inside the `payroll_items` tab — confirm which by reading the file's `tabs:` array before writing this computed property, and add a second `<tab-id>: 'payroll_settings'` entry to `tabToSubmodule` if `Settings` does have its own separate tab id.)

Change the tab-bar `v-for` from `v-for="tab in tabs"` to `v-for="tab in visibleTabs"` (confirm the exact surrounding markup first, same as every prior nav-filtering task).

- [ ] **Step 3: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 269 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 4: Rebuild assets**

Run: `npm run build`

- [ ] **Step 5: Manually verify with a real browser session**

1. Log in as a zero-grant user — confirm "Payroll" is gone from the left nav, and navigating to `/payrolls` directly now 403s.
2. Log in as `administrator@example.com` — confirm "Payroll" appears and every tab (Payroll Runs, Templates, Loans, Settings/Items, and the always-visible Sales Incentives) is visible.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Shared/Layouts/Components/Menu.vue resources/js/Pages/Modules/Payroll/Index.vue public/build
git commit -m "Filter Payroll nav link and in-page tabs by permission

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage** (against `docs/superpowers/specs/2026-08-14-dynamic-role-access-design.md` §14):
- Four-submodule taxonomy, matching both controller boundaries and the frontend's own tab grouping → Task 1
- `payroll_settings` spanning two different outer route groups (Payroll's `role:Administrator` and Libraries') → Task 6, called out explicitly in Global Constraints
- Rollout safety (Administrator-only, since it's the sole role actually satisfying today's gate) → Task 2
- `sales-incentives` left unfiltered → Task 3's route-edit note and Task 8's `visibleTabs` comment
- Second-layer pattern (existing `role:Administrator` untouched) → every backend task's `test_still_blocked_by_the_existing_role_gate`-equivalent test (Task 3 has one explicitly; Tasks 4–6 rely on the same Administrator-role fixture requirement, consistent with Plan C/D precedent)

**Placeholder scan:** Task 7 and part of Task 8 (Step 2) are deliberate, explicitly-flagged exceptions to "no placeholders" — same reasoning as Plan D's Task 5: six frontend files (plus `Payroll/Index.vue`'s tab array) weren't read while grounding this plan, time-boxed in favor of thoroughly grounding the backend (the real security boundary). This is called out in each step's own text, not silently assumed. Every other task has complete, real code, verified against actual controller/route contents read during this plan's investigation.

**Type consistency:** `can()`/`canAny()` calls (Tasks 7–8) use the exact submodule keys defined in Task 1 (`payroll_processing`, `payroll_templates`, `loans`, `payroll_settings`) and seeded via `ModulesAndSubmodulesSeeder`. The `permission:...` middleware strings (Tasks 3–6) match `PermissionMiddleware`'s `module,submodule,level` argument order exactly.

---

## After This Plan

**Accounting** (~35-40 routes, needs its own submodule taxonomy decided with the user given its size — candidates identified during Plan A/B/C/D's investigation: Financial Reports, Journal Entries, Chart of Accounts/Settings, Cash Management, Petty Cash, Expenses, Bank Reconciliation, Remittances) remains the last module in the original design spec's deferred list, per spec §13's stated sequencing.
