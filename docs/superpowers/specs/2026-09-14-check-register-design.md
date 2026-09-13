# Check Register — Design

**Status:** approved, ready for implementation
**Scope:** checks in both directions — received from customers, issued to suppliers — plus a dated cash projection. Expense and loan payments made by check are out of scope for this pass.

## Problem

Checks are the instrument this business runs on, and the system barely models them.

The owner issues a ₱1,000,000 check to a supplier on 14 September against a bank balance of ₱0, because she expects money to land on 22 September. She dates the check 23 September so it will not bounce when the supplier presents it. The check date is a **funding deadline**, not paperwork — and nothing in the system tells her how much she has committed against any future date.

On the other side, a sales rep takes a check from a customer and stays accountable for it until it is known to be good. Today there is no way for the rep to see whether their check has reached the owner's account.

Both directions share one defect: **the ledger moves money that has not moved.**

- `JournalEntryService::recordReceivedStockPaymentEntry()` posts DR Accounts Payable / CR Bank the instant a supplier payment is recorded. The ₱1M check above drives that bank account to **−₱1,000,000** for nine days.
- `deposits:post-due-checks` (shipped 2026-09-12) posts DR Bank / CR Cash automatically when a deposited check's date arrives, *assuming it clears*. A bounced check still credits the bank, and nothing ever takes it back out.

No `bounced` concept exists anywhere in the codebase.

## What already exists

Roughly half the received-check machinery is built and unused:

| Exists | State |
|---|---|
| `CheckMonitoringClass` — list with filters, `updateCheckDate()`, `markReleased()` | No controller, route, or UI |
| `CheckMonitoringResource` — already exposes `sales_rep`, `confirmed_at`, `confirmed_by` | Unused |
| `receipts.check_date`, `check_status` (on_hand → released → matured), `released_at`, `bank_name`, `confirmed_at`, `confirmed_by_id` | Live |
| `ArInvoiceClass::confirmCheck()` — gates AR balance release | Live, wired to `ReceiptController@confirmCheck` |
| `checks:mark-matured`, `checks:remind-pending` | Scheduled daily |
| `bank_deposits.deposit_type/check_date/check_number/status` | Live since 2026-09-12 |

Issued checks have nothing: `received_stock_payments` carries `payment_mode`, `payment_date` and `reference_number`, with the check number crammed into `reference_number` and appended to the journal memo. No check date, no status, no lifecycle.

Payments are already one-to-many (see `2026-08-21-split-payments-design.md`), so a single supplier payment may contain several lines and only one of them be a check. The register therefore binds to a **payment line**, not to a payment.

## Goals

1. One register of every check, both directions, with a real status.
2. Money posts only when a check actually clears, confirmed by a person.
3. A rep can see whether their received check has cleared, without an Accounting grant.
4. A bounced check reverts nothing silently: AR stays outstanding and the rep is told.
5. The owner sees, by date, what is committed against what is expected.

## Non-goals

- Expense and loan payments by check.
- Automatic bank-statement matching (Bank Reconciliation already owns that surface).
- Dropping the existing `receipts.check_*` columns. They stay; the register becomes authoritative going forward.

## Data model

One table, `checks`, authoritative for both directions.

```
id
direction          enum('received','issued')
check_number       string(50)
check_date         date            -- when it may be cashed; the forecast axis
amount             decimal(15,2)
bank_name          string  null    -- received: drawee bank
bank_account_id    FK bank_accounts null   -- issued: the account it draws on
status             enum('pending','cleared','bounced') default 'pending'
source_type        string          -- Receipt | ReceivedStockPayment
source_id          unsigned int
customer_id        FK customers      null   -- received
supplier_id        FK list_suppliers null   -- issued
received_by_id     FK employees      null   -- the rep accountable
cleared_at / cleared_by_id
bounced_at / bounced_by_id / bounce_reason
notes
timestamps
```

Indexes on `(status, check_date)` for the forecast, `(direction, status)` for the lists, and `(source_type, source_id)`.

Exactly one of `customer_id` / `supplier_id` is set, determined by `direction`. `source_type`/`source_id` point at the payment line that created the check, which is how the register reaches the AR invoice or the received stock.

## Received-check lifecycle

```
rep records check receipt
        │
        ▼
    PENDING ──────── admin deposits it around the check date
        │                    (existing BankDeposit check flow)
        │
        ├── admin confirms cleared ──▶ CLEARED
        │        DR Bank / CR Cash, dated the clearing
        │        payment applied to the AR invoice
        │        rep discharged
        │
        └── admin marks bounced ────▶ BOUNCED
                 nothing posts; AR stays outstanding
                 receiving rep notified to chase the customer
```

A bounced check is re-presentable: setting a new `check_date` returns it to `pending`. A replacement check from the customer is a new row, linked to the same receipt.

Nothing posts on any path except an actual clearing.

## Issued-check lifecycle

```
owner records supplier payment, mode = Check
        │
        ▼
    PENDING ── no journal entry at all
        │      bank untouched; supplier still shows owed
        │
        └── supplier cashes it; admin marks cleared ──▶ CLEARED
                 DR Accounts Payable / CR Bank, dated the clearing
```

This is the behavioural change to `recordReceivedStockPaymentEntry()`: for a check line, create the register row and post nothing. Every other payment mode is untouched.

## Forecast

A running projection over the register, by date:

```
projected(account, D) = that account's current balance
                      + Σ pending received checks to be deposited there, check_date ≤ D
                      − Σ pending issued checks drawn on it,             check_date ≤ D
```

**Projected per bank account, not in aggregate.** A check bounces against the account it is drawn on, so a healthy total across three accounts tells the owner nothing about whether the BDO check clears. Issued checks carry `bank_account_id` directly. Received checks are attributed to the account of the bank deposit that carries them, and are excluded from the projection until a deposit names one.

Rendered as one row per date on which any check falls due, with the projected balance after that date's movements. Any date where the projection is negative is flagged — that is the "your 23 September check will bounce" warning, days in advance.

Deliberately naive: it counts pending checks only, ignoring invoices not yet paid. A projection that over-promises inflows is worse than one that under-promises.

## Placement and permissions

Two surfaces over one register.

**Accounting → Check Register** (new submodule `check_register`)
Both directions, the forecast, and every action.
- `view` — see the register and forecast
- `approver` — confirm cleared, mark bounced, re-present

**Sales → Check Monitoring** (new submodule `check_monitoring`)
Read-only. A rep sees only checks where `received_by_id` is their own employee id, unless they hold sales `admin`, mirroring the scoping in `RemittanceClass::lists()`. Reps need no Accounting grant, which matters because that module also exposes Journal Entries, Cash Management and Chart of Accounts.

A rep must never be able to clear their own check. That separation is the whole point of the rep being "discharged" by someone else.

## Changes to existing code

| File | Change |
|---|---|
| `JournalEntryService::recordReceivedStockPaymentEntry()` | Check lines create a register row and post nothing |
| `ArInvoiceClass::confirmCheck()` | Drives the register; still gates AR balance release |
| `CashManagementService::postBankDeposit()` | Posting triggered by confirmation, not by date |
| `PostDueCheckDeposits` command | Stops posting entirely. Becomes a daily reminder notifying `check_register.approver` holders of pending checks whose date has arrived. It writes no ledger entries and changes no status — a person does that. |
| `CheckMonitoringClass::lists()` | Reads the register; gains rep scoping |
| `ReceiptController@confirmCheck` | Guard moves to `check_register.approver` |

The `confirmCheck` guard change is a live permission change: whoever confirms checks today via `sales.receipts.encoder` needs the new grant or loses the ability.

## Relationship to bank deposits

A received check is deposited through the existing `BankDeposit` check flow, which has its own `pending`/`posted` status. Those two states must not drift apart, so one drives the other: **a check-type bank deposit stays `pending` until its underlying check is confirmed cleared, and confirming the check is what posts the deposit.** The deposit records the act of depositing; the register records the instrument and its fate. Marking the check bounced leaves the deposit pending, where it can be cancelled or re-presented with a new date.

## Backfill

One migration, additive:

1. Create `checks`.
2. Insert a `received` row for every `receipts` record with `payment_mode = 'Check'` — `confirmed_at` set → `cleared` with that timestamp, otherwise `pending`; `received_by_id` resolved through `arInvoice.sales_order.sales_rep_id`.
3. Insert an `issued` row for every `received_stock_payments` record with `payment_mode = 'Check'`, status `cleared` — they have already posted under the old behaviour, and rewriting posted history is out of scope. Historic rows have no check date, because the column never existed: `check_date` falls back to `payment_date`, and `check_number` to `reference_number`, which is where check numbers have been kept. Both are best-effort for history; neither affects the forecast, since only `pending` rows are projected.
4. Insert the two submodule rows and default grants.

No column is dropped and no existing row is rewritten.

## Validation

- `check_number` required. For issued checks it must be unique per `bank_account_id` — one account cannot issue the same number twice. Enforced in validation rather than by a unique index, since received checks have a null `bank_account_id` and MySQL treats nulls as distinct, which would make the index silently useless on exactly the rows that need it least.
- `check_date` required.
- `amount` must equal the source payment line's amount.
- Confirm and bounce are rejected unless status is `pending`.
- Bounce requires a reason.
- Re-presenting requires a `check_date` strictly later than the current one.

## Testing

- A received check posts nothing until confirmed; confirming posts DR Bank / CR Cash and reduces AR balance.
- A bounced check posts nothing, leaves AR balance untouched, and notifies the receiving rep.
- An issued check posts nothing on recording; clearing posts DR AP / CR Bank dated the clearing.
- A supplier payment by cash or bank transfer still posts immediately — the change is check-only.
- A rep sees only their own received checks; a sales admin sees all; a rep cannot confirm.
- Forecast arithmetic: a date whose issued checks exceed balance plus expected inflows is flagged negative.
- Backfill: a confirmed receipt becomes a cleared check; an unconfirmed one becomes pending; counts match.

## Rollout and risks

The riskiest surfaces are `ArInvoiceClass::confirmCheck()`, which gates AR balance release, and supplier payment posting. Both are live and both carry money.

Build and verify locally in full. Hold the production migration and deploy until the owner has seen it working. The migration is additive — one `CREATE TABLE` plus row inserts — but per `feedback_never_migrate_production`, it is applied deliberately, after a verified `mysqldump`, with `migrate --pretend --force` read first.

Two corrections ship inside this work rather than separately: supplier check payments no longer drive the bank negative, and deposited checks no longer post on a date without a person confirming the money arrived.
