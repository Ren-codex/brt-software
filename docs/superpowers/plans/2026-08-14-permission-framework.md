# Permission Framework (Plan A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the core dynamic role-permission system — data model, backend enforcement service/middleware, Inertia sharing, a frontend `can()` helper, and an admin UI to assign Module/Submodule/Access-Level grants to a Role — as a complete, testable deliverable with no real routes/buttons wired to it yet.

**Architecture:** Three new tables (`modules`, `submodules`, `role_permissions`) sit alongside the existing `list_roles`/`user_roles`. A `PermissionService` answers "does this user hold level X for module/submodule Y" by unioning grants across the user's active roles (with a `Super Admin` bypass, mirroring `RoleMiddleware`'s existing convention). A new `permission:module,submodule,level` route middleware enforces it server-side; a computed permission map is shared to every Inertia page (like `roles`/`user` already are) and consumed via a small global Vue mixin (`this.can(...)`) for button-level UI gating. The admin UI extends the existing `/libraries/roles` screen with a new "Manage Permissions" modal rather than a new page.

**Tech Stack:** Laravel 11 (Eloquent, migrations, FormRequest, middleware), Pest/PHPUnit (`RefreshDatabase`, class-based `TestCase`), Vue 3 Options API + Inertia.js, axios.

## Global Constraints

- Follow existing codebase conventions exactly: services in `app/Services/<Area>/`, thin controllers using the `HandlesTransaction` trait for writes, `ValidationException::withMessages()` for validation errors, Bootstrap-style `.modal-overlay > .modal-container > .modal-header/.modal-body/.modal-footer` markup with **no scoped CSS redefining that chrome** (per `CLAUDE.md`), action buttons live in `.modal-footer` only.
- New DB columns use plain `string` (not a native `ENUM` type) for the access-level field — this codebase has hit real pain earlier from raw `ALTER TABLE ... ENUM(...)` not being portable to the SQLite test DB; a `string` + application-level `in:` validation avoids that entirely.
- `Super Admin` (by `list_roles.name`) always has full access, matching `RoleMiddleware`'s existing bypass. No other role name is special-cased.
- This plan does **not** touch `routes/web.php`'s existing 5 `role:...` groups, and does **not** apply the new `permission` middleware to any real route — that's Plan B (Sales) and Plan C (Inventory), written and executed after this plan ships.
- Run the full suite (`php artisan test`) after each task; the pre-existing baseline is **122 passing / 19 failing** (all 19 are known pre-existing failures unrelated to this work — Breeze scaffold tests, dead `/expenses` routes, a `products.code` factory gap). Any *new* failure is a regression to fix before moving on.

---

### Task 1: Data model — `modules`, `submodules`, `role_permissions` tables + Eloquent models

**Files:**
- Create: `database/migrations/2026_08_14_100000_create_modules_table.php`
- Create: `database/migrations/2026_08_14_100001_create_submodules_table.php`
- Create: `database/migrations/2026_08_14_100002_create_role_permissions_table.php`
- Create: `app/Models/Module.php`
- Create: `app/Models/Submodule.php`
- Create: `app/Models/RolePermission.php`
- Modify: `app/Models/ListRole.php`
- Test: `tests/Feature/Permissions/PermissionModelsTest.php`

**Interfaces:**
- Produces: `Module` (`id`, `key`, `name`, `sort_order`; `submodules()` hasMany, `rolePermissions()` hasMany), `Submodule` (`id`, `module_id`, `key`, `name`, `sort_order`; `module()` belongsTo, `rolePermissions()` hasMany), `RolePermission` (`id`, `role_id`, `module_id`, `submodule_id` nullable, `access_level` string; `role()`/`module()`/`submodule()` belongsTo, `const LEVELS = ['encoder','approver','view','admin']`), `ListRole::permissions()` hasMany `RolePermission`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\Submodule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_submodule_and_role_permission_relationships_resolve(): void
    {
        $module = Module::create(['key' => 'sales', 'name' => 'Sales', 'sort_order' => 1]);
        $submodule = Submodule::create([
            'module_id' => $module->id,
            'key' => 'sales_orders',
            'name' => 'Sales Orders',
            'sort_order' => 1,
        ]);
        $role = ListRole::create([
            'name' => 'Test Encoder Role',
            'type' => 'role',
            'definition' => 'For testing',
            'is_active' => true,
        ]);

        $grant = RolePermission::create([
            'role_id' => $role->id,
            'module_id' => $module->id,
            'submodule_id' => $submodule->id,
            'access_level' => 'encoder',
        ]);

        $this->assertTrue($module->submodules->contains($submodule));
        $this->assertTrue($module->rolePermissions->contains($grant));
        $this->assertTrue($submodule->rolePermissions->contains($grant));
        $this->assertTrue($role->permissions->contains($grant));
        $this->assertEquals('sales', $grant->module->key);
        $this->assertEquals('sales_orders', $grant->submodule->key);
        $this->assertEquals($role->id, $grant->role->id);
    }

    public function test_role_permission_submodule_id_can_be_null_for_module_wide_grant(): void
    {
        $module = Module::create(['key' => 'inventory', 'name' => 'Inventory', 'sort_order' => 2]);
        $role = ListRole::create([
            'name' => 'Test Admin Role',
            'type' => 'role',
            'definition' => 'For testing',
            'is_active' => true,
        ]);

        $grant = RolePermission::create([
            'role_id' => $role->id,
            'module_id' => $module->id,
            'submodule_id' => null,
            'access_level' => 'admin',
        ]);

        $this->assertNull($grant->submodule_id);
        $this->assertNull($grant->submodule);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PermissionModelsTest`
Expected: FAIL — `modules` table doesn't exist yet.

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_08_14_100000_create_modules_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
```

`database/migrations/2026_08_14_100001_create_submodules_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submodules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['module_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submodules');
    }
};
```

`database/migrations/2026_08_14_100002_create_role_permissions_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('list_roles')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            // Nullable = grant applies to the whole module (all its submodules).
            // Note: a DB-level unique constraint across a nullable column would not
            // reliably prevent duplicate module-wide rows (MySQL treats each NULL as
            // distinct), so the save endpoint (Task 6) enforces uniqueness by
            // deleting-and-recreating a role's grants atomically rather than relying
            // on a DB constraint for that case.
            $table->foreignId('submodule_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('access_level'); // encoder | approver | view | admin
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
```

- [ ] **Step 4: Create the models**

`app/Models/Module.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['key', 'name', 'sort_order'];

    public function submodules()
    {
        return $this->hasMany(Submodule::class)->orderBy('sort_order');
    }

    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class);
    }
}
```

`app/Models/Submodule.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submodule extends Model
{
    protected $fillable = ['module_id', 'key', 'name', 'sort_order'];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class);
    }
}
```

`app/Models/RolePermission.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    public const LEVELS = ['encoder', 'approver', 'view', 'admin'];

    protected $fillable = ['role_id', 'module_id', 'submodule_id', 'access_level'];

    public function role()
    {
        return $this->belongsTo(ListRole::class, 'role_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function submodule()
    {
        return $this->belongsTo(Submodule::class);
    }
}
```

Modify `app/Models/ListRole.php` — add the `permissions()` relation inside the class body:
```php
    public function permissions()
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PermissionModelsTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 124 passing / 19 failing (same 19 pre-existing failures; +2 from this task)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_14_100000_create_modules_table.php \
        database/migrations/2026_08_14_100001_create_submodules_table.php \
        database/migrations/2026_08_14_100002_create_role_permissions_table.php \
        app/Models/Module.php app/Models/Submodule.php app/Models/RolePermission.php \
        app/Models/ListRole.php tests/Feature/Permissions/PermissionModelsTest.php
git commit -m "Add modules/submodules/role_permissions data model

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Seed the module/submodule catalog

**Files:**
- Create: `database/seeders/ModulesAndSubmodulesSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php:18` (add the call right after `ChartOfAccountsSeeder::class`)
- Test: `tests/Feature/Permissions/ModulesAndSubmodulesSeederTest.php`

**Interfaces:**
- Consumes: `Module`, `Submodule` (Task 1)
- Produces: seeded rows with keys `sales`, `inventory`, `payroll`, `employees`, `customers`, `accounting`, `user_management`, `dashboard`; submodules `sales_orders`/`ar_invoices`/`receipts`/`sales_returns` under `sales`, and `purchase_orders`/`receiving`/`inventory_stocks`/`stock_returns` under `inventory`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Models\Module;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesAndSubmodulesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_expected_modules_and_pilot_submodules(): void
    {
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $expectedModules = [
            'sales', 'inventory', 'payroll', 'employees',
            'customers', 'accounting', 'user_management', 'dashboard',
        ];
        foreach ($expectedModules as $key) {
            $this->assertDatabaseHas('modules', ['key' => $key]);
        }

        $sales = Module::where('key', 'sales')->firstOrFail();
        $this->assertEquals(
            ['sales_orders', 'ar_invoices', 'receipts', 'sales_returns'],
            $sales->submodules->pluck('key')->all()
        );

        $inventory = Module::where('key', 'inventory')->firstOrFail();
        $this->assertEquals(
            ['purchase_orders', 'receiving', 'inventory_stocks', 'stock_returns'],
            $inventory->submodules->pluck('key')->all()
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ModulesAndSubmodulesSeeder::class);
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $this->assertEquals(8, Module::count());
        $this->assertEquals(4, Module::where('key', 'sales')->firstOrFail()->submodules()->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ModulesAndSubmodulesSeederTest`
Expected: FAIL — class `Database\Seeders\ModulesAndSubmodulesSeeder` not found.

- [ ] **Step 3: Write the seeder**

`database/seeders/ModulesAndSubmodulesSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Submodule;
use Illuminate\Database\Seeder;

class ModulesAndSubmodulesSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['key' => 'sales', 'name' => 'Sales', 'sort_order' => 1, 'submodules' => [
                ['key' => 'sales_orders', 'name' => 'Sales Orders', 'sort_order' => 1],
                ['key' => 'ar_invoices', 'name' => 'AR Invoices', 'sort_order' => 2],
                ['key' => 'receipts', 'name' => 'Receipts', 'sort_order' => 3],
                ['key' => 'sales_returns', 'name' => 'Sales Returns', 'sort_order' => 4],
            ]],
            ['key' => 'inventory', 'name' => 'Inventory', 'sort_order' => 2, 'submodules' => [
                ['key' => 'purchase_orders', 'name' => 'Purchase Orders', 'sort_order' => 1],
                ['key' => 'receiving', 'name' => 'Receiving', 'sort_order' => 2],
                ['key' => 'inventory_stocks', 'name' => 'Inventory Stocks', 'sort_order' => 3],
                ['key' => 'stock_returns', 'name' => 'Stock Returns', 'sort_order' => 4],
            ]],
            ['key' => 'payroll', 'name' => 'Payroll', 'sort_order' => 3, 'submodules' => []],
            ['key' => 'employees', 'name' => 'Employee Profiling', 'sort_order' => 4, 'submodules' => []],
            ['key' => 'customers', 'name' => 'Customers & Contacts', 'sort_order' => 5, 'submodules' => []],
            ['key' => 'accounting', 'name' => 'Accounting', 'sort_order' => 6, 'submodules' => []],
            ['key' => 'user_management', 'name' => 'User Management', 'sort_order' => 7, 'submodules' => []],
            ['key' => 'dashboard', 'name' => 'Dashboard', 'sort_order' => 8, 'submodules' => []],
        ];

        foreach ($catalog as $entry) {
            $module = Module::firstOrCreate(
                ['key' => $entry['key']],
                ['name' => $entry['name'], 'sort_order' => $entry['sort_order']]
            );

            foreach ($entry['submodules'] as $sub) {
                Submodule::firstOrCreate(
                    ['module_id' => $module->id, 'key' => $sub['key']],
                    ['name' => $sub['name'], 'sort_order' => $sub['sort_order']]
                );
            }
        }
    }
}
```

- [ ] **Step 4: Register it in `DatabaseSeeder.php`**

In `database/seeders/DatabaseSeeder.php`, change:
```php
        $this->call(ChartOfAccountsSeeder::class);
```
to:
```php
        $this->call(ChartOfAccountsSeeder::class);
        $this->call(ModulesAndSubmodulesSeeder::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ModulesAndSubmodulesSeederTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 126 passing / 19 failing

- [ ] **Step 7: Commit**

```bash
git add database/seeders/ModulesAndSubmodulesSeeder.php database/seeders/DatabaseSeeder.php \
        tests/Feature/Permissions/ModulesAndSubmodulesSeederTest.php
git commit -m "Seed the module/submodule catalog

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: `PermissionService` — the core access check

**Files:**
- Create: `app/Services/System/Permission/PermissionService.php`
- Test: `tests/Feature/Permissions/PermissionServiceTest.php`

**Interfaces:**
- Consumes: `User::roles()` (existing relation to `list_roles` via `user_roles`, already used by `RoleMiddleware`), `Module`, `Submodule`, `RolePermission` (Task 1)
- Produces: `PermissionService::userHasAccess(User $user, string $moduleKey, ?string $submoduleKey, string $level): bool` and `PermissionService::userPermissionMap(User $user): array` — both consumed by Task 4 (middleware) and Task 5 (Inertia sharing).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\Submodule;
use App\Models\User;
use App\Models\UserRole;
use App\Services\System\Permission\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $permissions;
    private Module $salesModule;
    private Submodule $salesOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissions = app(PermissionService::class);
        $this->salesModule = Module::create(['key' => 'sales', 'name' => 'Sales', 'sort_order' => 1]);
        $this->salesOrders = Submodule::create([
            'module_id' => $this->salesModule->id, 'key' => 'sales_orders', 'name' => 'Sales Orders', 'sort_order' => 1,
        ]);
    }

    private function userWithRole(string $roleName): array
    {
        $role = ListRole::create(['name' => $roleName, 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1]);

        return [$user, $role];
    }

    public function test_denies_when_no_grant_exists(): void
    {
        [$user] = $this->userWithRole('No Grants Role');

        $this->assertFalse($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'view'));
    }

    public function test_allows_with_a_matching_submodule_specific_grant(): void
    {
        [$user, $role] = $this->userWithRole('Encoder Role');
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => $this->salesOrders->id, 'access_level' => 'encoder',
        ]);

        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'encoder'));
        $this->assertFalse($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'approver'));
    }

    public function test_module_wide_grant_satisfies_any_submodule_in_that_module(): void
    {
        [$user, $role] = $this->userWithRole('Module Wide View Role');
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => null, 'access_level' => 'view',
        ]);

        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'view'));
        $this->assertFalse($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'encoder'));
    }

    public function test_admin_level_satisfies_checks_for_the_other_three_levels(): void
    {
        [$user, $role] = $this->userWithRole('Admin Role');
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => $this->salesOrders->id, 'access_level' => 'admin',
        ]);

        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'view'));
        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'encoder'));
        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'approver'));
        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'admin'));
    }

    public function test_holding_encoder_does_not_satisfy_an_admin_only_check(): void
    {
        [$user, $role] = $this->userWithRole('Encoder Only Role');
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => $this->salesOrders->id, 'access_level' => 'encoder',
        ]);

        $this->assertFalse($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'admin'));
    }

    public function test_super_admin_bypasses_every_check_with_no_grants(): void
    {
        [$user] = $this->userWithRole('Super Admin');

        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'admin'));
        $this->assertTrue($this->permissions->userHasAccess($user, 'inventory', 'purchase_orders', 'approver'));
    }

    public function test_inactive_user_role_does_not_grant_access(): void
    {
        $role = ListRole::create(['name' => 'Inactive Grant Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 0]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => null, 'access_level' => 'admin',
        ]);

        $this->assertFalse($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'view'));
    }

    public function test_permission_map_shapes_module_wide_and_submodule_grants(): void
    {
        [$user, $role] = $this->userWithRole('Mixed Grants Role');
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => $this->salesOrders->id, 'access_level' => 'encoder',
        ]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => $this->salesOrders->id, 'access_level' => 'view',
        ]);

        $map = $this->permissions->userPermissionMap($user);

        $this->assertEqualsCanonicalizing(['encoder', 'view'], $map['sales']['sales_orders']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PermissionServiceTest`
Expected: FAIL — class `App\Services\System\Permission\PermissionService` not found.

- [ ] **Step 3: Write the service**

`app/Services/System/Permission/PermissionService.php`:
```php
<?php

namespace App\Services\System\Permission;

use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Collection;

class PermissionService
{
    public function userHasAccess(User $user, string $moduleKey, ?string $submoduleKey, string $level): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $roleIds = $this->activeRoleIds($user);
        if ($roleIds->isEmpty()) {
            return false;
        }

        $module = Module::where('key', $moduleKey)->first();
        if (!$module) {
            return false;
        }

        $submoduleId = null;
        if ($submoduleKey !== null) {
            $submodule = $module->submodules()->where('key', $submoduleKey)->first();
            if (!$submodule) {
                return false;
            }
            $submoduleId = $submodule->id;
        }

        return RolePermission::whereIn('role_id', $roleIds)
            ->where('module_id', $module->id)
            ->where(function ($query) use ($submoduleId) {
                $query->whereNull('submodule_id');
                if ($submoduleId !== null) {
                    $query->orWhere('submodule_id', $submoduleId);
                }
            })
            ->whereIn('access_level', array_unique([$level, 'admin']))
            ->exists();
    }

    /**
     * Build the full permission map for a user, shaped for Inertia sharing:
     * ['sales' => ['sales_orders' => ['view','encoder'], '_module' => ['admin']]]
     * '_module' holds module-wide (submodule_id null) grants.
     */
    public function userPermissionMap(User $user): array
    {
        if ($this->isSuperAdmin($user)) {
            return $this->fullAccessMap();
        }

        $roleIds = $this->activeRoleIds($user);
        if ($roleIds->isEmpty()) {
            return [];
        }

        $grants = RolePermission::whereIn('role_id', $roleIds)
            ->with(['module', 'submodule'])
            ->get();

        $map = [];
        foreach ($grants as $grant) {
            if (!$grant->module) {
                continue;
            }
            $moduleKey = $grant->module->key;
            $subKey = $grant->submodule->key ?? '_module';
            $map[$moduleKey][$subKey][] = $grant->access_level;
        }

        foreach ($map as $moduleKey => $subs) {
            foreach ($subs as $subKey => $levels) {
                $map[$moduleKey][$subKey] = array_values(array_unique($levels));
            }
        }

        return $map;
    }

    protected function isSuperAdmin(User $user): bool
    {
        return $user->roles()->where('user_roles.is_active', 1)->where('name', 'Super Admin')->exists();
    }

    protected function activeRoleIds(User $user): Collection
    {
        return $user->roles()->where('user_roles.is_active', 1)->pluck('list_roles.id');
    }

    protected function fullAccessMap(): array
    {
        $map = [];
        foreach (Module::all() as $module) {
            $map[$module->key]['_module'] = RolePermission::LEVELS;
        }

        return $map;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PermissionServiceTest`
Expected: PASS (8 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 134 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add app/Services/System/Permission/PermissionService.php tests/Feature/Permissions/PermissionServiceTest.php
git commit -m "Add PermissionService: the core role/module/submodule/level check

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: `PermissionMiddleware` + route alias

**Files:**
- Create: `app/Http/Middleware/PermissionMiddleware.php`
- Modify: `bootstrap/app.php:29-31` (add `'permission'` alias next to `'role'`)
- Test: `tests/Feature/Permissions/PermissionMiddlewareTest.php`

**Interfaces:**
- Consumes: `PermissionService::userHasAccess()` (Task 3)
- Produces: route middleware usable as `->middleware('permission:module,level')` or `->middleware('permission:module,submodule,level')` — consumed by Plan B/C when real routes are gated.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\Submodule;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Module $inventoryModule;
    private Submodule $purchaseOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryModule = Module::create(['key' => 'inventory', 'name' => 'Inventory', 'sort_order' => 1]);
        $this->purchaseOrders = Submodule::create([
            'module_id' => $this->inventoryModule->id, 'key' => 'purchase_orders', 'name' => 'Purchase Orders', 'sort_order' => 1,
        ]);

        Route::middleware(['web', 'auth', 'permission:inventory,purchase_orders,encoder'])
            ->get('/__test/permission-submodule-check', fn () => response('ok'));

        Route::middleware(['web', 'auth', 'permission:inventory,view'])
            ->get('/__test/permission-module-check', fn () => response('ok'));
    }

    public function test_guest_is_redirected_or_denied(): void
    {
        $this->get('/__test/permission-submodule-check')->assertRedirect();
    }

    public function test_authenticated_user_without_grant_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/__test/permission-submodule-check')
            ->assertForbidden();
    }

    public function test_authenticated_user_with_matching_submodule_grant_is_allowed(): void
    {
        $role = ListRole::create(['name' => 'PO Encoder', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->inventoryModule->id,
            'submodule_id' => $this->purchaseOrders->id, 'access_level' => 'encoder',
        ]);

        $this->actingAs($user)
            ->get('/__test/permission-submodule-check')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_module_wide_two_argument_form_checks_module_level_only(): void
    {
        $role = ListRole::create(['name' => 'Inventory Viewer', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->inventoryModule->id,
            'submodule_id' => null, 'access_level' => 'view',
        ]);

        $this->actingAs($user)
            ->get('/__test/permission-module-check')
            ->assertOk();
    }

    public function test_super_admin_bypasses_the_middleware(): void
    {
        $role = ListRole::create(['name' => 'Super Admin', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1]);

        $this->actingAs($user)
            ->get('/__test/permission-submodule-check')
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PermissionMiddlewareTest`
Expected: FAIL — `permission` middleware alias is not defined.

- [ ] **Step 3: Write the middleware**

`app/Http/Middleware/PermissionMiddleware.php`:
```php
<?php

namespace App\Http\Middleware;

use App\Services\System\Permission\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function __construct(protected PermissionService $permissions)
    {
    }

    /**
     * Usage: 'permission:module,level' (module-wide) or
     * 'permission:module,submodule,level' (submodule-specific).
     */
    public function handle(Request $request, Closure $next, string $moduleKey, string $submoduleKeyOrLevel, ?string $level = null): Response
    {
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        if ($level === null) {
            $submoduleKey = null;
            $requiredLevel = $submoduleKeyOrLevel;
        } else {
            $submoduleKey = $submoduleKeyOrLevel;
            $requiredLevel = $level;
        }

        if (!$this->permissions->userHasAccess(Auth::user(), $moduleKey, $submoduleKey, $requiredLevel)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the middleware alias**

In `bootstrap/app.php`, change:
```php
        $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class
```
to:
```php
        $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        'permission' => \App\Http\Middleware\PermissionMiddleware::class
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PermissionMiddlewareTest`
Expected: PASS (5 tests)

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 139 passing / 19 failing

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/PermissionMiddleware.php bootstrap/app.php \
        tests/Feature/Permissions/PermissionMiddlewareTest.php
git commit -m "Add permission route middleware

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Share the permission map to every Inertia page

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/Permissions/InertiaPermissionsShareTest.php`

**Interfaces:**
- Consumes: `PermissionService::userPermissionMap()` (Task 3)
- Produces: Inertia shared prop `permissions` (object keyed by module key), consumed by Task 7's frontend mixin.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\Submodule;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaPermissionsShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_props_include_the_users_permission_map(): void
    {
        $module = Module::create(['key' => 'sales', 'name' => 'Sales', 'sort_order' => 1]);
        $submodule = Submodule::create([
            'module_id' => $module->id, 'key' => 'sales_orders', 'name' => 'Sales Orders', 'sort_order' => 1,
        ]);
        $role = ListRole::create(['name' => 'Share Test Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submodule->id, 'access_level' => 'view',
        ]);

        $this->actingAs($user);

        $shared = app(HandleInertiaRequests::class)->share(app('request'));

        $this->assertEquals(['view'], $shared['permissions']['sales']['sales_orders']);
    }

    public function test_guest_gets_an_empty_permission_map(): void
    {
        $shared = app(HandleInertiaRequests::class)->share(app('request'));

        $this->assertEquals([], $shared['permissions']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=InertiaPermissionsShareTest`
Expected: FAIL — `permissions` key missing from the shared array.

- [ ] **Step 3: Modify `HandleInertiaRequests`**

In `app/Http/Middleware/HandleInertiaRequests.php`, add the import and the new shared key:
```php
use App\Services\System\Permission\PermissionService;
```

Change the `share()` method's return array from:
```php
        return [
            ...parent::share($request),
            'user' => (\Auth::check()) ? new UserResource(User::with('employee')->where('id',\Auth::user()->id)->first()) : '',
            'roles' => (\Auth::check()) ? \Auth::user()->roles()->where('user_roles.is_active', 1)->pluck('name') : '',
            'flash' => [
```
to:
```php
        return [
            ...parent::share($request),
            'user' => (\Auth::check()) ? new UserResource(User::with('employee')->where('id',\Auth::user()->id)->first()) : '',
            'roles' => (\Auth::check()) ? \Auth::user()->roles()->where('user_roles.is_active', 1)->pluck('name') : '',
            'permissions' => (\Auth::check()) ? app(PermissionService::class)->userPermissionMap(\Auth::user()) : [],
            'flash' => [
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=InertiaPermissionsShareTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 141 passing / 19 failing

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/Permissions/InertiaPermissionsShareTest.php
git commit -m "Share the user's permission map to every Inertia page

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Admin backend — read/save a role's permission grid

**Files:**
- Create: `app/Http/Controllers/Libraries/RolePermissionController.php`
- Create: `app/Http/Requests/Libraries/RolePermissionRequest.php`
- Create: `app/Services/Libraries/RolePermissionClass.php`
- Modify: `routes/web.php` (add 2 routes inside the existing `role:Administrator` group that already contains `/libraries/roles`)
- Test: `tests/Feature/Permissions/RolePermissionEndpointTest.php`

**Interfaces:**
- Consumes: `Module`, `Submodule`, `RolePermission`, `ListRole` (Task 1), `ModulesAndSubmodulesSeeder` (Task 2)
- Produces: `GET /libraries/roles/{id}/permissions` → `{ role: {id,name}, modules: [{id,key,name,levels:[],submodules:[{id,key,name,levels:[]}]}] }`; `POST /libraries/roles/{id}/permissions` with body `{ grants: [{module_id, submodule_id, access_level}] }` — both consumed by Task 8's `Permissions.vue`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use App\Models\User;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
        $this->withoutMiddleware();
        $this->actingAs(User::factory()->create());
    }

    public function test_show_returns_full_catalog_with_empty_levels_when_role_has_no_grants(): void
    {
        $role = ListRole::create(['name' => 'Fresh Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $response = $this->getJson("/libraries/roles/{$role->id}/permissions");

        $response->assertOk();
        $salesModule = collect($response->json('modules'))->firstWhere('key', 'sales');
        $this->assertNotNull($salesModule);
        $this->assertEquals([], $salesModule['levels']);
        $salesOrders = collect($salesModule['submodules'])->firstWhere('key', 'sales_orders');
        $this->assertEquals([], $salesOrders['levels']);
    }

    public function test_show_reflects_existing_grants(): void
    {
        $role = ListRole::create(['name' => 'Granted Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $module = \App\Models\Module::where('key', 'inventory')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id, 'submodule_id' => null, 'access_level' => 'view',
        ]);

        $response = $this->getJson("/libraries/roles/{$role->id}/permissions");

        $inventory = collect($response->json('modules'))->firstWhere('key', 'inventory');
        $this->assertEquals(['view'], $inventory['levels']);
    }

    public function test_update_replaces_the_roles_grants(): void
    {
        $role = ListRole::create(['name' => 'Updatable Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $salesModule = \App\Models\Module::where('key', 'sales')->firstOrFail();
        $salesOrders = $salesModule->submodules()->where('key', 'sales_orders')->firstOrFail();

        // Pre-existing grant that should be removed by the update.
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $salesModule->id,
            'submodule_id' => null, 'access_level' => 'view',
        ]);

        $response = $this->postJson("/libraries/roles/{$role->id}/permissions", [
            'grants' => [
                ['module_id' => $salesModule->id, 'submodule_id' => $salesOrders->id, 'access_level' => 'encoder'],
                ['module_id' => $salesModule->id, 'submodule_id' => $salesOrders->id, 'access_level' => 'approver'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('role_permissions', 2);
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $role->id, 'submodule_id' => null]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id, 'module_id' => $salesModule->id,
            'submodule_id' => $salesOrders->id, 'access_level' => 'encoder',
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id, 'module_id' => $salesModule->id,
            'submodule_id' => $salesOrders->id, 'access_level' => 'approver',
        ]);
    }

    public function test_update_rejects_an_invalid_access_level(): void
    {
        $role = ListRole::create(['name' => 'Invalid Level Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $salesModule = \App\Models\Module::where('key', 'sales')->firstOrFail();

        $response = $this->postJson("/libraries/roles/{$role->id}/permissions", [
            'grants' => [
                ['module_id' => $salesModule->id, 'submodule_id' => null, 'access_level' => 'super-hacker'],
            ],
        ]);

        $response->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RolePermissionEndpointTest`
Expected: FAIL — route not defined (404).

- [ ] **Step 3: Write the FormRequest**

`app/Http/Requests/Libraries/RolePermissionRequest.php`:
```php
<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class RolePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grants' => 'array',
            'grants.*.module_id' => 'required|integer|exists:modules,id',
            'grants.*.submodule_id' => 'nullable|integer|exists:submodules,id',
            'grants.*.access_level' => 'required|string|in:encoder,approver,view,admin',
        ];
    }
}
```

- [ ] **Step 4: Write the service**

`app/Services/Libraries/RolePermissionClass.php`:
```php
<?php

namespace App\Services\Libraries;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;

class RolePermissionClass
{
    public function catalogForRole(int $roleId): array
    {
        $role = ListRole::findOrFail($roleId);
        $modules = Module::with('submodules')->orderBy('sort_order')->get();
        $grants = RolePermission::where('role_id', $roleId)->get();

        $grantsByKey = [];
        foreach ($grants as $grant) {
            $key = $grant->module_id . ':' . ($grant->submodule_id ?? 'null');
            $grantsByKey[$key][] = $grant->access_level;
        }

        return [
            'role' => ['id' => $role->id, 'name' => $role->name],
            'modules' => $modules->map(function (Module $module) use ($grantsByKey) {
                return [
                    'id' => $module->id,
                    'key' => $module->key,
                    'name' => $module->name,
                    'levels' => $grantsByKey[$module->id . ':null'] ?? [],
                    'submodules' => $module->submodules->map(function ($sub) use ($module, $grantsByKey) {
                        return [
                            'id' => $sub->id,
                            'key' => $sub->key,
                            'name' => $sub->name,
                            'levels' => $grantsByKey[$module->id . ':' . $sub->id] ?? [],
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }

    public function save(int $roleId, array $grants): array
    {
        $role = ListRole::findOrFail($roleId);

        RolePermission::where('role_id', $roleId)->delete();

        foreach ($grants as $grant) {
            RolePermission::create([
                'role_id' => $roleId,
                'module_id' => $grant['module_id'],
                'submodule_id' => $grant['submodule_id'] ?? null,
                'access_level' => $grant['access_level'],
            ]);
        }

        return [
            'data' => $this->catalogForRole($roleId),
            'message' => 'Permissions updated successfully!',
            'info' => "Permissions for {$role->name} have been saved.",
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

`app/Http/Controllers/Libraries/RolePermissionController.php`:
```php
<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\Controller;
use App\Http\Requests\Libraries\RolePermissionRequest;
use App\Services\Libraries\RolePermissionClass;
use App\Traits\HandlesTransaction;

class RolePermissionController extends Controller
{
    use HandlesTransaction;

    public function __construct(protected RolePermissionClass $rolePermission)
    {
    }

    public function show(int $id)
    {
        return response()->json($this->rolePermission->catalogForRole($id));
    }

    public function update(RolePermissionRequest $request, int $id)
    {
        $result = $this->handleTransaction(function () use ($request, $id) {
            return $this->rolePermission->save($id, $request->validated()['grants'] ?? []);
        });

        return response()->json($result);
    }
}
```

- [ ] **Step 6: Add the routes**

In `routes/web.php`, inside the existing `role:Administrator` group that contains `Route::resource('/libraries/roles', ...)`, add directly below that line:
```php
        Route::resource('/libraries/roles', App\Http\Controllers\Libraries\RoleController::class);
        Route::get('/libraries/roles/{id}/permissions', [App\Http\Controllers\Libraries\RolePermissionController::class, 'show']);
        Route::post('/libraries/roles/{id}/permissions', [App\Http\Controllers\Libraries\RolePermissionController::class, 'update']);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=RolePermissionEndpointTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 145 passing / 19 failing

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Libraries/RolePermissionController.php \
        app/Http/Requests/Libraries/RolePermissionRequest.php \
        app/Services/Libraries/RolePermissionClass.php \
        routes/web.php tests/Feature/Permissions/RolePermissionEndpointTest.php
git commit -m "Add admin endpoints to read/save a role's permission grants

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Frontend `can()`/`canAny()` helper

**Files:**
- Create: `resources/js/Shared/permissions.js`
- Modify: `resources/js/app.js`

**Interfaces:**
- Consumes: Inertia shared prop `permissions` (Task 5), available in every Options-API component as `this.$page.props.permissions` (Inertia's Vue3 plugin registers `$page` as a global property — already how `this.$page.props.user`/`roles` would be read elsewhere in this app).
- Produces: global instance methods `this.can(moduleKey, [submoduleKey,] level)` and `this.canAny(moduleKey, [submoduleKey])`, available in every Vue component — consumed by Plan B/C to gate buttons and sidebar items.

- [ ] **Step 1: Write `resources/js/Shared/permissions.js`**

```js
function hasLevel(levels, level) {
    return Array.isArray(levels) && (levels.includes(level) || levels.includes('admin'));
}

/**
 * this.can('sales', 'view')                     -> module-wide check
 * this.can('sales', 'sales_orders', 'encoder')   -> submodule-specific check
 * A module-wide grant always satisfies a submodule-specific check for that module.
 */
export const permissionsMixin = {
    methods: {
        can(moduleKey, submoduleKeyOrLevel, level) {
            let submoduleKey = submoduleKeyOrLevel;
            let requiredLevel = level;
            if (typeof level === 'undefined') {
                submoduleKey = null;
                requiredLevel = submoduleKeyOrLevel;
            }

            const permissions = this.$page?.props?.permissions || {};
            const moduleGrants = permissions[moduleKey];
            if (!moduleGrants) {
                return false;
            }

            if (submoduleKey && hasLevel(moduleGrants[submoduleKey], requiredLevel)) {
                return true;
            }

            return hasLevel(moduleGrants._module, requiredLevel);
        },
        canAny(moduleKey, submoduleKey) {
            const permissions = this.$page?.props?.permissions || {};
            const moduleGrants = permissions[moduleKey];
            if (!moduleGrants) {
                return false;
            }

            const levels = submoduleKey
                ? (moduleGrants[submoduleKey] || [])
                : Object.values(moduleGrants).flat();

            const moduleWide = moduleGrants._module || [];

            return levels.length > 0 || moduleWide.length > 0;
        },
    },
};
```

- [ ] **Step 2: Register it globally in `resources/js/app.js`**

Add the import near the top, with the other `@/Shared` imports:
```js
import { permissionsMixin } from '@/Shared/permissions';
```

Change the `setup()` chain from:
```js
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(store)
            .use(BootstrapVueNext)
```
to:
```js
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(store)
            .mixin(permissionsMixin)
            .use(BootstrapVueNext)
```

- [ ] **Step 3: Rebuild assets and manually verify**

Run: `npm run build`

Then, with `php artisan serve` + `npm run dev` running locally: open any Inertia page in the browser, open the browser console, and confirm the global mixin is active by checking a mounted Vue component instance exposes `can`/`canAny` (e.g., via Vue DevTools → select any component → Setup/Methods, or temporarily add `mounted() { console.log(this.can('sales','sales_orders','view')); }` to any existing page and confirm it logs `true`/`false` without throwing). Remove any temporary debug line afterward.

There is no JS test runner configured in this repository, so this step is manual verification rather than an automated test — consistent with how the rest of this codebase's Vue behavior has been verified throughout this project.

- [ ] **Step 4: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 145 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Shared/permissions.js resources/js/app.js public/build
git commit -m "Add global can()/canAny() permission helper for Vue components

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 8: Admin UI — "Manage Permissions" modal on the Roles screen

**Files:**
- Create: `resources/js/Pages/Modules/Libraries/Roles/Modals/Permissions.vue`
- Modify: `resources/js/Pages/Modules/Libraries/Roles/Index.vue`

**Interfaces:**
- Consumes: `GET`/`POST /libraries/roles/{id}/permissions` (Task 6)
- Produces: a working end-to-end admin flow — pick a role, check Encoder/Approver/View/Admin per module/submodule, save.

- [ ] **Step 1: Create the modal**

`resources/js/Pages/Modules/Libraries/Roles/Modals/Permissions.vue`:
```vue
<template>
  <Teleport to="body">
    <div v-if="showModal" class="modal-overlay active" @click.self="hide">
      <div class="modal-container modal-lg" @click.stop>
        <div class="modal-header">
          <div>
            <h2>Manage Permissions</h2>
            <p class="modal-subtitle" v-if="role">{{ role.name }}</p>
          </div>
          <button class="close-btn" @click="hide">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <div class="modal-body">
          <div v-if="loading" class="permissions-loading">
            <i class="ri-loader-4-line spinner"></i> Loading...
          </div>
          <table v-else class="table permissions-table">
            <thead>
              <tr>
                <th>Module / Submodule</th>
                <th class="text-center">Encoder</th>
                <th class="text-center">Approver</th>
                <th class="text-center">View</th>
                <th class="text-center">Admin</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="mod in modules" :key="'m' + mod.id">
                <tr class="module-row">
                  <td><strong>{{ mod.name }}</strong></td>
                  <td class="text-center" v-for="lvl in levels" :key="lvl">
                    <input
                      type="checkbox"
                      :checked="mod.levels.includes(lvl)"
                      @change="toggle(mod.id, null, lvl, $event.target.checked)"
                    >
                  </td>
                </tr>
                <tr v-for="sub in mod.submodules" :key="'s' + sub.id" class="submodule-row">
                  <td class="submodule-name">{{ sub.name }}</td>
                  <td class="text-center" v-for="lvl in levels" :key="lvl">
                    <input
                      type="checkbox"
                      :checked="sub.levels.includes(lvl)"
                      @change="toggle(mod.id, sub.id, lvl, $event.target.checked)"
                    >
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
          <div class="success-alert" v-if="saveSuccess">
            <i class="ri-checkbox-circle-fill"></i>
            <span>Permissions saved successfully!</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-cancel" @click="hide">
            <i class="ri-close-line"></i>
            Cancel
          </button>
          <button type="button" class="btn btn-save" :disabled="saving || loading" @click="save">
            <i class="ri-save-line" v-if="!saving"></i>
            <i class="ri-loader-4-line spinner" v-else></i>
            {{ saving ? 'Saving...' : 'Save Permissions' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
import axios from 'axios';

export default {
  name: 'RolePermissions',
  emits: ['saved'],
  data() {
    return {
      showModal: false,
      loading: false,
      saving: false,
      saveSuccess: false,
      role: null,
      modules: [],
      levels: ['encoder', 'approver', 'view', 'admin'],
    };
  },
  methods: {
    show(role) {
      this.role = role;
      this.showModal = true;
      this.saveSuccess = false;
      this.fetch();
    },
    hide() {
      this.showModal = false;
    },
    async fetch() {
      this.loading = true;
      try {
        const res = await axios.get(`/libraries/roles/${this.role.id}/permissions`);
        this.modules = res.data.modules;
      } finally {
        this.loading = false;
      }
    },
    toggle(moduleId, submoduleId, level, checked) {
      const target = submoduleId
        ? this.modules.find((m) => m.id === moduleId).submodules.find((s) => s.id === submoduleId)
        : this.modules.find((m) => m.id === moduleId);

      if (checked) {
        if (!target.levels.includes(level)) target.levels.push(level);
      } else {
        target.levels = target.levels.filter((l) => l !== level);
      }
    },
    async save() {
      this.saving = true;
      const grants = [];
      this.modules.forEach((mod) => {
        mod.levels.forEach((level) => grants.push({ module_id: mod.id, submodule_id: null, access_level: level }));
        mod.submodules.forEach((sub) => {
          sub.levels.forEach((level) => grants.push({ module_id: mod.id, submodule_id: sub.id, access_level: level }));
        });
      });

      try {
        await axios.post(`/libraries/roles/${this.role.id}/permissions`, { grants });
        this.saveSuccess = true;
        this.$emit('saved');
        setTimeout(() => {
          this.saveSuccess = false;
        }, 2000);
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.permissions-table { width: 100%; }
.module-row td { background: #f7fbf9; font-weight: 600; }
.submodule-row .submodule-name { padding-left: 2rem; color: #5a7a73; }
.permissions-loading { display: flex; align-items: center; gap: .5rem; padding: 2rem; justify-content: center; color: #6b8c85; }
.modal-subtitle { font-size: .8rem; color: #6b8c85; margin: 0; }
</style>
```

(This follows `CLAUDE.md`'s modal contract: only `.modal-overlay > .modal-container > .modal-header/.modal-body/.modal-footer` structural classes are used, none redefined by scoped CSS; action buttons live in `.modal-footer`; the scoped styles here only touch the permission-grid's own content classes.)

- [ ] **Step 2: Wire it into `Index.vue`**

In `resources/js/Pages/Modules/Libraries/Roles/Index.vue`, add the action button right after the existing Edit button:
```html
                                            <button @click="openEdit(list,index)" class="action-btn action-btn-edit" v-b-tooltip.hover title="Edit">
                                                <i class="ri-pencil-line"></i>
                                            </button>
                                            <button @click="openPermissions(list)" class="action-btn action-btn-permissions" v-b-tooltip.hover title="Manage Permissions">
                                                <i class="ri-shield-keyhole-line"></i>
                                            </button>
```

Add the modal instance next to the existing ones:
```html
    <Create @add="fetch()" ref="create"/>
    <Delete @delete="fetch()" ref="delete"/>
    <Permissions ref="permissions"/>
```

Import and register the component — change:
```js
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
```
to also import it:
```js
import _ from 'lodash';
import Multiselect from "@vueform/multiselect";
import PageHeader from '@/Shared/Components/PageHeader.vue';
import Permissions from './Modals/Permissions.vue';
```

Change the `components:` entry from:
```js
    components: { PageHeader, Pagination, Multiselect , Create ,Delete, TableLoadingRow },
```
to:
```js
    components: { PageHeader, Pagination, Multiselect , Create ,Delete, Permissions, TableLoadingRow },
```

Add the trigger method next to the existing `openEdit(data,index){...}` method:
```js
        openPermissions(role) {
            this.$refs.permissions.show(role);
        },
```

- [ ] **Step 3: Rebuild assets**

Run: `npm run build`

- [ ] **Step 4: Manually verify end-to-end**

With `php artisan serve` running and logged in as a Super Admin / Administrator user:
1. Go to **Libraries → Roles** and confirm every role row now shows a shield icon next to Edit.
2. Click it for any role → the modal opens, shows a loading state, then a full table of all 8 modules (Sales and Inventory expanded with their 4 submodules each) with unchecked boxes.
3. Check a few boxes (e.g., Sales → Sales Orders → Encoder, and Inventory as a whole → View), click **Save Permissions** → confirm the success message appears.
4. Close and reopen the modal for the same role → confirm the checkboxes you set are still checked (proves the save round-trips correctly).
5. In `php artisan tinker`, confirm the rows exist: `\App\Models\RolePermission::count()` should be `> 0`, and each row's `access_level` should be one of `encoder|approver|view|admin`.

- [ ] **Step 5: Run the PHP suite to confirm nothing broke**

Run: `php artisan test`
Expected: 145 passing / 19 failing (unchanged — this task is frontend-only)

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Modules/Libraries/Roles/Modals/Permissions.vue \
        resources/js/Pages/Modules/Libraries/Roles/Index.vue public/build
git commit -m "Add Manage Permissions modal to the Roles screen

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage** (against `docs/superpowers/specs/2026-08-14-dynamic-role-access-design.md`):
- §5 Data model → Task 1
- §5 catalog seeding → Task 2
- §6 Backend enforcement (service + middleware) → Tasks 3–4
- §7 Frontend enforcement (shared props + `can()`) → Tasks 5, 7
- §8 Admin UI → Tasks 6, 8
- §9 Pilot module wiring and §10 rollout-safety seeding → explicitly deferred to Plan B (Sales) and Plan C (Inventory), not in this plan's scope
- §11 Testing → a test task accompanies every backend task; Tasks 7–8 (frontend-only) use manual verification since this repo has no JS test runner

**Placeholder scan:** no "TBD"/"handle edge cases"/"similar to Task N" — every step has literal, complete code.

**Type consistency:** `PermissionService::userHasAccess(User, string, ?string, string): bool` and `::userPermissionMap(User): array` (Task 3) are called identically in Task 4's middleware and Task 5's `HandleInertiaRequests` change. The Inertia `permissions` shape (`{module: {submodule_or_'_module': [levels]}}`) produced in Task 3/5 matches exactly what Task 7's `permissionsMixin` reads. The `RolePermissionClass::catalogForRole()` shape (`{role, modules:[{id,key,name,levels,submodules:[...]}]}`) produced in Task 6 matches exactly what Task 8's `Permissions.vue` renders and re-serializes on save.

---

## After This Plan

Once Plan A is merged and deployed, **Plan B (wire Sales)** and **Plan C (wire Inventory)** each: apply `permission:...` middleware to their real routes, seed default grants for the roles that currently have implicit access to that module (per spec §10, avoiding a day-one lockout — reviewed with the business owner before running on production), and add `v-if="can(...)"` around the real buttons in each screen's Vue components using the mapping in spec §9. Each gets written as its own plan document following this same process.
