# Wire Employees, Customers, User Management (Plan D) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn on real backend + frontend permission enforcement for Employees (incl. Salaries/Positions), Customers, and User Management (`/users`), per the design spec's §13 addendum — the first of the post-pilot modules, chosen because they're small, flat, single-purpose CRUD areas with no submodule structure, two of which currently have no role gate at all.

**Architecture:** Same framework as Plans A/B/C (`PermissionService`, `PermissionMiddleware`, `can()`/`canAny()`). Unlike Sales, every controller here is already clean and single-purpose (no request-field action multiplexing), so this plan leans entirely on declarative `permission:...` route middleware via `Route::resource(...)->middlewareFor(...)`, matching Inventory's pattern (Plan C) rather than Sales' `FormRequest::authorize()` workaround.

**Tech Stack:** Same as Plans A/B/C — Laravel 11, Pest/PHPUnit (`RefreshDatabase`), Vue 3 Options API + Inertia.

## Global Constraints

- Run the full suite (`php artisan test`) after each task; the baseline going into this plan is **208 passing / 19 failing** (all 19 pre-existing, unrelated to this work). Any *new* failure is a regression to fix before moving on.
- Per spec §13: **Employees, Customers, and User Management are module-only — no submodules.** Every permission check in this plan uses the 2-argument middleware form (`permission:module,level`) or `PermissionService::userHasAccess($user, 'module', null, 'level')`, never a submodule key.
- Access levels used throughout: Encoder (create/edit), View (list/detail/summary views), Admin (delete). **No Approver tier** — none of these three areas has an approval workflow.
- **Salaries and Positions are gated under the `employees` module** (spec §13) — `permission:employees,encoder`/`view`/`admin`, not a new module. This is a UI/admin-grouping classification only; it does not expand anyone's current access.
- **`/libraries/roles` and its permissions endpoints are explicitly untouched** — stays `role:Administrator`-only, no new permission layer (spec §13's stated reason: don't risk locking an admin out of the tool that fixes permission problems).
- Employees and Customers currently have **no route-level role gate at all** — the new `permission:...` middleware becomes their only gate (mirrors Sales; rollout-safety seeding in Task 1 is what prevents a lockout). Users, Salaries, and Positions already sit behind `role:Administrator` — the new middleware layers on top of that, unchanged (mirrors Inventory).
- Per spec §10, the Task 1 seeder must be **reviewed with the user before running on production** — do not run it there without asking first, and given the lesson from Plan B/C: deploy + run the seeder immediately after the first backend-enforcement task lands, not after the whole plan ships.

---

### Task 1: Rollout-safety default permission grants

**Files:**
- Create: `database/seeders/EmployeesCustomersUsersDefaultPermissionsSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Permissions/EmployeesCustomersUsersDefaultPermissionsSeederTest.php`

**Interfaces:**
- Consumes: `Module`, `RolePermission`, `ListRole` (Plan A)
- Produces: seeded `role_permissions` rows preserving today's real access. `Administrator` → `admin` module-wide on `employees`, `customers`, `user_management` (matches the comprehensive self-granted access already observed on production, and the consistent "Administrator gets admin on every module" precedent from Plans B/C). `HR Manager` → `admin` on `employees` (they have full unrestricted access today, no gate exists). `Mini Admin` → `admin` on `customers` (same reasoning — no gate exists today). `Super Admin` needs no row.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use Database\Seeders\EmployeesCustomersUsersDefaultPermissionsSeeder;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeesCustomersUsersDefaultPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function grantLevels(string $roleName, string $moduleKey): array
    {
        $role = ListRole::where('name', $roleName)->firstOrFail();
        $module = \App\Models\Module::where('key', $moduleKey)->firstOrFail();

        return RolePermission::where('role_id', $role->id)
            ->where('module_id', $module->id)
            ->whereNull('submodule_id')
            ->pluck('access_level')
            ->sort()
            ->values()
            ->all();
    }

    public function test_administrator_gets_admin_on_all_three_modules(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Administrator', 'employees'));
        $this->assertEquals(['admin'], $this->grantLevels('Administrator', 'customers'));
        $this->assertEquals(['admin'], $this->grantLevels('Administrator', 'user_management'));
    }

    public function test_hr_manager_gets_admin_on_employees_only(): void
    {
        ListRole::create(['name' => 'HR Manager', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('HR Manager', 'employees'));
        $this->assertEquals(0, RolePermission::whereHas('role', fn ($q) => $q->where('name', 'HR Manager'))
            ->whereHas('module', fn ($q) => $q->where('key', 'customers'))->count());
    }

    public function test_mini_admin_gets_admin_on_customers_only(): void
    {
        ListRole::create(['name' => 'Mini Admin', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Mini Admin', 'customers'));
        $this->assertEquals(0, RolePermission::whereHas('role', fn ($q) => $q->where('name', 'Mini Admin'))
            ->whereHas('module', fn ($q) => $q->where('key', 'employees'))->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);
        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Administrator', 'employees'));
    }

    public function test_seeder_skips_roles_that_dont_exist_in_this_environment(): void
    {
        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);

        $this->assertEquals(0, RolePermission::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmployeesCustomersUsersDefaultPermissionsSeederTest`
Expected: FAIL — class `Database\Seeders\EmployeesCustomersUsersDefaultPermissionsSeeder` not found.

- [ ] **Step 3: Write the seeder**

`database/seeders/EmployeesCustomersUsersDefaultPermissionsSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class EmployeesCustomersUsersDefaultPermissionsSeeder extends Seeder
{
    /**
     * Rollout-safety seeding (design spec §10/§13): grants that preserve
     * exactly the access these roles already have today — Employees and
     * Customers have no route-level gate at all right now, so without this
     * seeding, turning on enforcement would immediately lock out HR Manager
     * and Mini Admin/Administrator respectively. Super Admin needs no row —
     * PermissionService bypasses it unconditionally.
     */
    public function run(): void
    {
        $grants = [
            ['role' => 'Administrator', 'module' => 'employees', 'level' => 'admin'],
            ['role' => 'Administrator', 'module' => 'customers', 'level' => 'admin'],
            ['role' => 'Administrator', 'module' => 'user_management', 'level' => 'admin'],
            ['role' => 'HR Manager', 'module' => 'employees', 'level' => 'admin'],
            ['role' => 'Mini Admin', 'module' => 'customers', 'level' => 'admin'],
        ];

        foreach ($grants as $grant) {
            $module = Module::where('key', $grant['module'])->first();
            if (!$module) {
                continue; // modules catalog not seeded yet — nothing to grant against.
            }

            $role = ListRole::where('name', $grant['role'])->first();
            if (!$role) {
                continue; // role doesn't exist in this environment — skip rather than fail the seeder.
            }

            RolePermission::firstOrCreate([
                'role_id' => $role->id,
                'module_id' => $module->id,
                'submodule_id' => null,
                'access_level' => $grant['level'],
            ]);
        }
    }
}
```

- [ ] **Step 4: Register it in `DatabaseSeeder.php`**

In `database/seeders/DatabaseSeeder.php`, change:
```php
        $this->call(InventoryDefaultPermissionsSeeder::class);
```
to:
```php
        $this->call(InventoryDefaultPermissionsSeeder::class);
        $this->call(EmployeesCustomersUsersDefaultPermissionsSeeder::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=EmployeesCustomersUsersDefaultPermissionsSeederTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 213 passing / 19 failing

- [ ] **Step 7: Commit**

```bash
git add database/seeders/EmployeesCustomersUsersDefaultPermissionsSeeder.php database/seeders/DatabaseSeeder.php \
        tests/Feature/Permissions/EmployeesCustomersUsersDefaultPermissionsSeederTest.php
git commit -m "Seed rollout-safety default Employees/Customers/User Management permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Do not run this seeder on production yet** — surface it to the user for review before running it there.

---

### Task 2: Employees (incl. Salaries, Positions) backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Employees/EmployeePermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `employees,view|encoder|admin` enforcement on `/employees`, `/employees/{id}/incentives-summary`, `/libraries/salaries`, `/libraries/positions` (incl. `/libraries/positions/{id}/toggle-active`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function userWithGrant(?string $level): User
    {
        $role = ListRole::create(['name' => 'Test Role ' . uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'employees')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => null, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeEmployee(): Employee
    {
        return Employee::create([
            'lastname' => 'Test', 'firstname' => 'Employee' . uniqid(),
            'mobile' => '09000000000', 'birthdate' => '1990-01-01', 'sex' => 'male', 'religion' => 'n/a',
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->userWithGrant(null);

        $this->actingAs($user)->getJson('/employees?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->userWithGrant('view');

        $this->actingAs($user)->getJson('/employees?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->userWithGrant('view');

        $this->actingAs($user)->post('/employees', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->userWithGrant('encoder');

        $response = $this->actingAs($user)->post('/employees', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $employee = $this->makeEmployee();
        $user = $this->userWithGrant('encoder');

        $this->actingAs($user)->delete("/employees/{$employee->id}")->assertForbidden();
    }

    public function test_destroy_allowed_with_admin_grant(): void
    {
        $employee = $this->makeEmployee();
        $user = $this->userWithGrant('admin');

        $response = $this->actingAs($user)->delete("/employees/{$employee->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_salaries_list_gated_by_the_same_employees_grant(): void
    {
        $denied = $this->userWithGrant(null);
        $this->actingAs($denied)->getJson('/libraries/salaries?option=lists')->assertForbidden();

        $allowed = $this->userWithGrant('view');
        $this->actingAs($allowed)->getJson('/libraries/salaries?option=lists')->assertOk();
    }

    public function test_positions_list_gated_by_the_same_employees_grant(): void
    {
        $denied = $this->userWithGrant(null);
        $this->actingAs($denied)->getJson('/libraries/positions?option=lists')->assertForbidden();

        $allowed = $this->userWithGrant('view');
        $this->actingAs($allowed)->getJson('/libraries/positions?option=lists')->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmployeePermissionTest`
Expected: FAIL — the `denied` tests fail; the `salaries`/`positions` tests fail with 403 (they already require `role:Administrator`, and the test user doesn't hold that role, so **read Global Constraints carefully here**: since these two currently sit behind `role:Administrator`, the "denied" half of `test_salaries_list_gated_by_the_same_employees_grant`/`test_positions_list_gated_by_the_same_employees_grant` will actually pass immediately (403 from the *existing* role gate) while the "allowed" half fails (still 403, since the fine-grained grant alone doesn't satisfy the outer `role:Administrator` check) — this is expected and gets fixed by giving the test user the Administrator role too. Fix this before Step 3 by changing those two test methods' `$allowed` fixture to also hold the `Administrator` role (mirroring Task 2's `warehouseManagerWithGrant` pattern from Plan C), since Salaries/Positions keep their outer role gate unlike Employees itself. Apply this fix now:

```php
    public function test_salaries_list_gated_by_the_same_employees_grant(): void
    {
        $denied = $this->userWithGrant(null);
        $this->actingAs($denied)->getJson('/libraries/salaries?option=lists')->assertForbidden();

        $adminRole = ListRole::firstOrCreate(['name' => 'Administrator'], ['type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $allowed = $this->userWithGrant('view');
        UserRole::create(['user_id' => $allowed->id, 'role_id' => $adminRole->id, 'is_active' => 1, 'added_by_id' => $allowed->id]);
        $this->actingAs($allowed)->getJson('/libraries/salaries?option=lists')->assertOk();
    }

    public function test_positions_list_gated_by_the_same_employees_grant(): void
    {
        $denied = $this->userWithGrant(null);
        $this->actingAs($denied)->getJson('/libraries/positions?option=lists')->assertForbidden();

        $adminRole = ListRole::firstOrCreate(['name' => 'Administrator'], ['type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $allowed = $this->userWithGrant('view');
        UserRole::create(['user_id' => $allowed->id, 'role_id' => $adminRole->id, 'is_active' => 1, 'added_by_id' => $allowed->id]);
        $this->actingAs($allowed)->getJson('/libraries/positions?option=lists')->assertOk();
    }
```

Replace the two methods in the test file with these versions before proceeding.

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
    Route::resource('/employees', App\Http\Controllers\Modules\EmployeeController::class);
    Route::get('/employees/{id}/incentives-summary', [App\Http\Controllers\Modules\EmployeeController::class, 'incentivesSummary']);
```
to:
```php
    Route::resource('/employees', App\Http\Controllers\Modules\EmployeeController::class)
        ->middlewareFor(['index', 'show'], 'permission:employees,view')
        ->middlewareFor(['store', 'update'], 'permission:employees,encoder')
        ->middlewareFor('destroy', 'permission:employees,admin');
    Route::get('/employees/{id}/incentives-summary', [App\Http\Controllers\Modules\EmployeeController::class, 'incentivesSummary'])
        ->middleware('permission:employees,view');
```

Then, inside the existing `role:Administrator` group, change:
```php
        Route::resource('/libraries/positions', App\Http\Controllers\Libraries\PositionController::class);
        Route::resource('/libraries/salaries', App\Http\Controllers\Libraries\SalaryController::class);
```
to:
```php
        Route::resource('/libraries/positions', App\Http\Controllers\Libraries\PositionController::class)
            ->middlewareFor(['index', 'show'], 'permission:employees,view')
            ->middlewareFor(['store', 'update'], 'permission:employees,encoder')
            ->middlewareFor('destroy', 'permission:employees,admin');
        Route::resource('/libraries/salaries', App\Http\Controllers\Libraries\SalaryController::class)
            ->middlewareFor(['index', 'show'], 'permission:employees,view')
            ->middlewareFor(['store', 'update'], 'permission:employees,encoder')
            ->middlewareFor('destroy', 'permission:employees,admin');
```

And change:
```php
        Route::patch('/libraries/positions/{id}/toggle-active', [App\Http\Controllers\Libraries\PositionController::class, 'toggleActive']);
```
to:
```php
        Route::patch('/libraries/positions/{id}/toggle-active', [App\Http\Controllers\Libraries\PositionController::class, 'toggleActive'])
            ->middleware('permission:employees,encoder');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=EmployeePermissionTest`
Expected: PASS (8 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 221 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Employees/EmployeePermissionTest.php
git commit -m "Enforce Employees permissions (incl. Salaries/Positions)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Deploy and run Task 1's seeder on production right after this task** — Employees has no prior gate, so this is the task that actually starts enforcing anything.

---

### Task 3: Customers backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Customers/CustomerPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `customers,view|encoder|admin` enforcement on `/customers` and its detail routes.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function userWithGrant(?string $level): User
    {
        $role = ListRole::create(['name' => 'Test Role ' . uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'customers')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => null, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Test Customer ' . uniqid(), 'address' => 'Test Address',
            'contact_number' => '09000000000', 'added_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->userWithGrant(null);

        $this->actingAs($user)->getJson('/customers?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->userWithGrant('view');

        $this->actingAs($user)->getJson('/customers?option=lists')->assertOk();
    }

    public function test_details_denied_without_view_grant(): void
    {
        $customer = $this->makeCustomer();
        $user = $this->userWithGrant(null);

        $this->actingAs($user)->getJson("/customers/{$customer->id}/details")->assertForbidden();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->userWithGrant('view');

        $this->actingAs($user)->post('/customers', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->userWithGrant('encoder');

        $response = $this->actingAs($user)->post('/customers', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $customer = $this->makeCustomer();
        $user = $this->userWithGrant('encoder');

        $this->actingAs($user)->delete("/customers/{$customer->id}")->assertForbidden();
    }

    public function test_destroy_allowed_with_admin_grant(): void
    {
        $customer = $this->makeCustomer();
        $user = $this->userWithGrant('admin');

        $response = $this->actingAs($user)->delete("/customers/{$customer->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CustomerPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
    Route::resource('/customers', App\Http\Controllers\Modules\CustomerController::class);
    Route::get('/customers/{id}/details', [App\Http\Controllers\Modules\CustomerController::class, 'details']);
    Route::get('/customers/{id}/order-summary', [App\Http\Controllers\Modules\CustomerController::class, 'orderSummary']);
    Route::get('/customers/{id}/purchase-history', [App\Http\Controllers\Modules\CustomerController::class, 'purchaseHistory']);
```
to:
```php
    Route::resource('/customers', App\Http\Controllers\Modules\CustomerController::class)
        ->middlewareFor(['index', 'show'], 'permission:customers,view')
        ->middlewareFor(['store', 'update'], 'permission:customers,encoder')
        ->middlewareFor('destroy', 'permission:customers,admin');
    Route::get('/customers/{id}/details', [App\Http\Controllers\Modules\CustomerController::class, 'details'])
        ->middleware('permission:customers,view');
    Route::get('/customers/{id}/order-summary', [App\Http\Controllers\Modules\CustomerController::class, 'orderSummary'])
        ->middleware('permission:customers,view');
    Route::get('/customers/{id}/purchase-history', [App\Http\Controllers\Modules\CustomerController::class, 'purchaseHistory'])
        ->middleware('permission:customers,view');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CustomerPermissionTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 228 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Customers/CustomerPermissionTest.php
git commit -m "Enforce Customers permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Deploy immediately after this task too** — Customers also has no prior gate.

---

### Task 4: User Management (`/users`) backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Users/UserManagementPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `user_management,view|encoder` enforcement on `/users`, layered inside the existing `role:Administrator` group (mirrors Inventory's pattern — `/users` already has a gate, this adds a second, finer one). No `admin` level: `UserController` has no `destroy()` method — deactivation, if ever added, would be a follow-up.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Users;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementPermissionTest extends TestCase
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
    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'user_management')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => null, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/users?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->getJson('/users?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/users', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $response = $this->actingAs($user)->post('/users', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_still_blocked_by_the_existing_role_gate(): void
    {
        // Right grant, wrong (non-Administrator) role — outer gate still applies.
        $role = ListRole::create(['name' => 'Random Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'user_management')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id, 'submodule_id' => null, 'access_level' => 'admin',
        ]);

        $this->actingAs($user)->getJson('/users?option=lists')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UserManagementPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing new is gated yet); `test_still_blocked_by_the_existing_role_gate` already passes (existing `role:Administrator` gate).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, inside the existing `role:Administrator` group, change:
```php
        Route::resource('/users', App\Http\Controllers\System\UserController::class);
```
to:
```php
        Route::resource('/users', App\Http\Controllers\System\UserController::class)
            ->middlewareFor(['index', 'show'], 'permission:user_management,view')
            ->middlewareFor(['store', 'update'], 'permission:user_management,encoder');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=UserManagementPermissionTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 233 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Users/UserManagementPermissionTest.php
git commit -m "Enforce User Management permissions on /users

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Frontend — replace hardcoded role checks, gate buttons

**Files:**
- Modify: `resources/js/Pages/Modules/Employees/Index.vue` (or equivalent — inspect first; see note below)
- Modify: `resources/js/Pages/Modules/Customers/Index.vue` (inspect first)
- Modify: `resources/js/Pages/Modules/Users/Index.vue` (inspect first)
- Modify: `resources/js/Pages/Modules/Libraries/Positions/Index.vue` (inspect first)
- Modify: `resources/js/Pages/Modules/Libraries/Salaries/Index.vue` (inspect first)

**Interfaces:**
- Consumes: `this.can(moduleKey, level)` (Plan A's `permissionsMixin`, 2-argument module-wide form since these modules have no submodules)

**Note on this task:** unlike Plans B/C, this codebase's exact button locations in these five files were not read while grounding this plan (time-boxed to the backend, which is the real security boundary). Each step below is a *procedure*, not a pre-written diff — follow it exactly for each file:

- [ ] **Step 1: For each of the 5 files above, find its Create/Add button and Delete button**

Run: `grep -n "@click=\"open\|acct-btn-primary\|create-btn\|action-btn-delete\|roles.includes" <file>` for each file listed. This mirrors exactly how Plans B/C located buttons (e.g. `grep -n "@click=\"open" resources/js/Pages/Modules/Sales/Components/SalesOrders/Index.vue`).

- [ ] **Step 2: Gate the Create/Add button**

Add `v-if="can('<module>', 'encoder')"` to the button that opens the create modal, where `<module>` is `employees`, `customers`, `user_management`, or `employees` (for both Positions and Salaries — they share the Employees grant per this plan's Global Constraints).

- [ ] **Step 3: Gate the Delete button**

Add `v-if="can('<module>', 'admin')"` (AND-combined with any existing `v-if` condition already on that button, exactly as done in every prior gating step in Plans B/C — never replace an existing condition, extend it with `&&`).

- [ ] **Step 4: Replace any hardcoded `roles.includes(...)` checks found in Step 1**

If any exist (mirroring Plan C's `canApprove`/`isAdmin` replacements), replace them with the equivalent `this.can('<module>', '<level>')` call. If none exist in these 5 files, skip this step — do not invent one.

- [ ] **Step 5: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 233 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 6: Rebuild assets**

Run: `npm run build`

- [ ] **Step 7: Manually verify with a real browser session**

1. Log in as `hrmanager@example.com` (Administrator gets `admin` on all three per Task 1; HR Manager gets `admin` on Employees only) — confirm Employees' Create/Delete buttons are visible, Customers/Users are not reachable.
2. Log in as `administrator@example.com` — confirm Employees, Customers, and Users all show Create/Delete buttons.
3. Confirm a zero-grant user gets no Create/Delete buttons anywhere and a 403 on direct navigation to `/employees`, `/customers`, `/users`.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Modules/Employees resources/js/Pages/Modules/Customers \
        resources/js/Pages/Modules/Users resources/js/Pages/Modules/Libraries/Positions \
        resources/js/Pages/Modules/Libraries/Salaries public/build
git commit -m "Wire Employees/Customers/User Management buttons to can()

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Frontend — nav filtering

**Files:**
- Modify: `resources/js/Shared/Layouts/Components/Menu.vue`

**Interfaces:**
- Consumes: `this.canAny(moduleKey)` (Plan A's `permissionsMixin`)

- [ ] **Step 1: Filter the Employees nav link**

Change:
```html
            <template
                v-if="$page.props.roles.includes('HR Manager') || $page.props.roles.includes('Super Admin')">
                <li class="nav-item">
                    <Link href="/employees" class="nav-link menu-link"
```
to:
```html
            <template
                v-if="canAny('employees')">
                <li class="nav-item">
                    <Link href="/employees" class="nav-link menu-link"
```

- [ ] **Step 2: Filter the Customers nav link**

Change:
```html
                <li class="nav-item" v-if="$page.props.roles.includes('Mini Admin') || $page.props.roles.includes('Administrator') || $page.props.roles.includes('Super Admin')">
                    <Link href="/customers" class="nav-link menu-link"
```
to:
```html
                <li class="nav-item" v-if="canAny('customers')">
                    <Link href="/customers" class="nav-link menu-link"
```

- [ ] **Step 3: Filter the Users ("Accounts") nav link**

Change:
```html
                <li class="nav-item"
                    v-if="$page.props.roles.includes('Super Admin') || $page.props.roles.includes('Human Resource Officer')">
                    <Link href="/users" class="nav-link menu-link"
```
to:
```html
                <li class="nav-item"
                    v-if="canAny('user_management')">
                    <Link href="/users" class="nav-link menu-link"
```

- [ ] **Step 4: Filter the Positions and Salaries submenu items**

Find the `<li class="nav-item submenu-item">` blocks for `/libraries/positions` and `/libraries/salaries` inside the Libraries dropdown (read the file first — their exact surrounding lines weren't captured while grounding this plan). Wrap each `<Link>` with `v-if="canAny('employees')"` on its parent `<li>`, following the exact same pattern as every other submenu item edit in this task (e.g. Step 1's `<li class="nav-item">` gets a `v-if` added, not a new wrapping element).

- [ ] **Step 5: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 233 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 6: Rebuild assets**

Run: `npm run build`

- [ ] **Step 7: Manually verify with a real browser session**

1. Log in as a zero-grant user — confirm Employees, Customers, Accounts (Users), Positions, and Salaries nav links are all gone.
2. Log in as `hrmanager@example.com` — confirm only Employees (and Positions/Salaries, since they share the `employees` grant) appear; Customers/Accounts do not.
3. Log in as `administrator@example.com` — confirm all five appear.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Shared/Layouts/Components/Menu.vue public/build
git commit -m "Filter Employees/Customers/User Management nav links by permission

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage** (against `docs/superpowers/specs/2026-08-14-dynamic-role-access-design.md` §13):
- Module-only, no submodules for Employees/Customers/User Management → every task
- Salaries/Positions classified under Employees, no access expansion → Task 2, Task 1's grant list
- `/libraries/roles` left untouched → confirmed nowhere in this plan touches it
- Employees/Customers as sole gate, Users/Salaries/Positions as second layer → Tasks 2–4, each with a `test_still_blocked_by_the_existing_role_gate`-equivalent test where relevant (Task 4 has one explicitly; Task 2's Salaries/Positions tests prove the same thing implicitly via the Administrator-role fixture requirement called out in Task 2 Step 2)
- Rollout safety, deploy-immediately-after-first-enforcement lesson from Plan B/C → Task 1's closing note, Task 2's closing note

**Placeholder scan:** Task 5 is a deliberate, explicitly-flagged exception to "no placeholders" — it's a *procedure* (grep, then apply a known pattern) rather than pre-written diffs, because the five frontend files' exact current contents weren't read while grounding this plan (unlike every backend task and unlike Plans B/C's frontend tasks, which did read every file first). This is called out in Task 5's own text, not silently assumed. Task 6 keeps one similar, narrower exception (Step 4) for the same reason. Every other task has complete, real code.

**Type consistency:** `can()`/`canAny()` calls (Tasks 5–6) use the exact module keys seeded in Plan A's `ModulesAndSubmodulesSeeder` (`employees`, `customers`, `user_management`) — no submodule keys anywhere, consistent with this plan's module-only design.

---

## After This Plan

**Payroll** (has real submodule structure: Processing, Templates, Loans, Settings) and **Accounting** (~35-40 routes, needs its own submodule taxonomy decided with the user given its size) remain as separate future plans, per spec §13's stated sequencing.
