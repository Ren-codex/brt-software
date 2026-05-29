# Connect All to Notification — Design Spec
Date: 2026-05-30

## Overview

Extend the existing notification bell to cover three alert types: fund low balance (wired to all balance-decreasing paths), low inventory stock, and overdue AR invoices. All new wiring goes through `NotificationService` dispatcher methods (Option B), following the existing service-class architecture.

---

## 1. New Notification Classes

Location: `app/Notifications/`

### `LowStockNotification`
- Channels: `database`, `broadcast`
- `broadcastType()` returns `'low_stock'`
- `toDatabase()` payload:
  ```php
  [
      'type'          => 'low_stock',
      'product_id'    => $this->product->id,
      'product_name'  => $this->product->brand->name . ' ' . $this->product->pack_size . ' ' . $this->product->unit->name,
      'current_stock' => $this->currentStock,
      'minimum_stock' => $this->product->minimum_stock,
  ]
  ```
- Constructor: `(Product $product, int $currentStock)`

### `OverdueInvoiceNotification`
- Channels: `database`, `broadcast`
- `broadcastType()` returns `'overdue_invoice'`
- `toDatabase()` payload:
  ```php
  [
      'type'           => 'overdue_invoice',
      'invoice_id'     => $this->invoice->id,
      'invoice_number' => $this->invoice->invoice_number,
      'customer_name'  => $this->invoice->sales_order->customer->name,
      'balance_due'    => (float) $this->invoice->balance_due,
      'due_date'       => $this->invoice->due_date->toDateString(),
  ]
  ```
- Constructor: `(ArInvoice $invoice)`

`LowBalanceFundNotification` is unchanged.

---

## 2. NotificationService — Dispatcher Methods

Location: `app/Services/NotificationService.php`

Add three public methods. All notify users with `Administrator` or `Top Management` roles.

### `checkAndNotifyLowBalance(PettyCashFund $fund, float $previousBalance, float $newBalance): void`
Guard: `$fund->low_balance_threshold !== null && $previousBalance >= threshold && $newBalance < threshold`  
Uses `DB::afterCommit()` to fire after the enclosing transaction commits.  
Replaces the inline dispatch block currently in `ExpenseClass::save()`.

### `checkAndNotifyLowStock(int $productId, int $previousTotal): void`
Caller passes `$previousTotal` (total stock before deduction). Method queries the new total inside `DB::afterCommit()` and applies a crossing guard: `$previousTotal >= minimum_stock && $newTotal < minimum_stock`. This prevents repeated notifications on every sale when stock is already below minimum.  
Uses `DB::afterCommit()`.

### `notifyOverdueInvoices(Collection $invoices): void`
Iterates each invoice, notifies `Administrator` + `Top Management` users with `OverdueInvoiceNotification`.  
Called synchronously (already outside a transaction in the scheduled command).

---

## 3. Wiring — Fund Low Balance

Inject `NotificationService` into each affected service class. Call `checkAndNotifyLowBalance()` after every fund decrement.

| Service | Method | Where to add call |
|---|---|---|
| `ExpenseClass` | `save()` | Replace existing inline dispatch block |
| `ExpenseClass` | `update()` | After balance delta applied, only when `$delta > 0` (amount increased = balance decremented) |
| `CashManagementService` | `addPettyCashTransaction()` | After `$fund->decrement('balance', $amount)` for disbursement type |
| `CashManagementService` | `deleteTransaction()` | After `$txn->fund->decrement('balance', $txn->amount)` for replenishment reversal |
| `FundClass` | `adjustBalance()` | Before `$fund->update(...)`, capture `$previousBalance = (float) $fund->balance`; call after update with new value |

---

## 4. Wiring — Low Stock

Inject `NotificationService` into `InventoryService` and `StockReturnClass`. Call `checkAndNotifyLowStock($productId)` after each deduction path.

| Service | Method | Where to add call |
|---|---|---|
| `InventoryService` | `deductStock()` | After the foreach loop completes (once per product) |
| `InventoryService` | `recordLossOrDamage()` | After the foreach loop completes (once per product) |
| `StockReturnClass` | (direct decrement) | After `$inventoryStock->decrement('quantity', $deductQty)` — pass `$poItem->product_id` (already loaded via `PurchaseOrderItem::with('product')`) |

---

## 5. Wiring — Overdue Invoices

Extend `app/Console/Commands/MarkOverdueInvoices.php`:

1. Inject `NotificationService` via constructor
2. Before the bulk `update()`, collect affected invoice IDs
3. After `update()`, reload those invoices with `sales_order.customer` eager-loaded
4. Call `$this->notificationService->notifyOverdueInvoices($invoices)`

The command already runs daily at `00:05` via `routes/console.php` — no scheduler change needed.

---

## 6. Frontend — Navigation.vue

### Echo handler
Extend the `.notification()` type guard from `=== 'low_balance'` to accept all three types:
```js
if (['low_balance', 'low_stock', 'overdue_invoice'].includes(notification.type)) {
```

### Notification list template
Replace the current hardcoded `low_balance` fields with a type-aware display using `v-if`/`v-else-if`:

- **`low_balance`**: `"Low fund: {fund_name} — ₱{balance} (threshold: ₱{threshold})"`
- **`low_stock`**: `"Low stock: {product_name} — {current_stock} remaining (min: {minimum_stock})"`
- **`overdue_invoice`**: `"Overdue: {invoice_number} · {customer_name} · ₱{balance_due}"`

---

## 7. Who Gets Notified

All three alert types notify users with the `Administrator` or `Top Management` role — consistent with the existing `LowBalanceFundNotification` pattern.

---

## Out of Scope

- No new notification types beyond the three above
- No per-user notification preferences
- No email/SMS channels — database + broadcast only
- No changes to the scheduler schedule
