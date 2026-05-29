# Group 3: Expense List Print & Low-Balance Notifications — Design Spec

**Date:** 2026-05-29
**Status:** Approved
**Scope:** Two features that complete the petty cash workflow surface: printable expense reports and real-time low-balance alerts for fund administrators.

---

## Overview

1. **Expense List Print** — A filterable PDF report of expenses (date range, status, fund, expense type) accessible from the Expenses page. Follows the existing `PrintClass` + Blade template pattern used by payroll, purchase orders, and receipts.
2. **Low-Balance Notifications** — When an expense causes a fund balance to cross below a configurable per-fund threshold, all Administrator and Top Management users receive a real-time in-app notification via Laravel Reverb (WebSocket), stored persistently in the database via Laravel's built-in Notification system.

---

## 1. Expense List Print

### Route

Inside the existing `auth` + `verified` + `is_active` + `2fa` middleware group:

```
GET /expenses/print    ExpenseController@printExpenses
```

Query parameters (all optional): `date_from`, `date_to`, `status`, `fund_id`, `expense_type`

### Backend Changes

**`PrintClass::printExpenseList(Request $request)`**
- Queries `Expense::with(['added_by', 'fund'])` with all provided filters:
  - `date_from` / `date_to` → `whereBetween('expense_date', ...)`
  - `status` → `where('status', $status)`
  - `fund_id` → `where('fund_id', $fund_id)`
  - `expense_type` → `where('expense_type', $expense_type)`
- Orders by `expense_date ASC`.
- Computes total amount from the result set.
- Renders `resources/views/prints/expense-list.blade.php` as landscape A4 PDF.
- Streams as: `expense-list-{timestamp}.pdf`.

**`ExpenseController@printExpenses`**
- Injects `PrintClass` via constructor (already available via `DropdownClass` pattern in other controllers — add to ExpenseController constructor).
- Delegates: `return $this->print->printExpenseList($request);`

### Blade Template

**`resources/views/prints/expense-list.blade.php`**

Standalone HTML/CSS (no Laravel layout), matching the style of `sales_order.blade.php` and `payroll.blade.php`:
- **Header block:** Company name/logo, report title "Expense List Report", generated date.
- **Filter summary bar:** Shows applied filters (e.g., "Period: 2026-05-01 to 2026-05-31 | Fund: Main Office | Status: released").
- **Table columns:** Date | Type | Fund | Description | Amount | Status | Recorded By
- **Footer row:** Grand total of `amount` column.
- Paper: A4 landscape.

### Frontend Changes

**`resources/js/Pages/Modules/Expenses/Index.vue`**

Add a "Print Report" button in the page header area (alongside existing controls).

- Clicking opens `PrintReportModal.vue` (a new modal component following the same pattern as Create/Edit modals in the module).
- Modal fields:
  - Date From / Date To (default: first and last day of current month)
  - Status (optional select: all statuses including `voided`)
  - Fund (optional select using the existing `funds` dropdown)
  - Expense Type (optional select: the 6 types)
- On confirm: `window.open('/expenses/print?' + params, '_blank')` — opens PDF in new tab.
- No axios call needed; it's a direct GET to the print route.

### Testing

**`tests/Feature/Expenses/ExpensePrintTest.php`**

- `test_print_returns_pdf_response` — GET /expenses/print returns 200 with `application/pdf` content-type
- `test_print_filtered_by_date_range` — only expenses within date range appear
- `test_print_filtered_by_status` — only expenses with matching status appear
- `test_print_filtered_by_fund` — only expenses for matching fund appear
- `test_print_filtered_by_expense_type` — only expenses of matching type appear
- `test_print_with_no_filters_returns_all_expenses` — baseline: all expenses included

---

## 2. Low-Balance Notifications

### Data Model Changes

**Migration: add `low_balance_threshold` to `petty_cash_funds`**
- Column: `low_balance_threshold decimal(10,2) nullable default null`
- When `null`: no alert configured for that fund.

**Migration: Laravel notifications table**
- Run `php artisan notifications:table` to generate the migration.
- Standard `notifications` table: `id (uuid)`, `type`, `notifiable_type`, `notifiable_id`, `data (json)`, `read_at (nullable)`, `created_at`, `updated_at`.

### Notification Payload

**`app/Notifications/LowBalanceFundNotification.php`**

```php
class LowBalanceFundNotification extends Notification implements ShouldBroadcast
{
    public function __construct(public PettyCashFund $fund) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'      => 'low_balance',
            'fund_id'   => $this->fund->id,
            'fund_name' => $this->fund->name,
            'balance'   => (float) $this->fund->balance,
            'threshold' => (float) $this->fund->low_balance_threshold,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

Broadcasts on each recipient's `App.Models.User.{id}` private channel — already authorized in `routes/channels.php`.

### Trigger Logic

In `ExpenseClass::save()`, immediately after `$fund->decrement('balance', $amount)`:

```php
$previousBalance = (float) $fund->balance; // captured before decrement
// ... decrement ...
$newBalance = $previousBalance - $amount;

if (
    $fund->low_balance_threshold !== null
    && $previousBalance >= (float) $fund->low_balance_threshold
    && $newBalance < (float) $fund->low_balance_threshold
) {
    User::whereHas('roles', fn($q) =>
        $q->whereIn('name', ['Administrator', 'Top Management'])
    )->each(fn($u) => $u->notify(new LowBalanceFundNotification($fund)));
}
```

**Crossing check:** Only fires when balance transitions from ≥ threshold to < threshold. Prevents spam when the fund is perpetually low. If the fund is replenished (balance goes back above threshold) and then drops below again, the notification fires once more — this is the intended behavior.

### Backend Routes + Controller

New `NotificationController` inside the auth middleware group:

```
GET   /notifications           NotificationController@index
PATCH /notifications/{id}/read NotificationController@markRead
PATCH /notifications/read-all  NotificationController@markAllRead
```

**`index()`**: Returns `{ notifications: [...], unread_count: N }` — current user's notifications, newest first, paginated at 10.

**`markRead($id)`**: Sets `read_at = now()` on the given notification (scoped to current user).

**`markAllRead()`**: Sets `read_at = now()` on all unread notifications for current user.

### Fund Management UI Changes

**`FundRequest`** — add `low_balance_threshold` as `nullable|numeric|min:0`.

**`FundClass::save()` and `update()`** — persist `low_balance_threshold` from request.

**`FundResource`** — include `low_balance_threshold` in the resource output.

**Funds Create/Edit modals** (`resources/js/Pages/Modules/Libraries/Funds/`) — add an optional "Low Balance Threshold (₱)" numeric input. Placeholder: "e.g. 500.00". Below the `weekly_budget` field.

### Frontend Notification Bell (Navigation.vue)

**Bell icon added to topbar** (between fullscreen button and user dropdown):
- `<i class="bx bxs-bell fs-22"></i>` with a `<span class="badge">{{ unreadCount }}</span>` overlay (hidden when count is 0).
- Clicking opens a Bootstrap dropdown showing the last 10 notifications.

**Each notification row:**
- Icon: `ri-alert-line text-warning`
- Text: "**{fund_name}** balance dropped to ₱{balance} (threshold: ₱{threshold})"
- Time: relative timestamp (e.g., "5 minutes ago")
- Unread rows have a light yellow background; read rows are normal.

**"Mark all as read" link** at the bottom of the dropdown.

**Data flow:**
1. On `mounted()`: `GET /notifications` → populates `notifications` array and `unreadCount`.
2. `Echo.private('App.Models.User.' + userId).notification(notification => { ... })` — listens for `BroadcastNotificationCreated` where `notification.type === 'low_balance'`. On event: increments `unreadCount`, prepends to `notifications`.
3. Mark read: on dropdown open OR explicit "mark all read" → `PATCH /notifications/read-all` → reset `unreadCount` to 0.

### Testing

**`tests/Feature/Expenses/LowBalanceNotificationTest.php`**

- `test_notification_fired_when_expense_causes_balance_to_cross_threshold` — balance was above threshold, expense brings it below → notification dispatched
- `test_notification_not_fired_when_balance_already_below_threshold` — balance starts below threshold, new expense makes it lower → no new notification
- `test_notification_not_fired_when_fund_has_no_threshold` — `low_balance_threshold` is null → never fires
- `test_notification_fires_again_after_fund_replenishment_crosses_threshold` — balance goes above threshold (simulated by direct update), then expense brings it below → fires again
- `test_notification_sent_to_administrator_and_top_management_only` — assert non-admin users receive no notification

---

## Error Handling

| Scenario | Handling |
|----------|---------|
| No `low_balance_threshold` set on fund | No notification — null check guards silently |
| Balance already below threshold when expense is saved | No re-notification — crossing check prevents spam |
| Notification dispatch fails (Reverb down) | DB channel is a separate write; WebSocket failure does not roll back the expense save |
| Print with no matching expenses | PDF renders with empty table and ₱0.00 total |
| Print route accessed without filters | Returns all expenses — no filter is applied |

---

## Data Flow Summary

```
User clicks "Print Report"
  └─► GET /expenses/print?date_from=&date_to=&...
        → ExpenseController::printExpenses()
          → PrintClass::printExpenseList()
            → PDF stream returned inline

User saves new expense
  └─► ExpenseClass::save()
        → fund balance decremented
        → threshold crossing check
          → (if crossed) LowBalanceFundNotification sent to admins
              → database: stored in notifications table
              → broadcast: pushed to App.Models.User.{id} channel via Reverb

Admin opens the app
  └─► Navigation.vue mounted()
        → GET /notifications → populates bell dropdown
        → Echo.private(...).notification(...) → real-time updates
```

---

## Out of Scope (Group 4+)

- Email notifications for low balance
- Budget exhaustion alerts
- Notification preferences (opt-in/opt-out per user)
- Export to Excel/CSV (only PDF is in scope here)
- Audit log for notification events
