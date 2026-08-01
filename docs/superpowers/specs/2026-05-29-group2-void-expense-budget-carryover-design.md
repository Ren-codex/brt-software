# Group 2: Void Expense & Budget Carryover — Design Spec

**Date:** 2026-05-29
**Status:** Approved
**Scope:** Two features that complete the expense lifecycle and budget period management

---

## Overview

Two capabilities that extend the petty cash workflow:

1. **Void Expense** — Allows pre-release expenses (`recorded`, `approved`, `submitted`) to be cancelled, restoring the fund balance. Released and reimbursed expenses are final and cannot be voided.
2. **Budget Carryover** — A monthly scheduled command that rolls unused budget amounts from the prior period into the current month, without overwriting manually pre-set budgets.

---

## 1. Void Expense

### Status Flow

```
recorded  ──(void)──► voided
approved  ──(void)──► voided
submitted ──(void)──► voided

released   — NOT voidable (final — in the books)
reimbursed — NOT voidable (final — replenishment settled)
```

### Routes

Inside the existing `role:Administrator,Top Management,Area Business Manager,Super Admin` middleware group:

```
PATCH /expenses/{id}/void    ExpenseController@void
```

### Backend Changes

**`ExpenseRequest`**
- Add `voided` to the `status` field's `in:` rule.

**`ExpenseClass::void($id)`**
- Finds expense by ID.
- Guard: if `status` is not in `['recorded', 'approved', 'submitted']`, returns soft error: `{ status: 'error', message: 'Only recorded, approved, or submitted expenses can be voided.' }`
- Restores fund balance: `PettyCashFund::where('id', $expense->fund_id)->increment('balance', $expense->amount)` (only if `fund_id` is set).
- Updates status to `voided`.
- Returns `ExpenseResource` with `status: 'success'`.

**`ExpenseController::void($id)`**
- Calls `$this->expense->void($id)` via `handleTransaction`.
- Returns JSON: `{ message, status, data }` — same shape as `approve`.

### Frontend Changes

**`resources/js/Pages/Modules/Expenses/Index.vue`**

Add a Void button to both the table row actions and the expanded row detail card:

- Visible when: `['recorded', 'approved', 'submitted'].includes(list.status)` AND current user role is Administrator or Top Management.
- `variant="danger"`, icon `ri-close-circle-line`, tooltip "Void".
- On click: SweetAlert confirm dialog ("Are you sure you want to void this expense? This cannot be undone.") → `axios.patch('/expenses/${list.id}/void')` → on success, update the row's status in the local list to `'voided'` (no full reload).
- On soft error: show the error message in a SweetAlert warning.

Add a `canVoid` computed property:
```js
canVoid() {
    const roles = this.$page?.props?.roles || [];
    return ['Administrator', 'Top Management'].some(r => roles.includes(r));
},
```

---

## 2. Budget Carryover

### Logic

On the 1st of each month, for each of the 6 expense types (`operational`, `utilities`, `supplies`, `transportation`, `maintenance`, `others`):

1. Read the prior month's `ExpenseBudget` record (`amount`). If none exists, skip this type.
2. Compute prior month's actual spend: `SUM(amount)` of `released` expenses for that type in the prior period.
3. Compute remainder: `budget.amount − actual_spend`. If remainder ≤ 0, skip.
4. Check if a budget already exists for the **current** month and type. If it does, skip (do not overwrite manual entries).
5. Create an `ExpenseBudget` record for the current month with `amount = remainder`.

### Artisan Command

**`app/Console/Commands/CarryBudgetCommand.php`**

- Signature: `expense:carry-budget {--month=} {--year=}`
- Defaults: `--month` and `--year` default to the current month/year if not provided.
- The command computes the prior period from the given month/year (handles January → December of prior year correctly).
- Runs inside `DB::transaction`.
- Outputs one line per expense type: `Carried ₱X from operational (YYYY-MM) → (YYYY-MM)` or `Skipped operational — budget already set` or `Skipped operational — no prior budget`.

**Scheduler Registration**

In `routes/console.php` (Laravel 11 style):

```php
Schedule::command('expense:carry-budget')->monthlyOn(1, '00:05');
```

### Testing

**`tests/Feature/Expenses/BudgetCarryoverTest.php`**

- `test_unused_budget_carries_over_to_next_month` — seed prior month budget + some released expenses, run command, assert current month budget = remainder
- `test_no_carryover_when_budget_fully_used` — seed budget with spend ≥ budget, run command, assert no current month record created
- `test_carryover_skips_existing_current_month_budget` — pre-create current month budget, run command, assert it was not changed
- `test_carryover_skips_type_with_no_prior_budget` — no prior budget record for a type, assert no current month record created for that type
- `test_command_accepts_month_year_flags` — run command with explicit `--month=1 --year=2026`, assert it targets December 2025 as the prior period

---

## Error Handling

| Scenario | Handling |
|----------|---------|
| Void on released expense | Soft error returned (`status: 'error'`), displayed as SweetAlert warning |
| Void on reimbursed expense | Soft error returned, same handling |
| Expense has no fund | Void succeeds; no balance restoration attempted |
| Carryover command run twice in same month | Second run skips all types (budget already exists) |
| Prior month budget fully consumed | No carryover record created for that type |

---

## Data Flow Summary

```
User (Admin/Top Mgmt)
  └─► PATCH /expenses/{id}/void
        → ExpenseClass::void()
          → restore fund balance (if fund_id set)
          → status: voided

Scheduler (1st of month, 00:05)
  └─► expense:carry-budget
        → for each expense type:
            → read prior month budget
            → read prior month released spend
            → if remainder > 0 and no current budget: create ExpenseBudget
```

---

## Out of Scope (Group 3+)

- Export / print views for voided expenses
- Notifications for low balance or budget exhaustion
- Audit log for void actions beyond status change
- Carryover for fund balances (separate from budget carryover)
