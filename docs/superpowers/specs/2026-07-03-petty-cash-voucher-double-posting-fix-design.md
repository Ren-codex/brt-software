# Petty Cash Voucher Double-Posting Fix — Design Spec

**Date:** 2026-07-03
**Status:** Approved
**Scope:** Stop double-recognizing petty cash voucher expenses in the general ledger

---

## Overview

The live petty cash voucher workflow (`/accounting/petty-cash`, `PettyCashController` + `ReplenishmentService`) posts two separate journal entries for the same expense:

1. **Voucher creation** — `PettyCashController::storeVoucher()` immediately calls `JournalEntryService::recordPettyCashVoucherEntry()`, posting **Dr Expense / Cr Petty Cash**.
2. **Replenishment approval** — once the voucher is swept into a `ReplenishmentRequest` and approved, `ReplenishmentService::approve()` calls `JournalEntryService::recordReplenishmentEntry()`, posting **Dr Expense (grouped by category) / Cr Cash-in-Bank** for the batch total.

Every voucher's expense is recognized twice in the GL. Expense accounts (Utilities, Supplies, Transportation, etc.) run roughly 2x actual spend, and there's no real cash movement that corresponds to crediting both Petty Cash and Cash-in-Bank for the same voucher.

Step 2 is already the textbook-correct imprest posting: it fires only at replenishment, grouped by category, computed from the actual unreimbursed vouchers (`ReplenishmentService::createDraft()`). The bug is solely step 1 — the voucher should not touch the GL at creation time.

(Separately confirmed during investigation: the older `CashManagementService`/`PettyCashTransaction` disbursement path is dead code — no live Vue page posts to it anymore after the UI consolidation earlier today. It is out of scope for this fix.)

## Decision

- **`PettyCashController::storeVoucher()`** ([app/Http/Controllers/Modules/PettyCashController.php:127](../../../app/Http/Controllers/Modules/PettyCashController.php)) — remove the `$this->journal->recordPettyCashVoucherEntry(...)` call. The method still creates the `Expense` record and decrements `fund.balance` (operational box-balance tracking, not a GL posting) — it just stops posting a journal entry.
- **`JournalEntryService::recordPettyCashVoucherEntry()`** ([app/Services/Accounting/JournalEntryService.php:1036](../../../app/Services/Accounting/JournalEntryService.php)) — delete the method entirely. It has exactly one call site, which is being removed.
- **`PettyCashController::voidVoucher()`** — no code change. Its existing `reverseEntriesForSource()` call is a no-op for any voucher created after this fix (no entry exists to reverse) and still correctly reverses the entry for any pre-fix voucher that has one.
- **No migration, no backfill.** Vouchers already replenished before this fix keep their double-posted journal history as historical fact. Only vouchers recorded after this change are affected — this is a forward-only fix.

## Data flow after the change

```
Record a voucher
  → PettyCashController::storeVoucher()
  → creates Expense (status: recorded), decrements fund.balance
  → NO journal entry posted

Void an unsubmitted voucher
  → PettyCashController::voidVoucher()
  → reverseEntriesForSource() finds nothing to reverse (no-op for new vouchers)
  → restores fund.balance, deletes Expense record

Batch vouchers into a replenishment request → approve
  → ReplenishmentService::createDraft() / submit() / approve()
  → recordReplenishmentEntry(): Dr [Expense accounts by category] / Cr Cash-in-Bank
  → this is now the ONLY journal entry a voucher ever produces
```

## Error handling / edge cases

- Vouchers voided before ever being swept into a replenishment request: unaffected either way, since `reverseEntriesForSource()` already handles the "nothing to reverse" case gracefully (returns an empty array when no matching un-reversed entries exist).
- Vouchers that already have a pre-fix `recordPettyCashVoucherEntry` journal entry and are later included in a replenishment batch: unaffected by this change — their existing (buggy) double posting remains as historical fact, consistent with the forward-only decision. Only vouchers created after the fix behave correctly.
- `formatFund()`'s `unsubmitted_vouchers` figure (sum of `status = recorded` expenses) is unrelated to journal postings and is unaffected.

## Testing

- No test suite currently covers `recordPettyCashVoucherEntry` specifically; confirm by grep that removing it doesn't break other tests.
- Manual check 1: record a new petty cash voucher, confirm no journal entry is created for it (check the Journal Entries list / `journal_entries` table for a `petty_cash_voucher` entry type — there should be none).
- Manual check 2: void that voucher, confirm `fund.balance` is restored and no error occurs from the no-op reversal call.
- Manual check 3: record a new voucher, batch it into a replenishment request, approve the request, confirm exactly one journal entry is posted (`petty_cash_replenishment`/replenishment entry type) with the correct Dr Expense-by-category / Cr Cash-in-Bank lines, and that no `petty_cash_voucher`-type entry exists anywhere for that voucher.

## Out of scope

- The dead `CashManagementService`/`PettyCashTransaction` disbursement path.
- Any backfill or reversal of pre-fix double-posted journal entries.
- Changes to the replenishment request approval workflow itself.
