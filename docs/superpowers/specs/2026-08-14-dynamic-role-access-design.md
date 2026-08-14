# Dynamic Role-Based Access Control (Module / Submodule / Access Level)

**Status:** Approved design, pending implementation plan
**Date:** 2026-08-14

## 1. Problem

Access control today is minimal and coarse:

- `ListRole` is just a catalog of role names (Administrator, Warehouse Manager, Sales Rep, …). It carries no permissions.
- `UserRole` assigns one or more roles to a user.
- `RoleMiddleware` reads a hardcoded `role:Name1,Name2` list off individual route-group definitions in `routes/web.php`. Only **5** route groups in the entire app use it. `Super Admin` bypasses every check.
- Every other route — including all of **Sales** (Sales Orders, AR Invoices, Receipts, Sales Returns) — has **no role restriction at all**; any authenticated user can reach it.
- Where a role *is* checked (e.g. Inventory: `role:Administrator,Warehouse Manager`), it gates the **entire route group as one block** — there's no way to let someone create a Purchase Order without also being able to approve/void it.
- There is no concept of per-action or per-button permission anywhere in the codebase.

This is fine for a small team where everyone with a role is trusted with everything that role's routes expose, but it doesn't support separating who can *enter* data from who can *approve* it, or restricting a role to read-only.

## 2. Goal

Let an administrator configure, per **Role**, which **Modules/Submodules** that role can access, and at what **Access Level(s)** — enforced on both the backend (the real gate) and the frontend (hiding/disabling buttons the user can't use). Built as a general framework, then wired into two real modules (**Sales**, **Inventory**) as a pilot to prove the pattern end-to-end.

## 3. Terminology

| Term | Meaning |
|---|---|
| **Module** | A top-level business area: Sales, Inventory, Payroll, Employees, Customers, Accounting, User Management, Dashboard. |
| **Submodule** | A section within a module, e.g. Sales → *Sales Orders*, *AR Invoices*, *Receipts*, *Sales Returns*. |
| **Access Level** | One of four fixed levels: **Encoder**, **Approver**, **View**, **Admin**. A role may hold any combination of these per module/submodule (not mutually exclusive). |
| **Role** | Unchanged — the existing `ListRole` entries. This feature adds a permission layer on top of existing roles; it does not replace or rename them. |
| **Grant** | One row recording that a Role holds one Access Level for one Module (and optionally one Submodule). |

`Super Admin` keeps its existing global bypass (unchanged behavior in `RoleMiddleware`, extended to this new system too). Every other role — including `Administrator` — needs explicit grants, same as today's convention where only `Super Admin` is special-cased.

## 4. Access levels → what they unlock

| Level | Typical actions/buttons |
|---|---|
| **View** | List, view details, print, export/generate report |
| **Encoder** | Create, edit/update, save draft |
| **Approver** | Approve, reject, void, review, receive |
| **Admin** | Everything above, plus delete and module-specific settings |

A role with **zero** grants for a module does not see that module in the sidebar navigation, and receives a 403 if the URL is requested directly.

Grants are additive: if a role holds both Encoder and Approver for a submodule, both sets of buttons are available. Admin is not automatically inferred from holding all three of the others — it must be granted explicitly, and it implies delete/settings access that the other three don't.

## 5. Data model

Three new tables.

**`modules`**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| key | string, unique | machine key, e.g. `sales`, `inventory` |
| name | string | display name, e.g. "Sales" |
| sort_order | int | for consistent admin-UI ordering |
| timestamps | | |

**`submodules`**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| module_id | FK → modules | cascade on delete |
| key | string | machine key, e.g. `sales_orders` |
| name | string | display name, e.g. "Sales Orders" |
| sort_order | int | |
| timestamps | | |
| | | unique on (module_id, key) |

**`role_permissions`**
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| role_id | FK → list_roles | cascade on delete |
| module_id | FK → modules | cascade on delete |
| submodule_id | FK → submodules, **nullable** | NULL = applies to the whole module (all its submodules); a specific value scopes the grant to just that submodule |
| access_level | string/enum | `encoder` \| `approver` \| `view` \| `admin` |
| timestamps | | |
| | | unique on (role_id, module_id, submodule_id, access_level) |

Both `modules` and `submodules` are a seeded catalog (matching the app's real areas/pages), not something end users invent freely — "dynamic" refers to the **Role → Module/Submodule → Level assignment** being data-driven and admin-configurable, replacing the hardcoded route-file checks, not to the module list itself being arbitrary.

Checking whether a role holds a level for a given (module, submodule) means checking for a matching row where `submodule_id` is either that submodule's id **or** NULL (module-wide grant) — either satisfies it.

## 6. Backend enforcement

- A `PermissionService` with a method such as `roleHasAccess(int $roleId, string $moduleKey, ?string $submoduleKey, string $level): bool`, and a user-level helper that checks across all of the user's active roles (mirroring how `RoleMiddleware` already unions role checks) plus the `Super Admin` bypass.
- A new `permission:module_key,submodule_key,level` route middleware, applied per-route the same way `role:...` is applied today. Example: `Route::patch('/purchase-orders/{id}/void', ...)->middleware('permission:inventory,purchase_orders,approver')`.
- The existing 5 `role:...` route-group gates are **left untouched**. This is an additional, finer-grained layer — for pilot routes it's the *only* gate (Sales currently has none); for Inventory it sits inside the existing `role:Administrator,Warehouse Manager` group as a second, more specific check.
- Unauthorized access returns 403 with a clear message, consistent with `RoleMiddleware`'s existing behavior.

## 7. Frontend enforcement

- The current user's effective permissions are computed once per request and shared globally via Inertia (the same mechanism that already shares `auth.user` in `HandleInertiaRequests`), shaped roughly as `{ sales: { sales_orders: ['view','encoder'], ar_invoices: ['view'] }, inventory: { purchase_orders: ['view','encoder','approver'] } }`.
- A small shared helper/composable, `can(moduleKey, submoduleKey, level)`, used in Vue templates to `v-if`/disable buttons: `v-if="can('sales', 'sales_orders', 'encoder')"` around the Create/Edit buttons, `v-if="can('sales', 'sales_orders', 'approver')"` around Approve/Cancel, etc.
- Sidebar navigation items are filtered by whether the user holds *any* level for that module/submodule.
- Frontend hiding is a UX convenience only — section 6's backend checks are the actual security boundary.

## 8. Admin UI

Extends the existing Role management screen (`/libraries/roles`, `Modules/Libraries/Roles/`) rather than introducing a new page. Add a **Permissions** panel to a role's edit view: a table of Module → Submodule rows, each with four checkboxes (Encoder / Approver / View / Admin). Saving diffs the checked state against `role_permissions` and inserts/deletes rows accordingly, inside a DB transaction.

## 9. Pilot: Sales + Inventory

Real backend + frontend enforcement wired into these two modules, using the level mapping from section 4. Representative (not exhaustive — final per-button mapping is confirmed screen-by-screen during implementation):

**Sales**
| Submodule | Encoder | Approver | View |
|---|---|---|---|
| Sales Orders | Create, Edit | Approve, Cancel | List, View, Print |
| AR Invoices | Record payment | — | List, View, Print |
| Receipts | Create, Edit | — | List, View |
| Sales Returns | Create/request return | Approve, Reject, Receive replacement | List, View |

**Inventory**
| Submodule | Encoder | Approver | View |
|---|---|---|---|
| Purchase Orders | Create, Edit | Approve status, Void | List, View, Print |
| Receiving (Received Stocks) | Receive stock, Record payment | — | List, View |
| Inventory Stocks | Adjustment, Conversion, Weight-loss, Update price | — | List, View |
| Stock Returns | Create return request | Approve, Reject, Receive item | List, View |

Other modules (Payroll, Employees, Customers, Accounting, User Management, Dashboard) get their `modules`/`submodules` catalog rows seeded so they already appear in the admin UI, but are **not enforced yet** — they keep today's access behavior (open to any authenticated user, or gated by whatever existing `role:` middleware already covers them) until a follow-up phase wires them the same way.

## 10. Rollout safety — no day-one lockout

Sales is currently open to any authenticated user, and Inventory is gated only at the whole-module level. Turning on fine-grained enforcement with zero existing grants would immediately 403 real staff mid-shift. Before the pilot ships:

- Seed default `role_permissions` for roles that currently have implicit access, so nobody loses a capability they have today:
  - `Administrator` → Admin level on Sales and Inventory (all submodules).
  - `Warehouse Manager` → Encoder + Approver + View on all Inventory submodules (matches today's unrestricted access within the already-gated group).
  - Any role that currently uses Sales routes (to be confirmed against real usage/logs before shipping) → Encoder + View on Sales submodules.
- This seeding is a one-time migration, reviewed with the business owner before running on production (consistent with the project's standing rule that monetary/business-configuration data is entered/approved by the owner, not invented by the assistant) — in this case it's assigning access levels, not money, but the same "confirm before applying to production" discipline applies.

## 11. Testing

- Unit tests for `PermissionService` (module-wide vs submodule-specific grants, multiple levels combining, Super Admin bypass, no-grant → denied).
- Feature tests per pilot route: 403 without the right grant, 200/success with it, for both Encoder-only and Approver-only role fixtures.
- Manual click-through: a test role with only View cannot see Create/Approve buttons and gets 403 if the endpoint is hit directly; a test role with only Encoder can create but not approve; Admin can do everything.

## 12. Explicitly out of scope for this phase

- Wiring enforcement into modules beyond Sales and Inventory (follow-up work, same pattern).
- Per-field permissions (e.g., "can edit price but not quantity") — only whole-action granularity.
- Time-bound or conditional grants (e.g., "Approver only for orders under ₱10,000").
- Removing/renaming the existing `ListRole`/`UserRole`/`RoleMiddleware` system — it stays as the coarse first gate.
