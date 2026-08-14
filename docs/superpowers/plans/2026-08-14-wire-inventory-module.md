# Wire Inventory Module (Plan C) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn on real backend + frontend permission enforcement for the Inventory module (Purchase Orders, Receiving/Received Stocks, Inventory Stocks, Stock Returns), adding a second, finer-grained layer *inside* the existing all-or-nothing `role:Administrator,Warehouse Manager` gate, using the framework built in Plan A.

**Architecture:** Unlike Sales (Plan B), Inventory's routes are almost entirely single-purpose — one route per action, no request-field action-multiplexing. This means enforcement is added almost entirely as declarative `permission:module,submodule,level` route middleware (Plan A's `PermissionMiddleware`), layered on top of the existing `role:Administrator,Warehouse Manager` group rather than replacing it. Laravel 11's `Route::resource(...)->middlewareFor('action', 'permission:...')` applies different levels to different actions of the same resource route without splitting it into individual route registrations.

**Tech Stack:** Same as Plans A/B — Laravel 11, Pest/PHPUnit (`RefreshDatabase`), Vue 3 Options API + Inertia.

## Global Constraints

- Follow existing codebase conventions exactly: services in `app/Services/`, `HandlesTransaction` trait for writes, thin controllers.
- Run the full suite (`php artisan test`) after each task; the baseline going into this plan is **172 passing / 19 failing** (all 19 pre-existing, unrelated to this work). Any *new* failure is a regression to fix before moving on.
- **The existing `role:Administrator,Warehouse Manager` group middleware is left untouched.** `permission:...` middleware is added as an *additional* layer on the same routes (per spec §6), not a replacement — a request must pass both. Because of this, every test in this plan gives its fixture user the `Warehouse Manager` role (satisfying the outer gate) and then varies only the new fine-grained grant, which is what actually distinguishes the test cases — this mirrors production reality exactly (a real Warehouse Manager still needs the specific grant once this plan ships).
- Access-level mapping used throughout (from spec §9, resolved against the actual code): Purchase Orders — Encoder: create/edit, Approver: approve status/void, View: list/show/print, Admin: delete. Receiving — Encoder: receive stock/record payment, View: list/show, Admin: delete. Inventory Stocks — Encoder: adjustment/conversion/weight-loss/update price, View: list/show, Admin: settings. Stock Returns — Encoder: create return request, Approver: approve/reject (one action, `status: approved|disapproved`)/receive item, View: list/show.
- Two route-naming quirks discovered while grounding this plan, both handled explicitly rather than assumed: (1) the Sales tab list uses submodule-key tab ids, but Inventory's tab ids are camelCase and don't match the seeded submodule keys 1:1 (`purchaseOrders`→`purchase_orders`, `receiving`→`receiving`, `productSummary`→`inventory_stocks`, `stockReturns`→`stock_returns`) — Task 7 maps these explicitly. (2) `purchaseRequests` and `accountsPayable` tabs are **not** part of the seeded submodule catalog (only the four above were seeded in Plan A) and are out of this pilot's scope per spec §9/§12 — they stay unfiltered, same as Sales' `remittance`/`sales-reports`. The Accounts Payable tab's "Record Payment" *button* is still gated in Task 6 (it calls the `receiving`-submodule `pay` route), even though the tab itself isn't filtered — those are two independent things.
- Per spec §10 (rollout safety), the Task 1 seeder must be **reviewed with the user before running on production** — do not run it there without asking first, same discipline already used twice in Plan A/B.
- `Route::resource('inventory-stocks', InventoryStockController::class)` and `Route::resource('/stock-returns', StockReturnController::class)` register `store`/`destroy` routes with no corresponding controller method (neither controller implements them) — these already 500 if ever hit today. This plan does not add `middlewareFor` to those two non-existent actions; gating a route that already errors adds no value and isn't part of any task's tested surface.

---

### Task 1: Rollout-safety default permission grants

**Files:**
- Create: `database/seeders/InventoryDefaultPermissionsSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (register the call)
- Test: `tests/Feature/Permissions/InventoryDefaultPermissionsSeederTest.php`

**Interfaces:**
- Consumes: `Module`, `RolePermission`, `ListRole` (Plan A)
- Produces: seeded `role_permissions` rows preserving today's real Inventory access — `Administrator` → `admin` module-wide; `Warehouse Manager` → `encoder`+`approver`+`view` module-wide (matches spec §10: "matches today's unrestricted access within the already-gated group"). `Super Admin` needs no row.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use Database\Seeders\InventoryDefaultPermissionsSeeder;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDefaultPermissionsSeederTest extends TestCase
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

        $this->seed(InventoryDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Administrator'));
    }

    public function test_warehouse_manager_gets_encoder_approver_and_view_module_wide(): void
    {
        ListRole::create(['name' => 'Warehouse Manager', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(InventoryDefaultPermissionsSeeder::class);

        $this->assertEquals(['approver', 'encoder', 'view'], $this->grantLevels('Warehouse Manager'));
    }

    public function test_seeder_is_idempotent(): void
    {
        ListRole::create(['name' => 'Warehouse Manager', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(InventoryDefaultPermissionsSeeder::class);
        $this->seed(InventoryDefaultPermissionsSeeder::class);

        $this->assertEquals(['approver', 'encoder', 'view'], $this->grantLevels('Warehouse Manager'));
    }

    public function test_seeder_skips_roles_that_dont_exist_in_this_environment(): void
    {
        $this->seed(InventoryDefaultPermissionsSeeder::class);

        $this->assertEquals(0, RolePermission::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryDefaultPermissionsSeederTest`
Expected: FAIL — class `Database\Seeders\InventoryDefaultPermissionsSeeder` not found.

- [ ] **Step 3: Write the seeder**

`database/seeders/InventoryDefaultPermissionsSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class InventoryDefaultPermissionsSeeder extends Seeder
{
    /**
     * Rollout-safety seeding (design spec §10): grants that preserve exactly
     * the access these roles already have today via the existing
     * role:Administrator,Warehouse Manager route-group gate, before this
     * plan adds a second, finer-grained permission layer inside it. Super
     * Admin needs no row — PermissionService bypasses it unconditionally.
     */
    public function run(): void
    {
        $inventory = Module::where('key', 'inventory')->first();
        if (!$inventory) {
            return; // modules/submodules catalog not seeded yet — nothing to grant against.
        }

        $grants = [
            ['role' => 'Administrator', 'level' => 'admin'],
            ['role' => 'Warehouse Manager', 'level' => 'encoder'],
            ['role' => 'Warehouse Manager', 'level' => 'approver'],
            ['role' => 'Warehouse Manager', 'level' => 'view'],
        ];

        foreach ($grants as $grant) {
            $role = ListRole::where('name', $grant['role'])->first();
            if (!$role) {
                continue; // role doesn't exist in this environment — skip rather than fail the seeder.
            }

            RolePermission::firstOrCreate([
                'role_id' => $role->id,
                'module_id' => $inventory->id,
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
        $this->call(SalesDefaultPermissionsSeeder::class);
```
to:
```php
        $this->call(SalesDefaultPermissionsSeeder::class);
        $this->call(InventoryDefaultPermissionsSeeder::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=InventoryDefaultPermissionsSeederTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 176 passing / 19 failing

- [ ] **Step 7: Commit**

```bash
git add database/seeders/InventoryDefaultPermissionsSeeder.php database/seeders/DatabaseSeeder.php \
        tests/Feature/Permissions/InventoryDefaultPermissionsSeederTest.php
git commit -m "Seed rollout-safety default Inventory permissions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Do not run this seeder on production yet** — surface it to the user for review before running it there, the same way Plans A/B's seeders were confirmed before running over SSH.

---

### Task 2: Purchase Orders backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Inventory/PurchaseOrderPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `inventory,purchase_orders,view|encoder|approver|admin` enforcement on every Purchase Order route.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inventory;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderPermissionTest extends TestCase
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
     * Every fixture holds the Warehouse Manager role (satisfies the existing
     * role:Administrator,Warehouse Manager gate) and varies only the new
     * fine-grained grant — matching how this plan actually changes behavior.
     */
    private function warehouseManagerWithGrant(?string $submoduleKey, ?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Warehouse Manager'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'inventory')->firstOrFail();
            $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submoduleId, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makePurchaseOrder(): PurchaseOrder
    {
        $supplierId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'Test Supplier', 'address' => 'Test Address', 'contact_person' => 'Test Person',
            'contact_number' => '09000000000', 'email' => 'supplier@test.com', 'tin' => '000-000-000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 1000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $supplierId, 'created_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('receiving', 'view');

        $this->actingAs($user)->getJson('/purchase-orders?option=list')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'view');

        $this->actingAs($user)->getJson('/purchase-orders?option=list')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'view');

        $this->actingAs($user)->post('/purchase-orders', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'encoder');

        $response = $this->actingAs($user)->post('/purchase-orders', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_void_denied_without_approver_grant(): void
    {
        $po = $this->makePurchaseOrder();
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'encoder');

        $this->actingAs($user)->patch("/purchase-orders/{$po->id}/void")->assertForbidden();
    }

    public function test_void_allowed_with_approver_grant(): void
    {
        $po = $this->makePurchaseOrder();
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'approver');

        $response = $this->actingAs($user)->patch("/purchase-orders/{$po->id}/void");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_update_status_denied_without_approver_grant(): void
    {
        $po = $this->makePurchaseOrder();
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'encoder');

        $this->actingAs($user)->put("/purchase-orders/{$po->id}/status")->assertForbidden();
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $po = $this->makePurchaseOrder();
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'approver');

        $this->actingAs($user)->delete("/purchase-orders/{$po->id}")->assertForbidden();
    }

    public function test_still_blocked_by_the_existing_role_gate(): void
    {
        // A user with the right permission grant but NOT Administrator/Warehouse
        // Manager must still be blocked — the outer role gate is untouched.
        $role = ListRole::create(['name' => 'Random Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'inventory')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id, 'submodule_id' => null, 'access_level' => 'admin',
        ]);

        $this->actingAs($user)->getJson('/purchase-orders?option=list')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PurchaseOrderPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing new is gated yet); `test_still_blocked_by_the_existing_role_gate` PASSES already (the pre-existing `role:` gate already covers it).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, inside the existing `role:Administrator,Warehouse Manager` group, change:
```php
        Route::get('/purchase-orders/next-po-number', [App\Http\Controllers\PurchaseOrderController::class, 'getNextPoNumber']);
        Route::resource('/purchase-orders', App\Http\Controllers\PurchaseOrderController::class);
        Route::put('/purchase-orders/{id}/status', [App\Http\Controllers\PurchaseOrderController::class, 'updateStatus']);
        Route::patch('/purchase-orders/{id}/void', [App\Http\Controllers\PurchaseOrderController::class, 'void']);
```
to:
```php
        Route::get('/purchase-orders/next-po-number', [App\Http\Controllers\PurchaseOrderController::class, 'getNextPoNumber'])
            ->middleware('permission:inventory,purchase_orders,encoder');
        Route::resource('/purchase-orders', App\Http\Controllers\PurchaseOrderController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory,purchase_orders,view')
            ->middlewareFor(['store', 'update'], 'permission:inventory,purchase_orders,encoder')
            ->middlewareFor('destroy', 'permission:inventory,purchase_orders,admin');
        Route::put('/purchase-orders/{id}/status', [App\Http\Controllers\PurchaseOrderController::class, 'updateStatus'])
            ->middleware('permission:inventory,purchase_orders,approver');
        Route::patch('/purchase-orders/{id}/void', [App\Http\Controllers\PurchaseOrderController::class, 'void'])
            ->middleware('permission:inventory,purchase_orders,approver');
```

And further down in the same group, change:
```php
        Route::get('/purchase-orders/{id}/print', [App\Http\Controllers\PurchaseOrderController::class, 'printPO']);
```
to:
```php
        Route::get('/purchase-orders/{id}/print', [App\Http\Controllers\PurchaseOrderController::class, 'printPO'])
            ->middleware('permission:inventory,purchase_orders,view');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PurchaseOrderPermissionTest`
Expected: PASS (9 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 185 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Inventory/PurchaseOrderPermissionTest.php
git commit -m "Enforce Inventory permissions on Purchase Orders

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Receiving (Received Stocks) backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Inventory/ReceivedStockPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `inventory,receiving,view|encoder|admin` enforcement on every Received Stock route.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inventory;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\ReceivedStock;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivedStockPermissionTest extends TestCase
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

    private function warehouseManagerWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Warehouse Manager'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'inventory')->firstOrFail();
        $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeReceivedStock(): ReceivedStock
    {
        $supplierId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'Test Supplier', 'address' => 'Test Address', 'contact_person' => 'Test Person',
            'contact_number' => '09000000000', 'email' => 'supplier@test.com', 'tin' => '000-000-000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $po = PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 1000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $supplierId, 'created_by_id' => User::factory()->create()->id,
        ]);

        return ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplierId,
            'received_date' => now()->toDateString(), 'received_no' => 'RS-TEST-' . uniqid(),
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'view');

        $this->actingAs($user)->getJson('/received-stocks')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('receiving', 'view');

        $this->actingAs($user)->getJson('/received-stocks')->assertOk();
    }

    public function test_pay_denied_without_encoder_grant(): void
    {
        $rs = $this->makeReceivedStock();
        $user = $this->warehouseManagerWithGrant('receiving', 'view'); // view only, not encoder

        $this->actingAs($user)
            ->post("/received-stocks/{$rs->id}/pay", ['amount' => 100, 'payment_mode' => 'Cash', 'payment_date' => now()->toDateString()])
            ->assertForbidden();
    }

    public function test_pay_allowed_with_encoder_grant(): void
    {
        $rs = $this->makeReceivedStock();
        $user = $this->warehouseManagerWithGrant('receiving', 'encoder');

        $response = $this->actingAs($user)
            ->post("/received-stocks/{$rs->id}/pay", ['amount' => 100, 'payment_mode' => 'Cash', 'payment_date' => now()->toDateString()]);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $rs = $this->makeReceivedStock();
        $user = $this->warehouseManagerWithGrant('receiving', 'encoder');

        $this->actingAs($user)->delete("/received-stocks/{$rs->id}")->assertForbidden();
    }

    public function test_destroy_allowed_with_admin_grant(): void
    {
        $rs = $this->makeReceivedStock();
        $user = $this->warehouseManagerWithGrant('receiving', 'admin');

        $response = $this->actingAs($user)->delete("/received-stocks/{$rs->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReceivedStockPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::get('/received-stocks/next-batch-code', [App\Http\Controllers\ReceivedStockController::class, 'getNextBatchCode']);
        Route::get('/accounting/cash-on-hand', [App\Http\Controllers\Modules\CashManagementController::class, 'cashOnHand']);
        Route::post('/received-stocks/{receivedStock}/pay', [App\Http\Controllers\ReceivedStockController::class, 'pay']);
        Route::resource('/received-stocks', App\Http\Controllers\ReceivedStockController::class);
```
to:
```php
        Route::get('/received-stocks/next-batch-code', [App\Http\Controllers\ReceivedStockController::class, 'getNextBatchCode'])
            ->middleware('permission:inventory,receiving,encoder');
        Route::get('/accounting/cash-on-hand', [App\Http\Controllers\Modules\CashManagementController::class, 'cashOnHand']);
        Route::post('/received-stocks/{receivedStock}/pay', [App\Http\Controllers\ReceivedStockController::class, 'pay'])
            ->middleware('permission:inventory,receiving,encoder');
        Route::resource('/received-stocks', App\Http\Controllers\ReceivedStockController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory,receiving,view')
            ->middlewareFor(['store', 'update'], 'permission:inventory,receiving,encoder')
            ->middlewareFor('destroy', 'permission:inventory,receiving,admin');
```

(`/accounting/cash-on-hand` is left untouched — it belongs to Accounting, not this pilot.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReceivedStockPermissionTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 191 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Inventory/ReceivedStockPermissionTest.php
git commit -m "Enforce Inventory permissions on Receiving/Received Stocks

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Inventory Stocks backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Inventory/InventoryStockPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `inventory,inventory_stocks,view|encoder|admin` enforcement on stock viewing/adjustment/conversion/weight-loss/price-update/settings.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryStocks;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStockPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function warehouseManagerWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Warehouse Manager'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'inventory')->firstOrFail();
        $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeStock(): InventoryStocks
    {
        return InventoryStocks::create([
            'batch_code' => 'BATCH-TEST-' . uniqid(),
            'quantity' => 10,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'view');

        $this->actingAs($user)->getJson('/inventory-stocks')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'view');

        $this->actingAs($user)->getJson('/inventory-stocks')->assertOk();
    }

    public function test_update_price_denied_without_encoder_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'view');

        $this->actingAs($user)->post("/inventory-stocks/{$stock->id}/update-price", [])->assertForbidden();
    }

    public function test_update_price_allowed_with_encoder_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'encoder');

        $response = $this->actingAs($user)->post("/inventory-stocks/{$stock->id}/update-price", []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_adjustment_denied_without_encoder_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'view');

        $this->actingAs($user)->post("/inventory-stocks/adjustment/{$stock->id}", [])->assertForbidden();
    }

    public function test_conversion_denied_without_encoder_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'view');

        $this->actingAs($user)->post('/inventory-stocks/conversions', [])->assertForbidden();
    }

    public function test_weight_loss_denied_without_encoder_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'view');

        $this->actingAs($user)->post("/inventory-stocks/{$stock->id}/weight-loss", [])->assertForbidden();
    }

    public function test_settings_denied_without_admin_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'encoder');

        $this->actingAs($user)->post("/inventory-stocks/{$stock->id}/settings", [])->assertForbidden();
    }

    public function test_settings_allowed_with_admin_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'admin');

        $response = $this->actingAs($user)->post("/inventory-stocks/{$stock->id}/settings", []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InventoryStockPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::resource('inventory-stocks', App\Http\Controllers\InventoryStockController::class);
        Route::post('inventory-stocks/adjustment/{id}', [App\Http\Controllers\InventoryAdjustmentController::class, 'store']);
        Route::post('/inventory-stocks/{id}/update-price', [App\Http\Controllers\InventoryStockController::class, 'update']);
        Route::post('/inventory-stocks/conversions', [App\Http\Controllers\ProductConversionController::class, 'store']);
        Route::post('/inventory-stocks/{id}/weight-loss', [App\Http\Controllers\WeightLossController::class, 'store']);
        Route::post('/inventory-stocks/{id}/settings', [App\Http\Controllers\InventoryStockController::class, 'settings']);
```
to:
```php
        Route::resource('inventory-stocks', App\Http\Controllers\InventoryStockController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory,inventory_stocks,view')
            ->middlewareFor('update', 'permission:inventory,inventory_stocks,encoder');
        Route::post('inventory-stocks/adjustment/{id}', [App\Http\Controllers\InventoryAdjustmentController::class, 'store'])
            ->middleware('permission:inventory,inventory_stocks,encoder');
        Route::post('/inventory-stocks/{id}/update-price', [App\Http\Controllers\InventoryStockController::class, 'update'])
            ->middleware('permission:inventory,inventory_stocks,encoder');
        Route::post('/inventory-stocks/conversions', [App\Http\Controllers\ProductConversionController::class, 'store'])
            ->middleware('permission:inventory,inventory_stocks,encoder');
        Route::post('/inventory-stocks/{id}/weight-loss', [App\Http\Controllers\WeightLossController::class, 'store'])
            ->middleware('permission:inventory,inventory_stocks,encoder');
        Route::post('/inventory-stocks/{id}/settings', [App\Http\Controllers\InventoryStockController::class, 'settings'])
            ->middleware('permission:inventory,inventory_stocks,admin');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InventoryStockPermissionTest`
Expected: PASS (9 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 200 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Inventory/InventoryStockPermissionTest.php
git commit -m "Enforce Inventory permissions on Inventory Stocks

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Stock Returns backend enforcement

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Inventory/StockReturnPermissionTest.php`

**Interfaces:**
- Consumes: `PermissionMiddleware` (Plan A)
- Produces: `inventory,stock_returns,view|encoder|approver` enforcement. `approve()` handles both "approve" and "reject" (via a `status` field) at the same `approver` level — there's no dynamic branching needed here, unlike Sales' shared-route case, because both outcomes require the identical permission.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inventory;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\RolePermission;
use App\Models\StockReturn;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReturnPermissionTest extends TestCase
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

    private function warehouseManagerWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Warehouse Manager'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'inventory')->firstOrFail();
        $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeStockReturn(): StockReturn
    {
        $supplierId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'Test Supplier', 'address' => 'Test Address', 'contact_person' => 'Test Person',
            'contact_number' => '09000000000', 'email' => 'supplier@test.com', 'tin' => '000-000-000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $po = PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 1000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $supplierId, 'created_by_id' => User::factory()->create()->id,
        ]);

        return StockReturn::create([
            'po_id' => $po->id, 'reason' => 'Damaged in transit',
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'created_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'view');

        $this->actingAs($user)->getJson('/stock-returns')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('stock_returns', 'view');

        $this->actingAs($user)->getJson('/stock-returns')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('stock_returns', 'view');

        $this->actingAs($user)->postJson('/stock-returns', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('stock_returns', 'encoder');

        $response = $this->actingAs($user)->postJson('/stock-returns', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_approve_denied_without_approver_grant(): void
    {
        $return = $this->makeStockReturn();
        $user = $this->warehouseManagerWithGrant('stock_returns', 'encoder');

        $this->actingAs($user)
            ->postJson("/stock-returns/{$return->id}/approve", ['status' => 'approved'])
            ->assertForbidden();
    }

    public function test_reject_denied_without_approver_grant(): void
    {
        // 'reject' shares the same approve() action (status: disapproved) — same gate.
        $return = $this->makeStockReturn();
        $user = $this->warehouseManagerWithGrant('stock_returns', 'encoder');

        $this->actingAs($user)
            ->postJson("/stock-returns/{$return->id}/approve", ['status' => 'disapproved'])
            ->assertForbidden();
    }

    public function test_approve_allowed_with_approver_grant(): void
    {
        $return = $this->makeStockReturn();
        $user = $this->warehouseManagerWithGrant('stock_returns', 'approver');

        $response = $this->actingAs($user)
            ->postJson("/stock-returns/{$return->id}/approve", ['status' => 'approved']);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_receive_item_denied_without_approver_grant(): void
    {
        $return = $this->makeStockReturn();
        $user = $this->warehouseManagerWithGrant('stock_returns', 'encoder');

        $this->actingAs($user)
            ->postJson("/stock-returns/{$return->id}/items/1/receive", ['replaced_quantity' => 1, 'loss_quantity' => 0])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StockReturnPermissionTest`
Expected: FAIL — the `denied` tests fail (nothing is gated yet).

- [ ] **Step 3: Add the middleware**

In `routes/web.php`, change:
```php
        Route::resource('/stock-returns', App\Http\Controllers\StockReturnController::class);
        Route::post('/stock-returns/{id}/approve', [App\Http\Controllers\StockReturnController::class, 'approve']);
        Route::post('/stock-returns/{id}/items/{itemId}/receive', [App\Http\Controllers\StockReturnController::class, 'receiveItem']);
```
to:
```php
        Route::resource('/stock-returns', App\Http\Controllers\StockReturnController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory,stock_returns,view')
            ->middlewareFor('store', 'permission:inventory,stock_returns,encoder');
        Route::post('/stock-returns/{id}/approve', [App\Http\Controllers\StockReturnController::class, 'approve'])
            ->middleware('permission:inventory,stock_returns,approver');
        Route::post('/stock-returns/{id}/items/{itemId}/receive', [App\Http\Controllers\StockReturnController::class, 'receiveItem'])
            ->middleware('permission:inventory,stock_returns,approver');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=StockReturnPermissionTest`
Expected: PASS (8 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 208 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add routes/web.php tests/Feature/Inventory/StockReturnPermissionTest.php
git commit -m "Enforce Inventory permissions on Stock Returns

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Frontend — replace hardcoded role checks with `can()`, gate previously-ungated buttons

**Files:**
- Modify: `resources/js/Pages/Modules/Inventory/Components/PurchaseOrders/View.vue`
- Modify: `resources/js/Pages/Modules/Inventory/Components/StockReturns/View.vue`
- Modify: `resources/js/Pages/Modules/Inventory/Tab/PurchaseOrdersTab.vue`
- Modify: `resources/js/Pages/Modules/Inventory/Tab/StockReturnsTab.vue`
- Modify: `resources/js/Pages/Modules/Inventory/Components/InventoryStocks/View.vue`
- Modify: `resources/js/Pages/Modules/Inventory/Modal/PayAccountsPayableModal.vue`

**Interfaces:**
- Consumes: `this.can(moduleKey, submoduleKey, level)` (Plan A's `permissionsMixin`)
- Produces: buttons that show/hide based on the same grants Tasks 2–5 now enforce server-side.

- [ ] **Step 1: `PurchaseOrders/View.vue` — replace `canApprove`, gate `canVoid`**

Change:
```js
  computed: {
    canApprove() {
      const roles = this.$page.props.roles;
      const userRoles = roles ? Object.values(roles) : [];
      return userRoles.some(role => ['Administrator', 'Warehouse Manager', 'Super Admin'].includes(role));
    },
    canVoid() {
      if (!this.purchaseOrder) return false;
      const statusName = this.purchaseOrder.status?.name;
      if (['Voided', 'Disapproved'].includes(statusName)) return false;
      const hasReceivedItems = (this.purchaseOrder.items || []).some(item => Number(item.received_quantity || 0) > 0);
      if (hasReceivedItems) return false;
      if (!this.purchaseOrder.approved_by_id) return false;
      return this.purchaseOrder.approved_by_id === this.$page.props.user.data.id;
    },
```
to:
```js
  computed: {
    canApprove() {
      return this.can('inventory', 'purchase_orders', 'approver');
    },
    canVoid() {
      if (!this.can('inventory', 'purchase_orders', 'approver')) return false;
      if (!this.purchaseOrder) return false;
      const statusName = this.purchaseOrder.status?.name;
      if (['Voided', 'Disapproved'].includes(statusName)) return false;
      const hasReceivedItems = (this.purchaseOrder.items || []).some(item => Number(item.received_quantity || 0) > 0);
      if (hasReceivedItems) return false;
      if (!this.purchaseOrder.approved_by_id) return false;
      return this.purchaseOrder.approved_by_id === this.$page.props.user.data.id;
    },
```

(The self-approval business rule — only the person who approved a PO can void it — is preserved unchanged; the permission check is an additional, earlier gate.)

- [ ] **Step 2: `StockReturns/View.vue` — replace `canApprove`**

Change:
```js
    canApprove() {
      const roles = this.$page.props.roles;
      const userRoles = roles ? Object.values(roles) : [];
      return userRoles.some(role => ['Administrator', 'Warehouse Manager', 'Super Admin'].includes(role));
    },
```
to:
```js
    canApprove() {
      return this.can('inventory', 'stock_returns', 'approver');
    },
```

- [ ] **Step 3: `PurchaseOrdersTab.vue` — gate "Receive Stock"**

Change:
```html
                          <button
                            v-if="hasPendingItems(list) && list.status?.name !== 'Voided'"
                            class="action-btn action-btn-receive"
                            @click="openReceiveStock(list)"
                            title="Receive Stock"
                          >
                            Receive Stock
                          </button>
```
to:
```html
                          <button
                            v-if="hasPendingItems(list) && list.status?.name !== 'Voided' && can('inventory', 'receiving', 'encoder')"
                            class="action-btn action-btn-receive"
                            @click="openReceiveStock(list)"
                            title="Receive Stock"
                          >
                            Receive Stock
                          </button>
```

- [ ] **Step 4: `StockReturnsTab.vue` — gate "Create Return"**

Change:
```html
            <button class="create-btn" @click="openReturnStockModal" :disabled="loadingOrders">
```
to:
```html
            <button v-if="can('inventory', 'stock_returns', 'encoder')" class="create-btn" @click="openReturnStockModal" :disabled="loadingOrders">
```

- [ ] **Step 5: `InventoryStocks/View.vue` — gate the actions dropdown**

Change:
```html
                <div class="action-dropdown-menu" v-if="showActions">
                  <button class="action-dropdown-item" @click="updatePrice(); showActions = false">
                    <i class="ri-price-tag-3-line"></i> Update Price
                  </button>
                  <button v-if="data.quantity > 0" class="action-dropdown-item" @click="adjustStock(); showActions = false">
                    <i class="ri-edit-line"></i> Adjust Stocks
                  </button>
                  <button v-if="data.quantity > 0" class="action-dropdown-item" @click="convertStock(); showActions = false">
                    <i class="ri-recycle-line"></i> Convert / Repack
                  </button>
                  <button class="action-dropdown-item" @click="recordLoss(); showActions = false">
                    <i class="ri-scales-3-line"></i> Record Loss
                  </button>
                  <div class="action-dropdown-divider"></div>
                  <button class="action-dropdown-item" @click="openSettings(); showActions = false">
                    <i class="ri-settings-3-line"></i> Settings
                  </button>
                </div>
```
to:
```html
                <div class="action-dropdown-menu" v-if="showActions">
                  <button v-if="can('inventory', 'inventory_stocks', 'encoder')" class="action-dropdown-item" @click="updatePrice(); showActions = false">
                    <i class="ri-price-tag-3-line"></i> Update Price
                  </button>
                  <button v-if="data.quantity > 0 && can('inventory', 'inventory_stocks', 'encoder')" class="action-dropdown-item" @click="adjustStock(); showActions = false">
                    <i class="ri-edit-line"></i> Adjust Stocks
                  </button>
                  <button v-if="data.quantity > 0 && can('inventory', 'inventory_stocks', 'encoder')" class="action-dropdown-item" @click="convertStock(); showActions = false">
                    <i class="ri-recycle-line"></i> Convert / Repack
                  </button>
                  <button v-if="can('inventory', 'inventory_stocks', 'encoder')" class="action-dropdown-item" @click="recordLoss(); showActions = false">
                    <i class="ri-scales-3-line"></i> Record Loss
                  </button>
                  <div class="action-dropdown-divider" v-if="can('inventory', 'inventory_stocks', 'admin')"></div>
                  <button v-if="can('inventory', 'inventory_stocks', 'admin')" class="action-dropdown-item" @click="openSettings(); showActions = false">
                    <i class="ri-settings-3-line"></i> Settings
                  </button>
                </div>
```

- [ ] **Step 6: `PayAccountsPayableModal.vue` — verify the Pay submit button is disabled without the grant**

Read the file first to find its submit button; add `|| !can('inventory', 'receiving', 'encoder')` to the button's existing `:disabled` binding (the exact expression depends on the button's current disabled logic — inspect the file before editing, matching the pattern used in every other step of this plan: read current content, then make the minimal targeted change).

- [ ] **Step 7: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 208 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 8: Rebuild assets**

Run: `npm run build`

- [ ] **Step 9: Manually verify with a real browser session**

1. Log in as `warehouse.manager@example.com` (Warehouse Manager — has `encoder`+`approver`+`view` module-wide from Task 1's seeding, once it's actually run — see the note at the end of this task) and confirm Approve/Void/Receive Stock/Create Return/the full action-dropdown are all visible.
2. Log in as `administrator@example.com` (Admin level — sees everything, including Settings).
3. Confirm a user with zero Inventory grants gets no action buttons and a 403 on direct navigation to `/purchase-orders`.

- [ ] **Step 10: Commit**

```bash
git add resources/js/Pages/Modules/Inventory/Components/PurchaseOrders/View.vue \
        resources/js/Pages/Modules/Inventory/Components/StockReturns/View.vue \
        resources/js/Pages/Modules/Inventory/Tab/PurchaseOrdersTab.vue \
        resources/js/Pages/Modules/Inventory/Tab/StockReturnsTab.vue \
        resources/js/Pages/Modules/Inventory/Components/InventoryStocks/View.vue \
        resources/js/Pages/Modules/Inventory/Modal/PayAccountsPayableModal.vue \
        public/build
git commit -m "Wire Inventory buttons to can() — replace hardcoded role checks

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Frontend — sidebar tab filtering + global nav Inventory link

**Files:**
- Modify: `resources/js/Pages/Modules/Inventory/Index.vue`
- Modify: `resources/js/Shared/Layouts/Components/Menu.vue`

**Interfaces:**
- Consumes: `this.canAny(moduleKey, submoduleKey)` (Plan A's `permissionsMixin`)
- Produces: the in-page Inventory tab list and the global left-nav "Inventory Management" link both reflect real grants.

- [ ] **Step 1: Filter the Inventory tab list**

In `resources/js/Pages/Modules/Inventory/Index.vue`, find the `data()` method's `tabs:` array (it ends just before the closing of `data()`) and add a computed property. If a `computed: {}` block already exists, add `visibleTabs` to it; otherwise add a new `computed: {}` block immediately after `data() { ... }` closes, following the exact same pattern used in Sales' `Index.vue` (Plan B, Task 7):

```js
  computed: {
    visibleTabs() {
      // purchaseRequests and accountsPayable are outside this pilot's
      // submodule catalog (spec §9/§12) — they stay visible to anyone who
      // can see the Inventory module at all, unfiltered, same as today.
      // Tab ids don't match submodule keys 1:1 — map them explicitly.
      const tabToSubmodule = {
        purchaseOrders: 'purchase_orders',
        receiving: 'receiving',
        productSummary: 'inventory_stocks',
        stockReturns: 'stock_returns',
      };
      return this.tabs.filter((tab) => {
        const submoduleKey = tabToSubmodule[tab.id];
        if (!submoduleKey) {
          return true;
        }
        return this.canAny('inventory', submoduleKey);
      });
    },
  },
```

Then change the tab-bar `v-for` from `v-for="tab in tabs"` to `v-for="tab in visibleTabs"` (inspect the template first to confirm the exact surrounding markup, matching Sales' `Index.vue` Task 7 pattern).

- [ ] **Step 2: Filter the global nav Inventory link**

In `resources/js/Shared/Layouts/Components/Menu.vue`, change:
```html
                <li class="nav-item"
                    v-if="$page.props.roles.includes('Warehouse Manager')  || $page.props.roles.includes('Super Admin')">
```
to:
```html
                <li class="nav-item"
                    v-if="canAny('inventory')">
```

- [ ] **Step 3: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 208 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 4: Rebuild assets**

Run: `npm run build`

- [ ] **Step 5: Manually verify with a real browser session**

1. Log in as a user with zero Inventory grants — confirm "Inventory Management" is gone from the left nav, and navigating to `/purchase-orders` directly now 403s.
2. Log in as `warehouse.manager@example.com` (once Task 1's seeder has actually been run — see note below) — confirm "Inventory Management" appears, and inside it, Purchase Orders / Received Stocks / Product Inventory / Stock Returns tabs are all visible (module-wide grant), while Purchase Requests / Accounts Payable remain visible regardless (unfiltered, matching today).
3. Log in as `administrator@example.com` — confirm every tab is visible.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Modules/Inventory/Index.vue resources/js/Shared/Layouts/Components/Menu.vue public/build
git commit -m "Filter Inventory sidebar tabs and nav link by permission

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

**Before this task's manual verification is meaningful in any real environment (local dev DB or production), Task 1's `InventoryDefaultPermissionsSeeder` must actually be run there** — the seeder existing in code isn't enough, exactly like Plan B's `SalesDefaultPermissionsSeeder` needed a real run before its backend enforcement tasks landed. Confirm with the user before running it on production, and — learning from Plan B's near-lockout — run it there *before or immediately after* deploying Task 2 (the first task that actually starts enforcing anything), not after all 7 tasks are pushed.

---

## Self-Review

**Spec coverage** (against `docs/superpowers/specs/2026-08-14-dynamic-role-access-design.md` §9 Inventory row and §10):
- Purchase Orders Encoder (Create, Edit) / Approver (Approve status, Void) / View (List, View, Print) → Tasks 2, 6
- Receiving Encoder (Receive stock, Record payment) / View (List, View) → Tasks 3, 6
- Inventory Stocks Encoder (Adjustment, Conversion, Weight-loss, Update price) / View (List, View) → Tasks 4, 6 (also added Admin/settings, matching spec §4's general Admin-includes-settings rule)
- Stock Returns Encoder (Create return request) / Approver (Approve, Reject, Receive item) / View (List, View) → Tasks 5, 6
- §10 rollout safety (no day-one lockout) → Task 1, with an explicit lesson-learned note (deploy+seed before pushing further enforcement, not after) carried over from Plan B's near-miss
- §7 sidebar filtering → Task 7
- §6 "sits inside the existing role:Administrator,Warehouse Manager group as a second, more specific check" → the defining constraint of every backend task; verified with a dedicated `test_still_blocked_by_the_existing_role_gate` test in Task 2

**Placeholder scan:** no "TBD"/"handle edge cases"/"similar to Task N" with one deliberate, narrow exception — Task 6 Step 6 (`PayAccountsPayableModal.vue`) asks the implementer to read the file before editing rather than presupposing its exact current disabled-state expression, because that file wasn't read during this plan's grounding (everything else in this plan was verified against actual file contents first). This is flagged explicitly rather than guessed at.

**Type consistency:** `can()`/`canAny()` calls (Task 6–7) use the exact module/submodule keys seeded in Plan A's `ModulesAndSubmodulesSeeder` (`inventory`, `purchase_orders`, `receiving`, `inventory_stocks`, `stock_returns`). The `permission:...` middleware strings (Tasks 2–5) match `PermissionMiddleware`'s documented `module,submodule,level` / `module,level` argument order exactly as built in Plan A.

---

## After This Plan

This completes the pilot scope defined in the original design spec (§9: Sales + Inventory). Extending enforcement to the remaining modules (Payroll, Employees, Customers, Accounting, User Management, Dashboard) — whose catalog rows already exist from Plan A's seeder but aren't enforced yet — is explicitly out of scope per spec §12 and would be proposed as its own follow-up plan if/when the business wants it.
