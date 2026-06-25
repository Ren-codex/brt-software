# Cash Flow Statement — Indirect Method Redesign

**Date:** 2026-06-21
**Scope:** Replace the current direct-method operating section of the Cash Flow Statement with the textbook indirect method. Investing and Financing sections are unchanged.

---

## Context

The Cash Flow Statement lives at `/accounting/cash-flow`. It is rendered by:
- **Controller:** `app/Http/Controllers/Modules/AccountingController.php` → `cashFlowStatement()` + `buildCashFlowData()`
- **Vue page:** `resources/js/Pages/Modules/Accounting/CashFlow.vue`

The current operating section queries `journal_entry_lines` grouped by `entry_type` (e.g., `receipt_collection`, `accounts_payable_payment`) — this is a direct method. The subtitle in the Vue page already labels it "Direct method."

The goal is to replace the operating section with the indirect method: start from Net Income, add back non-cash charges, then adjust for working capital changes.

---

## Approach: Account-Balance Delta Method

Compute working capital changes by diffing cumulative account balances at two points:
- **Opening balances** = all journal entries with `entry_date < date_from` (zero if no date range)
- **Closing balances** = all journal entries with `entry_date ≤ date_to`

Delta per account = closing balance − opening balance.

The existing `buildAccountBalances(?string $dateFrom, ?string $dateTo)` already computes period-scoped balances and will be called twice with different bounds to get opening and closing snapshots.

---

## Backend Design (`buildCashFlowData`)

### Step 1 — Dual balance queries

```php
$openingBalances = $dateFrom ? $this->buildAccountBalances(null, (Carbon::parse($dateFrom)->subDay()->format('Y-m-d'))) : collect();
$closingBalances = $this->buildAccountBalances(null, $dateTo);
```

Helper to get a single account's balance from a collection by slug or subtype:
```php
$balanceDelta = fn(Collection $closing, Collection $opening, string $slug) =>
    (float)($closing->firstWhere('slug', $slug)?->balance ?? 0)
    - (float)($opening->firstWhere('slug', $slug)?->balance ?? 0);
```

For subtype-based lookup (inventory, prepaid, accrued):
```php
$subtypeDelta = fn(Collection $closing, Collection $opening, string|array $subtypes) => ...
```

### Step 2 — Net Income for the period

```php
$plClosing = $this->buildProfitLossTotals($closingBalances);
$plOpening = $this->buildProfitLossTotals($openingBalances);
$netIncome  = round($plClosing['net_income_raw'] - $plOpening['net_income_raw'], 2);
```

### Step 3 — Non-cash adjustments

Find accounts with `subtype IN ['depreciation', 'amortization']` or `name LIKE '%depreciation%'` or `name LIKE '%amortization%'`. Period movement = sum of (closing debit total − opening debit total) across those accounts. Always added back (positive adjustment).

```php
$deprClosing = $closingBalances->filter(fn($a) =>
    in_array($a['subtype'], ['depreciation', 'amortization'])
    || str_contains(strtolower($a['name']), 'depreciation')
    || str_contains(strtolower($a['name']), 'amortization')
)->sum('balance');

$deprOpening = $openingBalances->filter(...same...)->sum('balance');
$depreciation = round($deprClosing - $deprOpening, 2);
```

### Step 4 — Working capital changes

| Row label | Account lookup | Cash impact rule |
|---|---|---|
| (Increase) / Decrease in Accounts Receivable | `slug = accounts_receivable` OR `subtype = accounts_receivable` | Δ positive → outflow; Δ negative → inflow |
| (Increase) / Decrease in Inventory | `subtype IN [inventory, merchandise_inventory]` | same as AR |
| (Increase) / Decrease in Prepaid Expenses | `subtype = prepaid` | same as AR |
| Increase / (Decrease) in Accounts Payable | `slug = accounts_payable` OR `subtype = accounts_payable` | Δ positive → inflow; Δ negative → outflow |
| Increase / (Decrease) in Accrued Liabilities | `subtype = accrued_liabilities` | same as AP |

**Asset accounts (AR, Inventory, Prepaid):** increase in asset = cash outflow (money tied up). Direction = Δ > 0 → `outflow`, Δ < 0 → `inflow`. Amount = abs(Δ).

**Liability accounts (AP, Accruals):** increase in liability = cash saved. Direction = Δ > 0 → `inflow`, Δ < 0 → `outflow`. Amount = abs(Δ).

Rows where Δ = 0 are excluded from the output (hidden). Net Income row always shown even if zero.

### Step 5 — Net Operating Cash

```
Net Operating = Net Income + Depreciation + Σ(working capital adjustments)
```

All sign conventions are resolved before summing: inflow rows contribute positive, outflow rows contribute negative.

### Operating rows array structure

Each row follows the existing shape used by the frontend:

```php
[
    'label'      => 'Net Income / (Net Loss)',
    'note'       => 'Profit or loss for the period',
    'amount_raw' => abs($netIncome),
    'amount'     => $this->formatCurrency(abs($netIncome)),
    'entries'    => null,
    'direction'  => $netIncome >= 0 ? 'inflow' : 'outflow',
    'details'    => [],   // empty = not clickable in drawer
]
```

The `details => []` check is already used by the Vue template to suppress the drawer arrow — no frontend change needed for this.

### Investing & Financing

Unchanged — still built by `buildManualCashRows($dateFrom, $dateTo)`.

---

## Frontend Design (`CashFlow.vue`)

**Only one change required:**

Line 78 subtitle text:
```
// Before
"Direct method — operating, investing, and financing cash movements for the selected period."

// After
"Indirect method — starts from net income and adjusts for non-cash items and working capital changes."
```

The `cf-row-clickable` class, drawer open logic, and all other UI components are already conditional on `row.details?.length > 0`, so computed indirect-method rows (empty details) are naturally non-interactive. No other Vue changes needed.

---

## Edge Cases

| Scenario | Behaviour |
|---|---|
| No date range selected | Opening balances = zero collection; Δ = full closing balance (change since inception). Valid and meaningful. |
| Account slug not found (e.g., no AR account) | Delta = 0; row is hidden. No error. |
| Net Income is negative (net loss) | Row shows as `outflow` with parenthetical amount, matching existing formatting convention. |
| Depreciation subtype not in chart of accounts | Delta = 0; row is hidden. |
| Mixed manual + operating journal entries | Manual entries still flow into Investing/Financing via `buildManualCashRows`. Operating section is now purely derived from account balance deltas. |

---

## Files Changed

| File | Change |
|---|---|
| `app/Http/Controllers/Modules/AccountingController.php` | Replace `buildCashFlowData()` operating section; add `buildWorkingCapitalDelta()` private helper |
| `resources/js/Pages/Modules/Accounting/CashFlow.vue` | Update subtitle string (line 78) |

No migrations, no new routes, no schema changes.

---

## What Does NOT Change

- Chart (waterfall bar — Operating / Investing / Financing / Net Change)
- Opening and closing cash balance banners
- Net Change box at the bottom
- Investing and Financing sections
- Export (PDF / Excel) — same `$data['sections']` shape passed through
- Summary cards (Net Operating / Investing / Financing / Net Change)
- Drawer panel (already conditionally triggered; indirect rows just won't open it)
