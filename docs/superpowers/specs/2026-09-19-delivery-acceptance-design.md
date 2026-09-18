# Delivery acceptance — design

Date: 2026-09-19
Status: awaiting review

## Why

The system records what was ordered and what was paid. It records nothing about
what actually arrived. Two gaps follow:

1. **Nothing says an order was delivered.** Shipping and delivery dates are
   plans typed at order entry, and a sales order's status only tracks money and
   returns. For a COD order, an unpaid balance means either "the driver has not
   left yet" or "the driver delivered and is holding your cash" — two situations
   needing different phone calls.
2. **A partial refusal at the door has no home.** A sales return needs a
   receipt (`validateSalesReturnEligibility`), and an uncollected COD order has
   none. The only way through today is to edit the order down to what the
   customer accepted, which loses the fact that goods came back from a
   customer's door — so refusals cannot be counted per customer or per product.

## Scope

Recording what the customer accepted, at the moment the office learns it.

Not in scope: the "cash in the field" view, per-hand-off custody of collected
money, driver logins, proof of delivery.

## The action

One action on a sales order, `mark-delivered`, added to the existing action
switch in `SalesOrderController::update()` and guarded by the same sales-orders
permission that covers editing.

It takes a delivery date and an accepted quantity per order item, prefilled with
the ordered quantity. Accepting everything is one click and behaves exactly like
a plain "delivered" stamp.

### Rules

- A cancelled order cannot be marked delivered.
- Marking twice keeps the first timestamp; quantities are not re-opened. A
  correction after that is an ordinary order edit.
- Accepted quantity per line is between zero and the ordered quantity.
- Accepting zero of everything is a refused delivery: the order is marked
  delivered with nothing accepted, all stock returns, and the invoice goes to
  zero. The order is not cancelled — the trip happened and is worth counting.
- Once a payment has been recorded against the invoice, acceptance is closed.
  Goods coming back after money changed hands is a return, and the existing
  return flow handles it, refund included.

## Data

Two nullable columns on `sales_orders`:

| Column | Type | Meaning |
|---|---|---|
| `delivered_at` | timestamp | when the goods reached the customer |
| `delivered_by_id` | user id | who recorded it |

One new table, `sales_order_delivery_refusals`, one row per line short-delivered:

| Column | Type |
|---|---|
| `sales_order_id`, `sales_order_item_id`, `product_id` | foreign keys |
| `ordered_quantity`, `accepted_quantity`, `refused_quantity` | integers |
| `batch_code` | string, so stock returns to the batch it left |
| `reason` | string, nullable |
| `recorded_by_id` | user id |
| `recorded_at` | timestamp |

A separate table from `sales_return_history` on purpose: a refusal happens
before any money moves and never refunds, while a return reverses a payment.
Mixing them would make both harder to read. A report wanting "why did stock come
back" can read both.

## Stock, invoice and ledger

Reuse the order update path rather than writing a second way to resize an order.
Marking delivered with short quantities:

1. writes the refusal rows,
2. sets each item's quantity to the accepted quantity, deleting a line accepted
   at zero,
3. restores the refused quantity to its own batch through `InventoryService`,
4. resizes the AR invoice to the accepted total,
5. reverses the original journal entries and re-posts them at the accepted
   amount, via `recordSalesOrderUpdateEntries()`.

Revenue, receivable, cost of goods sold and inventory therefore all end at the
accepted figures, and the COD collection matches what the driver holds.

## Collection stamps delivery

Recording a payment against a COD order stamps `delivered_at` if it is still
empty. Money cannot come back unless the goods went out, and it saves recording
one event twice.

## Screen

In the order's row actions: **Mark Delivered** while unmarked, otherwise
*Delivered 09/22/2026* with the recorder's name. The action opens a small modal
listing the items with accepted quantities prefilled and an optional reason per
short line.

## Tests

- Marking delivered stamps when and who.
- A cancelled order is refused; a second mark does not move the first timestamp.
- Full acceptance changes no stock, no invoice total and no ledger entry.
- A short quantity: stock returns to the right batch, the invoice drops to the
  accepted total, and the ledger's revenue and receivable match it.
- Accepting zero on every line empties the invoice and leaves the order
  delivered rather than cancelled.
- Acceptance is refused once a payment exists.
- Recording a COD collection stamps `delivered_at` when it is empty.
- A view-only user cannot mark delivered.

## Deployment

Two migrations, on top of the shipping and delivery dates migration that is
already waiting for production. All three are additive. They must be applied
immediately after the deploy that carries them, since deploys here do not run
migrations.
