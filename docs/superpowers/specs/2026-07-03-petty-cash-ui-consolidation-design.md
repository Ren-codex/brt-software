# Petty Cash UI Consolidation — Design Spec

**Date:** 2026-07-03
**Status:** Approved
**Scope:** Remove duplicate fund-administration UI between the Cash Management page, the Petty Cash vouchers page, and the Petty Cash Funds page

---

## Overview

The sidebar's "Cash & Banking" section has three entries that all touch petty cash data:

1. **Cash Management** (`/accounting/cash-management`) — has an internal "Petty Cash" tab with its own Create Fund / Replenish Fund / Edit Fund modals and a transaction ledger.
2. **Petty Cash Funds** (`/accounting/funds`) — a dedicated fund administration page (create, top-up, adjust balance, toggle active), originally specced in `2026-05-29-group1-fund-management-expense-approval-design.md`.
3. **Petty Cash** (`/accounting/petty-cash`) — vouchers + replenishment request workflow.

Both (1) and (2) let a user create a fund and top it up — the same action, two different forms, in two different places. This is confusing ("which one do I use?") and was the reason two parallel, slightly-divergent backend implementations existed until they were consolidated onto `CashManagementService` earlier this session.

(3)'s voucher entry and replenishment-request workflow is genuinely different (routine expense recording + approval, not fund setup/correction) and is unaffected by this change. However, while auditing endpoint usage it turned out (3) *also* has its own full "Top-up Fund" form (`PettyCash.vue`'s "Fund" sub-tab, `openTopUp()`/`topUpForm`/`doTopUp()`, posting to `PettyCashController::topUpFund`) — a third duplicate of the same top-up action. This is in scope too, addressed below alongside the Cash Management changes.

## Decision

- **Petty Cash Funds** page remains the single place for fund administration. No changes.
- **Cash Management → Petty Cash tab** loses its fund-admin actions but keeps everything that's a *view* of fund data:
  - Fund selector dropdown — unchanged.
  - Balance card (name, GL code, current balance) — unchanged.
  - Transaction ledger table (txn #, date, type, category, amount, reference, receipt link, delete/reverse action) — unchanged. This stays because it's a ledger *view*, not a fund-admin action, and nothing else in the system currently surfaces this ledger.
  - **Removed:** "New Fund" button, "Replenish Fund" button, the Create Fund modal, the Edit Fund modal, and their associated form state (`pcFundModal`, `pcFundForm`, `pcFundErrors`, `pcFundSaving`, `fundEditModal`, `fundEditForm`, `fundEditSaving`, `openCreateFund`, `submitFund`, `openEditFund`, `submitEditFund`) and the rename-pencil button next to GL code (that button's only purpose was opening the now-removed Edit Fund modal).
  - **Added:** a "Manage Funds" link/button in the Petty Cash tab header that navigates to `/accounting/funds`.
- **`CashManagementController::storeFund()` / `updateFund()`** and their routes (`POST /accounting/petty-cash/funds`, `PUT /accounting/petty-cash/funds/{id}`) are removed — dead code once the frontend no longer calls them. `CashManagementService::createFund()` stays (still used by `FundClass::save()`).
- **Petty Cash vouchers page ("Fund" sub-tab)** — keeps the fund cards (name, GL code, custodian, fixed amount, low-balance badge, imprest reconciliation display) as a read-only view. **Removed:** the "Top Up" button per fund card, the top-up modal, and its state/methods (`topUpModal`, `topUpForm`, `openTopUp`, `doTopUp`). **Added:** a "Manage Funds" link pointing to `/accounting/funds` (same treatment as Cash Management). Vouchers and Replenishments sub-tabs are untouched.
- **`PettyCashController::topUpFund()`** and its route (`POST /accounting/petty-cash/funds/{id}/top-up`) are removed — dead code once `PettyCash.vue` no longer calls it. This was already fixed earlier this session to delegate to `CashManagementService::addTransaction()`, but with no caller left, the method and route are removed rather than left dead.

## Data flow after the change

```
Create/top-up/adjust a fund
  → user goes to /accounting/funds (only entry point)
  → FundController → FundClass → CashManagementService (unchanged backend)

View a fund's transaction history
  → user goes to /accounting/cash-management → Petty Cash tab
  → reads the existing ledger (read-only, no admin actions)
  → "Manage Funds" link → /accounting/funds if they need to act

View fund reconciliation / record a voucher / manage replenishments
  → user goes to /accounting/petty-cash
  → "Fund" sub-tab is read-only (no Top Up button)
  → "Manage Funds" link → /accounting/funds if they need to top up
  → "Vouchers"/"Replenishments" sub-tabs unchanged
```

## Error handling / edge cases

- Removing `storeFund`/`updateFund` routes: confirmed no other frontend file calls `/accounting/petty-cash/funds` (POST) or `/accounting/petty-cash/funds/{id}` (PUT) besides `CashManagement.vue`.
- Removing `topUpFund`/its route: confirmed no other frontend file calls `/accounting/petty-cash/funds/{id}/top-up` besides `PettyCash.vue`.
- The transaction ledger's delete/reverse action (`destroyPettyCashTransaction`) and the "Replenish Fund" removal don't interact — deleting a transaction is unaffected by removing the button that *creates* new replenishment transactions.

## Testing

- No backend logic changes beyond removing three controller methods/routes — existing `FundManagementTest`, `LowBalanceFundClassTest`, `LowBalanceCashManagementTest` suites should be unaffected; re-run them to confirm.
- Manual check 1: load Cash Management → Petty Cash tab, confirm ledger still renders, "New Fund"/"Replenish Fund" buttons are gone, "Manage Funds" link goes to `/accounting/funds`.
- Manual check 2: load Petty Cash (`/accounting/petty-cash`) → Fund sub-tab, confirm fund cards/reconciliation still render, "Top Up" button is gone, "Manage Funds" link goes to `/accounting/funds`, and Vouchers/Replenishments sub-tabs still work.
- Manual check 3: confirm `/accounting/funds` still supports full fund creation/top-up/adjustment as before (unchanged, but worth reconfirming after route removal).

## Out of scope

- The vouchers/replenishment sub-tabs themselves (only the Fund sub-tab's top-up action is removed).
- Any further backend consolidation.
- Visual redesign of the ledger table itself.
