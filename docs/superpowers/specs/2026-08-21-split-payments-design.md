# Split Payments — Design

**Status:** approved, ready for implementation
**Scope of this pass:** supplier payments (received stock). Customer receipts follow the same pattern in a later pass.

## Problem

A payment can currently use exactly one method. Paying a supplier ₱5,000 in cash and ₱10,000 by bank transfer means two separate trips through a three-step modal, and the same limitation applies when a customer settles an invoice.

## Key finding: the data model already supports this

`received_stock_payments` is one-to-many against `received_stocks`, and every row already carries its own `payment_mode`, `bank_account_id`, `bank_name` and `reference_number`. `ReceivedStockService::applyPayment()` **appends** a row and accumulates `amount_paid`, so partial payments already work.

`JournalEntryService::recordReceivedStockPaymentEntry()` takes **one payment row** and posts debit Accounts Payable / credit the account resolved from that row's own mode and bank account.

So a split is N payment rows rather than one. No migration, no re-modelling, and existing records stay valid. The change is the entry point: one request carrying a list of lines.

`receipts` is one-to-many against `ar_invoices` in the same shape, which is why the sales pass will reuse this design.

## Architecture

```
POST /received-stocks/{id}/pay
  { lines: [ { payment_mode, payment_amount, bank_account_id?, bank_name?, reference_number? }, … ] }
      │
      ▼
  applyPayment()  ──for each line──▶  payments()->create(line)
                                      recordReceivedStockPaymentEntry(receivedStock, line)
      │
      ▼
  accumulate amount_paid once; mark settled when the total is reached
```

Each line produces its own ledger entry against its own funding source, so cash, each bank account and check clearing each move by exactly their own amount. Bank reconciliation and cash-on-hand reporting continue to work with no changes.

The whole loop runs inside the existing `DB::transaction` in `applyPayment()`, so a single bad line rejects the entire payment rather than half-recording it.

## Validation

Per line, reusing the current rules:

| Method | Requires | Balance checked against |
|---|---|---|
| Cash on Hand | — | cash on hand |
| Bank Transfer | `bank_name`, `reference_number` | that bank account |
| Check | `reference_number` | — |

Two rules are new:

1. **Aggregate cap.** The sum of the lines must not exceed the remaining payable. Today a single amount is checked; with lines it is the total.

2. **Same source used more than once.** Lines drawing on the same funding source must be checked against that source's balance **combined**. Two ₱20,000 cash lines each pass an individual check against a ₱30,000 balance while together overdrawing it. This is the one case where a naive implementation silently corrupts balances, so it carries an explicit test.

Lines with an amount of zero are rejected rather than silently dropped, so a stray empty row cannot be mistaken for a recorded payment.

## Backwards compatibility

The endpoint accepts the existing single-payment body as well as the new `lines` array. A request without `lines` is treated as a one-line split. This keeps any existing caller working and makes the change reviewable in isolation.

## UI

The current three-step flow (Cash or Credit → Cash / Bank Transfer / Check → amount) is replaced, for the immediate-payment path, by **one payment screen**:

- Opens with a single empty line, so paying by one method is no slower than today.
- Each line: funding source dropdown (Cash on Hand, each active bank account, Check), amount, and a reference number field shown **only** when the source needs one.
- Each line shows the available balance for its selected source.
- "Add payment line" adds another; a line can be removed.
- A running footer shows *paid now* and *remaining payable*.
- Confirm is disabled while any line is invalid, with the reason shown inline.

The Credit path is untouched.

The line-entry component is written to be reused by the customer-receipt flow in the following pass, rather than duplicated.

## Testing

Feature tests:

- a three-way split records three payment rows and three journal entries, crediting the correct account for each
- the lines' sum cannot exceed the remaining payable
- **two lines on the same source are checked against the combined balance**
- Bank Transfer without a reference number is rejected; Check without one is rejected; Cash needs neither
- a zero-amount line is rejected
- one invalid line rolls back the whole payment, leaving no rows and no entries
- a legacy single-payment request body still works

Then a live browser check recording a genuine two-method payment and confirming both rows and both ledger entries.

## Files

- `app/Http/Requests/ApplyReceivedStockPaymentRequest.php` — lines validation, aggregate and per-source caps
- `app/Services/ReceivedStockService.php` — `applyPayment()` loops over lines
- `resources/js/Pages/Modules/Inventory/Modal/CreateReceivedStockModal.vue` — use the new screen
- `resources/js/Shared/Components/PaymentLines.vue` — new, shared with the later sales pass
- `tests/Feature/Inventory/SplitPaymentTest.php` — new

No migration.

## Out of scope

- Customer receipts (next pass, same pattern)
- Editing or reversing an individual line of an already-recorded split; the existing void path still reverses the whole received stock
