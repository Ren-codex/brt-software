# Petty Cash UI Consolidation — Design Spec

**Date:** 2026-07-03
**Status:** Approved
**Scope:** Remove duplicate fund-administration UI between the Cash Management page and the Petty Cash Funds page

---

## Overview

The sidebar's "Cash & Banking" section has three entries that all touch petty cash data:

1. **Cash Management** (`/accounting/cash-management`) — has an internal "Petty Cash" tab with its own Create Fund / Replenish Fund / Edit Fund modals and a transaction ledger.
2. **Petty Cash Funds** (`/accounting/funds`) — a dedicated fund administration page (create, top-up, adjust balance, toggle active), originally specced in `2026-05-29-group1-fund-management-expense-approval-design.md`.
3. **Petty Cash** (`/accounting/petty-cash`) — vouchers + replenishment request workflow.

Both (1) and (2) let a user create a fund and top it up — the same action, two different forms, in two different places. This is confusing ("which one do I use?") and was the reason two parallel, slightly-divergent backend implementations existed until they were consolidated onto `CashManagementService` earlier this session.

(3) is a genuinely different workflow (routine voucher entry + approval, not fund setup/correction) and is unaffected by this change.

## Decision

- **Petty Cash Funds** page remains the single place for fund administration. No changes.
- **Cash Management → Petty Cash tab** loses its fund-admin actions but keeps everything that's a *view* of fund data:
  - Fund selector dropdown — unchanged.
  - Balance card (name, GL code, current balance) — unchanged.
  - Transaction ledger table (txn #, date, type, category, amount, reference, receipt link, delete/reverse action) — unchanged. This stays because it's a ledger *view*, not a fund-admin action, and nothing else in the system currently surfaces this ledger.
  - **Removed:** "New Fund" button, "Replenish Fund" button, the Create Fund modal, the Edit Fund modal, and their associated form state (`pcFundModal`, `pcFundForm`, `pcFundErrors`, `pcFundSaving`, `fundEditModal`, `fundEditForm`, `fundEditSaving`, `openCreateFund`, `submitFund`, `openEditFund`, `submitEditFund`) and the rename-pencil button next to GL code (that button's only purpose was opening the now-removed Edit Fund modal).
  - **Added:** a "Manage Funds" link/button in the Petty Cash tab header that navigates to `/accounting/funds`.
- **`CashManagementController::storeFund()` / `updateFund()`** and their routes (`POST /accounting/petty-cash/funds`, `PUT /accounting/petty-cash/funds/{id}`) are removed — dead code once the frontend no longer calls them. `CashManagementService::createFund()` stays (still used by `FundClass::save()`).
- **Petty Cash vouchers page** — untouched.

## Data flow after the change

```
Create/top-up/adjust a fund
  → user goes to /accounting/funds (only entry point)
  → FundController → FundClass → CashManagementService (unchanged backend)

View a fund's transaction history
  → user goes to /accounting/cash-management → Petty Cash tab
  → reads the existing ledger (read-only, no admin actions)
  → "Manage Funds" link → /accounting/funds if they need to act
```

## Error handling / edge cases

- Removing `storeFund`/`updateFund` routes: confirm no other frontend file calls `/accounting/petty-cash/funds` (POST) or `/accounting/petty-cash/funds/{id}` (PUT) before deleting — only `CashManagement.vue` does today.
- The transaction ledger's delete/reverse action (`destroyPettyCashTransaction`) and the "Replenish Fund" removal don't interact — deleting a transaction is unaffected by removing the button that *creates* new replenishment transactions.

## Testing

- No backend logic changes beyond removing two controller methods/routes — existing `FundManagementTest`, `LowBalanceFundClassTest`, `LowBalanceCashManagementTest` suites should be unaffected; re-run them to confirm.
- Manual check: load Cash Management → Petty Cash tab, confirm ledger still renders, "New Fund"/"Replenish Fund" buttons are gone, "Manage Funds" link goes to `/accounting/funds`, and that page still supports full fund creation/top-up/adjustment as before.

## Out of scope

- The vouchers/replenishment page.
- Any further backend consolidation.
- Visual redesign of the ledger table itself.
