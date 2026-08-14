# Wire Sales Module (Plan B) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn on real backend + frontend permission enforcement for the Sales module (Sales Orders, AR Invoices, Receipts, Sales Returns), replacing today's "no restriction at all" state and the handful of hardcoded role-name checks scattered across Sales Vue components, using the framework built in Plan A (`docs/superpowers/plans/2026-08-14-permission-framework.md`).

**Architecture:** Plan A's `PermissionService`, `permission` middleware, Inertia `permissions` share, and `can()`/`canAny()` frontend helper already exist and are tested. This plan is pure *consumption* of that framework: (1) seed rollout-safety default grants so no current user loses access on day one, (2) add backend authorization checks to the real Sales controllers/requests, (3) replace hardcoded `roles.includes(...)` checks in Vue with `can()`/`canAny()` and add missing gates around buttons that have none today, (4) filter the Sales sidebar tabs and the global nav's Sales link by permission.

**Tech Stack:** Same as Plan A — Laravel 11, Pest/PHPUnit (`RefreshDatabase`), Vue 3 Options API + Inertia.

## Global Constraints

- Follow existing codebase conventions exactly: services in `app/Services/Modules/`, `HandlesTransaction` trait for writes, thin controllers.
- Run the full suite (`php artisan test`) after each task; the baseline going into this plan is **145 passing / 19 failing** (all 19 pre-existing, unrelated to this work). Any *new* failure is a regression to fix before moving on.
- **Architecture fact this plan is built around:** Sales Orders and Sales Returns are NOT separately routed. Both live entirely under `POST/PUT/PATCH/DELETE /sales-orders{,/{sales_order}}` — there is no `/sales-returns` route or controller. `SalesOrderController::update()` multiplexes several actions of *different* required access levels through one route via a request `action` field (`update`, `approve`, `adjustment`), and `SalesOrderClass::approve()` internally branches by the order's *current status* (`sales-return-approval` vs. everything else) to decide whether it's actually approving a return or a regular order. Because of this, backend enforcement for these multiplexed actions is added directly inside `SalesOrderRequest::authorize()` (which already branches on `action` for validation rules — this plan mirrors that existing pattern for authorization) rather than as declarative route middleware, which can't express "this one route needs different levels depending on a request field and the target row's current state."
- Where a route already goes through a `FormRequest` (`SalesOrderRequest`, `PaymentRequest`), the permission check lives in that request's `authorize()` method — it runs before validation, so an unauthorized request gets a clean 403 before any field-level errors could leak information about the action. Where no `FormRequest` exists (`ReceiptController`, and the GET/DELETE actions on `SalesOrderController`/`ArInvoiceController`), the check is a direct call via a new `AuthorizesPermission` trait, placed as the first line of the controller method.
- `sales-orders-external` (`SalesOrderExternalController`) reuses `SalesOrderRequest` but is a distinct, unexplored flow not mentioned anywhere in the approved design spec's pilot mapping (spec §9 only covers internal Sales Orders). It is explicitly **out of scope** — `SalesOrderRequest::authorize()` detects and bypasses the external path unchanged, rather than silently sweeping it into new restrictions that haven't been reviewed for that flow.
- `remittance` and `sales-reports` tabs in `Sales/Index.vue` are **not** part of the module/submodule catalog seeded in Plan A (only `sales_orders`, `ar_invoices`, `receipts`, `sales_returns` were seeded under `sales`) and are out of this pilot's scope per spec §9/§12. They stay unfiltered — unchanged from today.
- Per spec §10 (rollout safety), the Task 1 seeder must be **reviewed with the user before running on production** — do not run it there without asking first, same discipline already used for Plan A's catalog seeder.
- Access-level mapping used throughout (from spec §9, resolved against the actual code in this plan): Sales Orders — Encoder: create/edit/adjustment, Approver: approve/cancel, View: list/show/print. AR Invoices — Encoder: record payment, View: list/show/print. Receipts — Encoder: create/edit, Admin: delete, View: list/show/print. Sales Returns — Encoder: create/request return (shares the Sales Orders create/edit routes), Approver: approve/reject (the `approve` action when the order's status is `sales-return-approval`), View: list/show.

---

### Task 1: Rollout-safety default permission grants

**Files:**
- Create: `database/seeders/SalesDefaultPermissionsSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (register the call)
- Test: `tests/Feature/Permissions/SalesDefaultPermissionsSeederTest.php`

**Interfaces:**
- Consumes: `Module`, `RolePermission`, `ListRole` (Plan A)
- Produces: seeded `role_permissions` rows preserving today's real Sales access (`Administrator` → `admin`; `Sales Rep` → `encoder`+`view`; `Area Business Manager` → `approver`+`view`; all module-wide, i.e. `submodule_id = null`) so Tasks 6–7's frontend/backend enforcement don't lock anyone out. `Super Admin` needs no row — `PermissionService` bypasses it unconditionally.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Database\Seeders\SalesDefaultPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDefaultPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function grantLevels(string $roleName): array
    {
        $role = ListRole::where('name', $roleName)->firstOrFail();

        return RolePermission::where('role_id', $role->id)
            ->whereNull('submodule_id')
            ->pluck('access_level')
            ->sort()
            ->values()
            ->all();
    }

    public function test_administrator_gets_admin_level_module_wide(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(SalesDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Administrator'));
    }

    public function test_sales_rep_gets_encoder_and_view_module_wide(): void
    {
        ListRole::create(['name' => 'Sales Rep', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(SalesDefaultPermissionsSeeder::class);

        $this->assertEquals(['encoder', 'view'], $this->grantLevels('Sales Rep'));
    }

    public function test_area_business_manager_gets_approver_and_view_module_wide(): void
    {
        ListRole::create(['name' => 'Area Business Manager', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(SalesDefaultPermissionsSeeder::class);

        $this->assertEquals(['approver', 'view'], $this->grantLevels('Area Business Manager'));
    }

    public function test_seeder_is_idempotent(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(SalesDefaultPermissionsSeeder::class);
        $this->seed(SalesDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Administrator'));
    }

    public function test_seeder_skips_roles_that_dont_exist_in_this_environment(): void
    {
        // No ListRole rows created at all — seeder must not throw.
        $this->seed(SalesDefaultPermissionsSeeder::class);

        $this->assertEquals(0, RolePermission::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SalesDefaultPermissionsSeederTest`
Expected: FAIL — class `Database\Seeders\SalesDefaultPermissionsSeeder` not found.

- [ ] **Step 3: Write the seeder**

`database/seeders/SalesDefaultPermissionsSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class SalesDefaultPermissionsSeeder extends Seeder
{
    /**
     * Rollout-safety seeding (design spec §10): grants that preserve exactly
     * the access these roles already have today via hardcoded role-name
     * checks (Menu.vue's Sales Management link, and the canApprove/isAdmin
     * computed props in the Sales Vue components) before those checks are
     * replaced with can()/canAny() in a later task. Super Admin needs no
     * row — PermissionService bypasses it unconditionally.
     */
    public function run(): void
    {
        $sales = Module::where('key', 'sales')->first();
        if (!$sales) {
            return; // modules/submodules catalog not seeded yet — nothing to grant against.
        }

        $grants = [
            ['role' => 'Administrator', 'level' => 'admin'],
            ['role' => 'Sales Rep', 'level' => 'encoder'],
            ['role' => 'Sales Rep', 'level' => 'view'],
            ['role' => 'Area Business Manager', 'level' => 'approver'],
            ['role' => 'Area Business Manager', 'level' => 'view'],
        ];

        foreach ($grants as $grant) {
            $role = ListRole::where('name', $grant['role'])->first();
            if (!$role) {
                continue; // role doesn't exist in this environment — skip rather than fail the seeder.
            }

            RolePermission::firstOrCreate([
                'role_id' => $role->id,
                'module_id' => $sales->id,
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
        $this->call(ModulesAndSubmodulesSeeder::class);
```
to:
```php
        $this->call(ModulesAndSubmodulesSeeder::class);
        $this->call(SalesDefaultPermissionsSeeder::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SalesDefaultPermissionsSeederTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 150 passing / 19 failing

- [ ] **Step 7: Commit**

```bash
git add database/seeders/SalesDefaultPermissionsSeeder.php database/seeders/DatabaseSeeder.php \
        tests/Feature/Permissions/SalesDefaultPermissionsSeederTest.php
git commit -m "Seed rollout-safety default Sales permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Do not run this seeder on production yet** — surface it to the user for review before running it there (spec §10), the same way Plan A's catalog seeder was confirmed before running over SSH.

---

### Task 2: `AuthorizesPermission` trait + AR Invoices backend enforcement

**Files:**
- Create: `app/Traits/AuthorizesPermission.php`
- Modify: `app/Http/Requests/Modules/PaymentRequest.php`
- Modify: `app/Http/Controllers/Modules/ArInvoiceController.php`
- Test: `tests/Feature/Sales/ArInvoicePermissionTest.php`

**Interfaces:**
- Consumes: `PermissionService::userHasAccess()` (Plan A)
- Produces: `AuthorizesPermission::authorizePermission(string $moduleKey, ?string $submoduleKey, string $level): void` — a controller-method-level 403 guard, reused by every task from here on that doesn't already go through a `FormRequest`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArInvoicePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['for-payment', 'unpaid'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucwords(str_replace('-', ' ', $slug)), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function userWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::create(['name' => 'Test Role ' . uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        $submoduleId = null;
        if ($submoduleKey) {
            $submoduleId = $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id;
        }

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeInvoice(): ArInvoice
    {
        $order = SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(),
            'payment_mode' => 'Credit',
            'order_date' => now()->toDateString(),
            'added_by_id' => User::factory()->create()->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);

        return ArInvoice::create([
            'sales_order_id' => $order->id,
            'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'amount_due' => 1000,
            'amount_paid' => 0,
            'balance_due' => 1000,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->userWithGrant('sales_orders', 'view'); // wrong submodule

        $this->actingAs($user)
            ->getJson('/ar-invoices?option=lists')
            ->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->userWithGrant('ar_invoices', 'view');

        $this->actingAs($user)
            ->getJson('/ar-invoices?option=lists')
            ->assertOk();
    }

    public function test_payment_denied_without_encoder_grant(): void
    {
        $invoice = $this->makeInvoice();
        $user = $this->userWithGrant('ar_invoices', 'view'); // view only, not encoder

        $this->actingAs($user)
            ->put("/ar-invoices/{$invoice->id}", [
                'option' => 'payment',
                'amount_paid' => 500,
                'balance_due' => 1000,
                'payment_date' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_payment_allowed_with_encoder_grant(): void
    {
        $invoice = $this->makeInvoice();
        $user = $this->userWithGrant('ar_invoices', 'encoder');

        $response = $this->actingAs($user)
            ->put("/ar-invoices/{$invoice->id}", [
                'option' => 'payment',
                'amount_paid' => 500,
                'balance_due' => 1000,
                'payment_date' => now()->toDateString(),
            ]);

        // Proves the request wasn't blocked by the 403 authorization gate —
        // the redirect-back outcome (success or a business-logic error) is
        // covered by this codebase's existing payment tests elsewhere.
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ArInvoicePermissionTest`
Expected: FAIL — `test_list_denied_without_view_grant` and `test_payment_denied_without_encoder_grant` fail because nothing is gated yet (both currently succeed instead of 403).

- [ ] **Step 3: Write the trait**

`app/Traits/AuthorizesPermission.php`:
```php
<?php

namespace App\Traits;

use App\Services\System\Permission\PermissionService;

trait AuthorizesPermission
{
    /**
     * Abort with 403 unless the current user holds $level for the given
     * module (and, optionally, submodule). For use inside controller
     * methods where the check can't be expressed as a single declarative
     * route middleware — e.g. several actions of different required
     * levels sharing one route (see PermissionMiddleware for the
     * route-middleware form used where the route IS single-purpose).
     */
    protected function authorizePermission(string $moduleKey, ?string $submoduleKey, string $level): void
    {
        $user = auth()->user();

        if (!$user || !app(PermissionService::class)->userHasAccess($user, $moduleKey, $submoduleKey, $level)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }
}
```

- [ ] **Step 4: Gate `ArInvoiceController`**

In `app/Http/Controllers/Modules/ArInvoiceController.php`, add the trait:
```php
use App\Traits\HandlesTransaction;
```
to:
```php
use App\Traits\HandlesTransaction;
use App\Traits\AuthorizesPermission;
```

Change:
```php
class ArInvoiceController extends Controller
{
    use HandlesTransaction;
```
to:
```php
class ArInvoiceController extends Controller
{
    use HandlesTransaction;
    use AuthorizesPermission;
```

Change `index()` from:
```php
    public function index(Request $request){
        switch($request->option){
```
to:
```php
    public function index(Request $request){
        $this->authorizePermission('sales', 'ar_invoices', 'view');

        switch($request->option){
```

Change `show()` from:
```php
    public function show($id , Request $request){
        return $this->print->print($id, $request);
    }
```
to:
```php
    public function show($id , Request $request){
        $this->authorizePermission('sales', 'ar_invoices', 'view');

        return $this->print->print($id, $request);
    }
```

- [ ] **Step 5: Gate `PaymentRequest`**

In `app/Http/Requests/Modules/PaymentRequest.php`, change:
```php
    public function authorize(): bool
    {
        return true;
    }
```
to:
```php
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user !== null && app(\App\Services\System\Permission\PermissionService::class)
            ->userHasAccess($user, 'sales', 'ar_invoices', 'encoder');
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ArInvoicePermissionTest`
Expected: PASS (4 tests)

- [ ] **Step 7: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 154 passing / 19 failing

- [ ] **Step 8: Commit**

```bash
git add app/Traits/AuthorizesPermission.php app/Http/Requests/Modules/PaymentRequest.php \
        app/Http/Controllers/Modules/ArInvoiceController.php \
        tests/Feature/Sales/ArInvoicePermissionTest.php
git commit -m "Enforce Sales permissions on AR Invoices (view + record payment)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Receipts backend enforcement

**Files:**
- Modify: `app/Http/Controllers/Modules/ReceiptController.php`
- Test: `tests/Feature/Sales/ReceiptPermissionTest.php`

**Interfaces:**
- Consumes: `AuthorizesPermission` (Task 2)
- Produces: `sales,receipts,view|encoder|admin` enforcement on the Receipt CRUD routes.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Receipt;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['for-payment', 'unpaid', 'pending'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucwords(str_replace('-', ' ', $slug)), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function userWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::create(['name' => 'Test Role ' . uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeReceipt(): Receipt
    {
        $order = SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(), 'payment_mode' => 'Cash',
            'order_date' => now()->toDateString(), 'added_by_id' => User::factory()->create()->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);
        $invoice = ArInvoice::create([
            'sales_order_id' => $order->id, 'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
            'invoice_number' => 'INV-TEST-' . uniqid(), 'invoice_date' => now()->toDateString(),
            'amount_due' => 500, 'amount_paid' => 0, 'balance_due' => 500,
        ]);

        return Receipt::create([
            'ar_invoice_id' => $invoice->id,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'receipt_number' => 'RC-TEST-' . uniqid(),
            'receipt_date' => now()->toDateString(),
            'amount_paid' => 500,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->userWithGrant('sales_orders', 'view');

        $this->actingAs($user)->getJson('/receipts?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->userWithGrant('receipts', 'view');

        $this->actingAs($user)->getJson('/receipts?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->userWithGrant('receipts', 'view');

        $response = $this->actingAs($user)->post('/receipts', []);

        $response->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->userWithGrant('receipts', 'encoder');

        $response = $this->actingAs($user)->post('/receipts', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $receipt = $this->makeReceipt();
        $user = $this->userWithGrant('receipts', 'encoder'); // encoder, not admin

        $this->actingAs($user)->delete("/receipts/{$receipt->id}")->assertForbidden();
    }

    public function test_destroy_allowed_with_admin_grant(): void
    {
        $receipt = $this->makeReceipt();
        $user = $this->userWithGrant('receipts', 'admin');

        $response = $this->actingAs($user)->delete("/receipts/{$receipt->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReceiptPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Gate `ReceiptController`**

In `app/Http/Controllers/Modules/ReceiptController.php`, add the trait — change:
```php
class ReceiptController extends Controller
{
    use HandlesTransaction;
```
to:
```php
class ReceiptController extends Controller
{
    use HandlesTransaction;
    use \App\Traits\AuthorizesPermission;
```

Change `index()` from:
```php
    public function index(Request $request){
        switch($request->option){
```
to:
```php
    public function index(Request $request){
        $this->authorizePermission('sales', 'receipts', 'view');

        switch($request->option){
```

Change `store()` from:
```php
    public function store(Request $request){
        $result = $this->handleTransaction(function () use ($request) {
```
to:
```php
    public function store(Request $request){
        $this->authorizePermission('sales', 'receipts', 'encoder');

        $result = $this->handleTransaction(function () use ($request) {
```

Change `show()` from:
```php
    public function show($id, Request $request)
    {
        if ($request->option === 'detail') {
```
to:
```php
    public function show($id, Request $request)
    {
        $this->authorizePermission('sales', 'receipts', 'view');

        if ($request->option === 'detail') {
```

Change `update()` from:
```php
    public function update(Request $request, $id){
        $request->merge(['id' => $id]);
```
to:
```php
    public function update(Request $request, $id){
        $this->authorizePermission('sales', 'receipts', 'encoder');

        $request->merge(['id' => $id]);
```

Change `destroy()` from:
```php
    public function destroy($id){
        $result = $this->handleTransaction(function () use ($id) {
```
to:
```php
    public function destroy($id){
        $this->authorizePermission('sales', 'receipts', 'admin');

        $result = $this->handleTransaction(function () use ($id) {
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReceiptPermissionTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 160 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Modules/ReceiptController.php tests/Feature/Sales/ReceiptPermissionTest.php
git commit -m "Enforce Sales permissions on Receipts (view/encoder/admin)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Sales Orders backend enforcement — index/show/destroy

**Files:**
- Modify: `app/Http/Controllers/Modules/SalesOrderController.php`
- Test: `tests/Feature/Sales/SalesOrderPermissionTest.php`

**Interfaces:**
- Consumes: `AuthorizesPermission` (Task 2)
- Produces: `sales,sales_orders,view` on list/show, `sales,sales_orders,approver` on cancel (destroy). `store`/`update`/`adjustment` are handled separately in Task 5 (they go through `SalesOrderRequest`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sales;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        ListStatus::firstOrCreate(['slug' => 'for-payment'], [
            'name' => 'For Payment', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
    }

    private function userWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::create(['name' => 'Test Role ' . uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeOrder(): SalesOrder
    {
        return SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(), 'payment_mode' => 'Cash',
            'order_date' => now()->toDateString(), 'added_by_id' => User::factory()->create()->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->userWithGrant('ar_invoices', 'view');

        $this->actingAs($user)->getJson('/sales-orders?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->userWithGrant('sales_orders', 'view');

        $this->actingAs($user)->getJson('/sales-orders?option=lists')->assertOk();
    }

    public function test_show_page_denied_without_view_grant(): void
    {
        $order = $this->makeOrder();
        $user = $this->userWithGrant('ar_invoices', 'view');

        $this->actingAs($user)->get("/sales-orders/{$order->id}")->assertForbidden();
    }

    public function test_cancel_denied_without_approver_grant(): void
    {
        $order = $this->makeOrder();
        $user = $this->userWithGrant('sales_orders', 'encoder'); // encoder, not approver

        $this->actingAs($user)->delete("/sales-orders/{$order->id}")->assertForbidden();
    }

    public function test_cancel_allowed_with_approver_grant(): void
    {
        $order = $this->makeOrder();
        $user = $this->userWithGrant('sales_orders', 'approver');

        $response = $this->actingAs($user)->delete("/sales-orders/{$order->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SalesOrderPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Gate `SalesOrderController`**

In `app/Http/Controllers/Modules/SalesOrderController.php`, add the trait — change:
```php
class SalesOrderController extends Controller
{
    use HandlesTransaction;
```
to:
```php
class SalesOrderController extends Controller
{
    use HandlesTransaction;
    use \App\Traits\AuthorizesPermission;
```

Change `index()` from:
```php
    public function index(Request $request){
        switch($request->option){
```
to:
```php
    public function index(Request $request){
        $this->authorizePermission('sales', 'sales_orders', 'view');

        switch($request->option){
```

Change `show()` from:
```php
    public function show($id , Request $request){
        return $this->print->print($id, $request);
    }
```
to:
```php
    public function show($id , Request $request){
        $this->authorizePermission('sales', 'sales_orders', 'view');

        return $this->print->print($id, $request);
    }
```

Change `destroy()` from:
```php
    public function destroy($id){
        $result = $this->handleTransaction(function () use ($id) {
```
to:
```php
    public function destroy($id){
        $this->authorizePermission('sales', 'sales_orders', 'approver');

        $result = $this->handleTransaction(function () use ($id) {
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SalesOrderPermissionTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 165 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Modules/SalesOrderController.php tests/Feature/Sales/SalesOrderPermissionTest.php
git commit -m "Enforce Sales Orders permissions on list/show/cancel

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Sales Orders backend enforcement — create/edit/adjustment/approve (via `SalesOrderRequest`)

**Files:**
- Modify: `app/Http/Requests/Modules/SalesOrderRequest.php`
- Test: `tests/Feature/Sales/SalesOrderUpdateApprovePermissionTest.php`

**Interfaces:**
- Consumes: `PermissionService::userHasAccess()` (Plan A)
- Produces: `sales,sales_orders,encoder` on create/edit/adjustment; `sales,sales_orders,approver` on approving a regular order; `sales,sales_returns,approver` on approving an order whose current status is `sales-return-approval` — this is the dynamic branch that mirrors `SalesOrderClass::approve()`'s own status check.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sales;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderUpdateApprovePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['for-payment', 'sales-return-approval'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucwords(str_replace('-', ' ', $slug)), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function userWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::create(['name' => 'Test Role ' . uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeOrder(string $statusSlug): SalesOrder
    {
        return SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(), 'payment_mode' => 'Cash',
            'order_date' => now()->toDateString(), 'added_by_id' => User::factory()->create()->id,
            'status_id' => ListStatus::where('slug', $statusSlug)->first()->id,
        ]);
    }

    public function test_create_denied_without_encoder_grant(): void
    {
        $user = $this->userWithGrant('sales_orders', 'view');

        $this->actingAs($user)->post('/sales-orders', [])->assertForbidden();
    }

    public function test_create_allowed_with_encoder_grant(): void
    {
        $user = $this->userWithGrant('sales_orders', 'encoder');

        $response = $this->actingAs($user)->post('/sales-orders', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_edit_denied_without_encoder_grant(): void
    {
        $order = $this->makeOrder('for-payment');
        $user = $this->userWithGrant('sales_orders', 'approver'); // approver, not encoder

        $this->actingAs($user)->put("/sales-orders/{$order->id}", [])->assertForbidden();
    }

    public function test_adjustment_denied_without_encoder_grant(): void
    {
        $order = $this->makeOrder('for-payment');
        $user = $this->userWithGrant('sales_orders', 'approver');

        $this->actingAs($user)
            ->put("/sales-orders/{$order->id}", ['action' => 'adjustment'])
            ->assertForbidden();
    }

    public function test_approving_a_regular_order_requires_sales_orders_approver(): void
    {
        $order = $this->makeOrder('for-payment');
        $returnsOnlyUser = $this->userWithGrant('sales_returns', 'approver');

        $this->actingAs($returnsOnlyUser)
            ->put("/sales-orders/{$order->id}", ['action' => 'approve'])
            ->assertForbidden();

        $ordersApprover = $this->userWithGrant('sales_orders', 'approver');

        $response = $this->actingAs($ordersApprover)
            ->put("/sales-orders/{$order->id}", ['action' => 'approve']);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_approving_a_return_requires_sales_returns_approver(): void
    {
        $order = $this->makeOrder('sales-return-approval');
        $ordersOnlyUser = $this->userWithGrant('sales_orders', 'approver');

        $this->actingAs($ordersOnlyUser)
            ->put("/sales-orders/{$order->id}", ['action' => 'approve'])
            ->assertForbidden();

        $returnsApprover = $this->userWithGrant('sales_returns', 'approver');

        $response = $this->actingAs($returnsApprover)
            ->put("/sales-orders/{$order->id}", ['action' => 'approve']);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_external_sales_orders_route_is_not_affected(): void
    {
        // sales-orders-external is out of this pilot's scope — a user with
        // zero Sales grants must still be able to reach it unchanged.
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/sales-orders-external', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SalesOrderUpdateApprovePermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Gate `SalesOrderRequest::authorize()`**

In `app/Http/Requests/Modules/SalesOrderRequest.php`, change:
```php
    public function authorize(): bool
    {
        return true;
    }
```
to:
```php
    public function authorize(): bool
    {
        // sales-orders-external shares this FormRequest but is a distinct,
        // unexplored flow not covered by the pilot's design spec (§9/§12)
        // — leave it unrestricted, unchanged, until explicitly wired later.
        if (str_starts_with((string) $this->path(), 'sales-orders-external')) {
            return true;
        }

        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $permissions = app(\App\Services\System\Permission\PermissionService::class);
        $action = $this->input('action');
        $orderId = $this->route('sales_order') ?? $this->route('id');

        if ($action === 'approve' && $orderId) {
            $order = \App\Models\SalesOrder::with('status')->find($orderId);
            $isReturnApproval = $order && optional($order->status)->slug === 'sales-return-approval';
            $submodule = $isReturnApproval ? 'sales_returns' : 'sales_orders';

            return $permissions->userHasAccess($user, 'sales', $submodule, 'approver');
        }

        // Plain create (POST /sales-orders), plain edit (PUT with no/'update'
        // action), and 'adjustment' are all Encoder-level per spec §9.
        return $permissions->userHasAccess($user, 'sales', 'sales_orders', 'encoder');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SalesOrderUpdateApprovePermissionTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 172 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Modules/SalesOrderRequest.php \
        tests/Feature/Sales/SalesOrderUpdateApprovePermissionTest.php
git commit -m "Enforce Sales Orders/Returns permissions on create/edit/adjustment/approve

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Frontend — replace hardcoded role checks with `can()`, gate previously-ungated buttons

**Files:**
- Modify: `resources/js/Pages/Modules/Sales/Components/SalesOrders/Index.vue`
- Modify: `resources/js/Pages/Modules/Sales/Components/SalesReturns/Index.vue`
- Modify: `resources/js/Pages/Modules/Sales/Components/ARInvoices/Index.vue`

**Interfaces:**
- Consumes: `this.can(moduleKey, submoduleKey, level)` (Plan A's `permissionsMixin`, already global)
- Produces: buttons that show/hide based on the same grants Tasks 2–5 now enforce server-side, closing the gap between what the UI offers and what the backend actually allows.

- [ ] **Step 1: `SalesOrders/Index.vue` — replace `canApprove`, gate Create/Edit/Adjustment/Cancel**

Change the `canApprove` computed property from:
```js
    computed: {
        canApprove() {
            const roles = this.$page?.props?.roles || [];
            return ['Administrator', 'Area Business Manager', 'Super Admin'].some(r => roles.includes(r));
        },
    },
```
to:
```js
    computed: {
        canApprove() {
            return this.can('sales', 'sales_orders', 'approver');
        },
    },
```

Change the Create Order button from:
```html
                    <button class="acct-btn-primary" @click="openCreate">
                        <i class="ri-add-line me-1"></i>Create Order
                    </button>
```
to:
```html
                    <button v-if="can('sales', 'sales_orders', 'encoder')" class="acct-btn-primary" @click="openCreate">
                        <i class="ri-add-line me-1"></i>Create Order
                    </button>
```

Change the Sales Adjustment and Edit buttons from:
```html
                                                <button v-if="list.status?.slug == 'for-payment'"
                                                    @click.stop="onSalesAdjustment(list)"
                                                    class="action-btn warn" title="Sales Adjustment">
                                                    <i class="ri-refund-line"></i>
                                                </button>
                                                <button @click.stop="onPrint(list.id)"
                                                    class="action-btn info" title="Print Invoice">
                                                    <i class="ri-printer-line"></i>
                                                </button>
                                                <button v-if="list.status?.slug == 'for-payment'"
                                                    @click.stop="openEdit(list, index)"
                                                    class="action-btn edit" title="Edit">
                                                    <i class="ri-pencil-fill"></i>
                                                </button>
                                                <button v-if="list.status?.slug !== 'cancelled'"
                                                    @click.stop="onCancel(list)"
                                                    class="action-btn delete" title="Cancel Order">
                                                    <i class="ri-close-line"></i>
                                                </button>
```
to:
```html
                                                <button v-if="list.status?.slug == 'for-payment' && can('sales', 'sales_orders', 'encoder')"
                                                    @click.stop="onSalesAdjustment(list)"
                                                    class="action-btn warn" title="Sales Adjustment">
                                                    <i class="ri-refund-line"></i>
                                                </button>
                                                <button @click.stop="onPrint(list.id)"
                                                    class="action-btn info" title="Print Invoice">
                                                    <i class="ri-printer-line"></i>
                                                </button>
                                                <button v-if="list.status?.slug == 'for-payment' && can('sales', 'sales_orders', 'encoder')"
                                                    @click.stop="openEdit(list, index)"
                                                    class="action-btn edit" title="Edit">
                                                    <i class="ri-pencil-fill"></i>
                                                </button>
                                                <button v-if="list.status?.slug !== 'cancelled' && can('sales', 'sales_orders', 'approver')"
                                                    @click.stop="onCancel(list)"
                                                    class="action-btn delete" title="Cancel Order">
                                                    <i class="ri-close-line"></i>
                                                </button>
```

- [ ] **Step 2: `SalesReturns/Index.vue` — replace `canApprove`/`isAdmin`, gate Create**

Change:
```js
    computed: {
        canApprove() {
            const roles = this.$page?.props?.roles || [];
            return ['Administrator', 'Area Business Manager', 'Super Admin'].some(r => roles.includes(r));
        },
        isAdmin() {
            const roles = this.$page?.props?.roles || [];
            return ['Administrator', 'Super Admin'].some(r => roles.includes(r));
        },
    },
```
to:
```js
    computed: {
        canApprove() {
            return this.can('sales', 'sales_returns', 'approver');
        },
        isAdmin() {
            return this.can('sales', 'sales_returns', 'admin');
        },
    },
```

Change the Create button from:
```html
                            <button class="acct-btn-primary" @click="openCreate">
```
to:
```html
                            <button v-if="can('sales', 'sales_returns', 'encoder')" class="acct-btn-primary" @click="openCreate">
```

- [ ] **Step 3: `ARInvoices/Index.vue` — gate Record Payment**

Change both occurrences of the Record Payment button. First:
```html
                                                <button
                                                    v-if="(list.status?.slug == 'unpaid' || list.status?.slug == 'partially-paid' || list.balance_due > 0) && (list.sales_order?.status?.slug != 'cancelled' && list.sales_order?.status?.slug != 'sales-returned')"
                                                    @click.stop="onPayment(list)" class="action-btn edit" title="Record Payment">
```
to:
```html
                                                <button
                                                    v-if="(list.status?.slug == 'unpaid' || list.status?.slug == 'partially-paid' || list.balance_due > 0) && (list.sales_order?.status?.slug != 'cancelled' && list.sales_order?.status?.slug != 'sales-returned') && can('sales', 'ar_invoices', 'encoder')"
                                                    @click.stop="onPayment(list)" class="action-btn edit" title="Record Payment">
```

Second (the expanded-row copy):
```html
                                                        <button
                                                            v-if="(list.status?.slug == 'unpaid' || list.status?.slug == 'partially-paid' || list.balance_due > 0) && (list.sales_order?.status?.slug != 'cancelled' && list.sales_order?.status?.slug != 'sales-returned')"
                                                            @click.stop="onPayment(list)" class="acct-btn-primary">
```
to:
```html
                                                        <button
                                                            v-if="(list.status?.slug == 'unpaid' || list.status?.slug == 'partially-paid' || list.balance_due > 0) && (list.sales_order?.status?.slug != 'cancelled' && list.sales_order?.status?.slug != 'sales-returned') && can('sales', 'ar_invoices', 'encoder')"
                                                            @click.stop="onPayment(list)" class="acct-btn-primary">
```

- [ ] **Step 4: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 172 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 5: Rebuild assets**

Run: `npm run build`

- [ ] **Step 6: Manually verify with a real browser session**

With `php artisan serve`/Herd running, using the credentials and pattern already established this session (log in, navigate, screenshot):
1. Log in as `salesrep@example.com` (Sales Rep — has `encoder`+`view` from Task 1's seeding once it's actually run — see Task 7's note) and confirm the Create Order / Sales Adjustment / Edit buttons are visible, Cancel is NOT (no approver grant).
2. Log in as `administrator@example.com` (Admin level — sees everything) and confirm Cancel, Approve, and the Sales Returns "Return Settings" button are all visible.
3. Confirm a user with zero Sales grants (e.g. `hrmanager@example.com`, once nav filtering from Task 7 doesn't block direct navigation) sees no action buttons on any Sales Orders row.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Modules/Sales/Components/SalesOrders/Index.vue \
        resources/js/Pages/Modules/Sales/Components/SalesReturns/Index.vue \
        resources/js/Pages/Modules/Sales/Components/ARInvoices/Index.vue \
        public/build
git commit -m "Wire Sales buttons to can() — replace hardcoded role checks

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Frontend — sidebar tab filtering + global nav Sales link

**Files:**
- Modify: `resources/js/Pages/Modules/Sales/Index.vue`
- Modify: `resources/js/Shared/Layouts/Components/Menu.vue`

**Interfaces:**
- Consumes: `this.canAny(moduleKey, submoduleKey)` (Plan A's `permissionsMixin`)
- Produces: the in-page Sales tab list and the global left-nav "Sales Management" link both reflect real grants instead of a hardcoded role name / an unfiltered static list.

- [ ] **Step 1: Filter the Sales tab list**

In `resources/js/Pages/Modules/Sales/Index.vue`, add a computed property. Change:
```js
  computed: {
```
to:
```js
  computed: {
    visibleTabs() {
      // remittance and sales-reports are outside this pilot's submodule
      // catalog (spec §9/§12) — they stay visible to anyone who can see
      // the Sales module at all, unfiltered, same as today.
      const gatedTabIds = ['sales_orders', 'sales_returns', 'ar_invoices', 'receipts'];
      return this.tabs.filter((tab) => {
        if (!gatedTabIds.includes(tab.id)) {
          return true;
        }
        return this.canAny('sales', tab.id);
      });
    },
```

Change the tab-bar `v-for` from:
```html
          <button
            v-for="tab in tabs"
```
to:
```html
          <button
            v-for="tab in visibleTabs"
```

- [ ] **Step 2: Filter the global nav Sales link**

In `resources/js/Shared/Layouts/Components/Menu.vue`, change:
```html
                <li class="nav-item"
                    v-if="$page.props.roles.includes('Sales Rep') || $page.props.roles.includes('Super Admin')">
```
to:
```html
                <li class="nav-item"
                    v-if="canAny('sales')">
```

- [ ] **Step 3: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 172 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 4: Rebuild assets**

Run: `npm run build`

- [ ] **Step 5: Manually verify with a real browser session**

1. Log in as a user with zero Sales grants — confirm "Sales Management" is gone from the left nav, and navigating to `/sales-orders` directly now 403s (Task 4's backend gate).
2. Log in as `salesrep@example.com` (once Task 1's seeder has actually been run — see note below) — confirm "Sales Management" appears, and inside it only Sales Orders / Sales Returns / AR Invoices / Receipts tabs are gated by grants while Remittances / Sales Report remain visible regardless.
3. Log in as `super.admin@example.com` — confirm every tab is visible.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Modules/Sales/Index.vue resources/js/Shared/Layouts/Components/Menu.vue public/build
git commit -m "Filter Sales sidebar tabs and nav link by permission

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Before this task's manual verification is meaningful in any real environment (local dev DB or production), Task 1's `SalesDefaultPermissionsSeeder` must actually be run there** — the migration/seeder existing in code isn't enough, exactly like Plan A's `ModulesAndSubmodulesSeeder` needed a real run over SSH after deploy. Confirm with the user before running it on production.

---

## Self-Review

**Spec coverage** (against `docs/superpowers/specs/2026-08-14-dynamic-role-access-design.md` §9 Sales row and §10):
- Sales Orders Encoder (Create, Edit) / Approver (Approve, Cancel) / View (List, View, Print) → Tasks 4, 5, 6
- AR Invoices Encoder (Record payment) / View (List, View, Print) → Task 2, 6
- Receipts Encoder (Create, Edit) / View (List, View) → Task 3 (also added Admin/delete, matching spec §4's general Admin-includes-delete rule, since `destroy()` exists and needed a level)
- Sales Returns Encoder (Create/request return) / Approver (Approve, Reject) / View (List, View) → Task 5 (shares Sales Orders' routes — see Global Constraints), Task 6
- §10 rollout safety (no day-one lockout) → Task 1
- §7 sidebar filtering → Task 7

**Placeholder scan:** no "TBD"/"handle edge cases"/"similar to Task N" — every step has literal, complete code, grounded in the actual current contents of every file touched (verified by reading each one before writing its diff).

**Type consistency:** `AuthorizesPermission::authorizePermission(string, ?string, string): void` (Task 2) is called identically in Tasks 3–4. `SalesOrderRequest::authorize()`'s branching (Task 5) mirrors the exact same `$currentStatus === 'sales-return-approval'` condition already used by `SalesOrderClass::approve()` — verified by reading that method's source, not assumed. Frontend `can()`/`canAny()` calls (Tasks 6–7) use the exact module/submodule keys seeded in Plan A's `ModulesAndSubmodulesSeeder` (`sales`, `sales_orders`, `sales_returns`, `ar_invoices`, `receipts`).

**Known, documented limitations (not gaps — deliberate scope boundaries):**
- `sales-orders-external` is untouched (Task 5's Global Constraints note).
- `remittance` and `sales-reports` tabs stay unfiltered (Task 7).
- Backend enforcement for "Sales Returns" piggybacks on the shared `/sales-orders` routes rather than having its own — there is no separate route to enforce against in the current codebase.

---

## After This Plan

**Plan C (wire Inventory)** is the remaining piece of the original pilot scope (spec §9's Inventory table) and should be written as its own plan document following this same process — Inventory's routes are more cleanly single-purpose per spec §6's own example (`/purchase-orders/{id}/void`), so it's expected to lean on `PermissionMiddleware` (Plan A, Task 4) directly rather than the `FormRequest::authorize()` pattern this plan needed for Sales Orders' multiplexed actions.
