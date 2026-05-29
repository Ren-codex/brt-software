# Group 1: Fund Management & Expense Approval — Design Spec

**Date:** 2026-05-29
**Status:** Approved
**Scope:** Two features that form the foundation of the petty cash workflow

---

## Overview

Two missing capabilities block the full expense lifecycle:

1. **Fund Management** — No UI exists to create, edit, top-up, or deactivate petty cash funds. Funds must be created directly in the database.
2. **Expense Approval** — The `approved` status and its route/controller were removed in a previous PR, breaking the `recorded → approved → released` flow.

Both are added under the existing patterns in the codebase (Libraries CRUD + inline action buttons).

---

## 1. Fund Management

### Placement

Lives at `/libraries/funds`, inside the existing `role:Administrator` Libraries route group. This follows the exact pattern used by Brands, Units, Roles, Positions, etc.

Accessible to: **Administrator** and **Top Management** roles (same as the expense module).

### Routes

```
GET    /libraries/funds              FundController@index        (Inertia page + option=lists)
POST   /libraries/funds              FundController@store
PUT    /libraries/funds/{id}         FundController@update
PATCH  /libraries/funds/{id}/toggle-active   FundController@toggleActive
POST   /libraries/funds/{id}/top-up          FundController@topUp
PATCH  /libraries/funds/{id}/balance         FundController@adjustBalance
```

### Backend Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Libraries/FundController.php` | Thin controller using `HandlesTransaction`; same structure as `BrandController` |
| `app/Services/Libraries/FundClass.php` | All business logic: create, update, toggleActive, topUp, adjustBalance |
| `app/Http/Requests/Libraries/FundRequest.php` | Validates name, gl_code (unique), weekly_budget (nullable numeric ≥ 0) |

### Business Logic

**`create` / `update`**
- Fields: `name`, `gl_code` (unique), `weekly_budget` (nullable).
- `balance` is not settable on create (starts at 0) or via the edit modal. Only changed via top-up or balance adjustment.
- `is_active` defaults to `true`.

**`topUp($id, $request)`**
- Validates: `amount` (numeric > 0), `date` (date), `description` (nullable string).
- Creates a `PettyCashTransaction` record with `type = 'replenishment'`.
- Increments `petty_cash_funds.balance` by `amount`.
- Runs inside `DB::transaction`.

**`adjustBalance($id, $request)`**
- Validates: `balance` (numeric ≥ 0), `reason` (required string, for audit note).
- Directly sets `petty_cash_funds.balance` to the submitted value. No transaction record.
- Intended for corrections only (rounding errors, cash counts).

**`toggleActive($id)`**
- Flips `is_active`. Deactivated funds cannot receive new expenses (validated in `ExpenseClass::save()` — add check: if fund is inactive, throw `ValidationException`).

### Frontend Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Modules/Libraries/Funds/Index.vue` | Main page — library-card + table, matches Brands/Units pattern |
| `resources/js/Pages/Modules/Libraries/Funds/Modals/Create.vue` | Create + edit modal (same modal, edit pre-fills fields) |
| `resources/js/Pages/Modules/Libraries/Funds/Modals/TopUp.vue` | Formal deposit modal |
| `resources/js/Pages/Modules/Libraries/Funds/Modals/AdjustBalance.vue` | Direct balance correction modal |

**Table columns:** Name · GL Code · Balance · Weekly Budget · Status · Actions

**Row actions:**
- **Top-up** button (always visible for active funds) — opens TopUp modal
- **Adjust** button — opens AdjustBalance modal
- **Edit** button — opens Create modal in edit mode (name, GL code, weekly budget only)
- **Toggle Active** — inline pill/button, greys out the row when deactivated

**Sidebar nav:** Add "Funds" link to the Libraries sidebar, between existing entries. The nav item is visible only to Administrator and Top Management roles (uses the existing role-check pattern in the sidebar).

---

## 2. Expense Approval

### Status Flow

```
recorded  ──(approve)──►  approved  ──(submit to replenishment)──►  submitted
                                    ──(manual release)──────────►  released
                                                                      ↑
                                                          (budget check + journal entry)
Replenishment approval:
submitted ──(replenishment approved)──► reimbursed
recorded  ──(replenishment approved)──► approved → reimbursed  (bulk promotion)
```

### Routes

Restored inside the existing `role:Administrator,Top Management,Area Business Manager,Super Admin` middleware group:

```
PATCH /expenses/{id}/approve    ExpenseController@approve
```

### Backend Changes

**`ExpenseController@approve($id)`**
- Calls `$this->expense->approve($id)` via `handleTransaction`.
- Returns JSON: `{ message, status, data }` — same shape as `destroy`.

**`ExpenseClass@approve($id)`**
- Finds expense by ID.
- Guard: if `status !== 'recorded'`, returns `{ status: 'error', message: 'Only recorded expenses can be approved.' }` (soft error, no exception).
- Updates status to `approved`.
- Returns `ExpenseResource` of the updated expense (with `added_by` eager-loaded).

**Replenishment bulk promotion** (addition to `ReplenishmentClass` or equivalent approval method):
- When a replenishment request is approved, iterate its linked expenses.
- Any expense still in `recorded` status is updated to `approved`, then immediately to `reimbursed`.
- Any expense already in `approved` is updated to `reimbursed`.
- Runs inside the existing replenishment approval transaction.

### Frontend Changes

**`resources/js/Pages/Modules/Expenses/Index.vue`**

Add an "Approve" button to both the table row actions and the expanded row detail card:

- Visible when: `list.status === 'recorded'` AND current user role is Administrator, Top Management, Area Business Manager, or Super Admin.
- `variant="success"`, icon `ri-check-double-line`, tooltip "Approve".
- On click: `axios.patch('/expenses/${list.id}/approve')` → on success, update the row's status in the local list (no full reload) and show a success toast.
- On error (soft error from service): show the error message in a toast.

The button sits between the existing table columns, consistent with the Edit/Delete button placement.

---

## Data Flow Summary

```
User (Admin/Top Mgmt)
  │
  ├─► POST /libraries/funds              → FundClass::create()
  ├─► POST /libraries/funds/{id}/top-up  → FundClass::topUp()  → PettyCashTransaction + balance++
  ├─► PATCH /libraries/funds/{id}/balance → FundClass::adjustBalance() → balance = X
  ├─► PATCH /libraries/funds/{id}/toggle-active → FundClass::toggleActive()
  │
  └─► PATCH /expenses/{id}/approve       → ExpenseClass::approve() → status: recorded → approved
```

---

## Error Handling

| Scenario | Handling |
|----------|---------|
| Top-up amount ≤ 0 | `FundRequest` validation rejects it |
| Adjust balance < 0 | `FundRequest` validation rejects it |
| Expense not in `recorded` state when approving | Soft error returned (`status: 'error'`), displayed as toast |
| Approving expense on an inactive fund | Not blocked at approval — fund check only on new expense creation |
| GL code collision on create | `FundRequest` unique rule catches it, Inertia surfaces as field error |

---

## Testing

- `tests/Feature/Libraries/FundManagementTest.php` — create fund, top-up increments balance, adjust balance sets directly, deactivated fund rejects new expenses
- `tests/Feature/Expenses/ExpenseApprovalTest.php` — approve moves status to `approved`, non-recorded expense returns soft error, replenishment approval bulk-promotes linked expenses

---

## Out of Scope (Group 2+)

- Void/cancel expense status
- Budget carryover
- Export / print views
- Notifications for low balance
