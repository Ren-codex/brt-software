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

3. **Money collected in the field is only half-watched.** A cheque cannot
   reduce an invoice until someone confirms it with the bank, and its custody
   runs on-hand → released → matured. A bank transfer, by contrast, counts as
   paid the moment a reference number is typed, with nothing checking that it
   landed. And for cash or cheque alike, nothing records *which* driver or rep
   is holding it.

## Scope

Recording what the customer accepted, and watching the money collected at the
door until it reaches the office.

Not in scope: driver logins, proof of delivery such as signatures or photos,
per-hand-off approval of cash passed between staff.

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

## Field collection

A COD collection reaches the office by one of three routes, and each is watched
differently. What the customer pays is the accepted total, since acceptance has
already resized the invoice.

### Cash and cheque: who is holding it

One nullable column on `receipts`, `held_by_employee_id`: the person physically
holding the money or the cheque. Set when the collection is recorded — the
order's driver for a field collection, otherwise whoever took it. A "turned
over to" action moves it on; the remittance that carries the receipt clears it.

On a local run the collection and the turnover happen the same afternoon and
nobody needs the action. On an out-of-town run the cash travels for days, and
this is what answers "who has it right now".

Cheque custody keeps its existing on-hand → released → matured lifecycle. The
holder is the separate question of whose hands it is in.

### Bank transfer: confirmed, not claimed

`ArInvoiceClass::confirmCheck()` becomes `confirmReceipt()`, keeping cheque
behaviour and adding bank transfers **collected in the field**. Such a receipt
records its reference but does not reduce the invoice until someone matches it
in the bank account, exactly as a cheque does not. A transfer taken at the
counter is unchanged: the cashier is already looking at the confirmation.

So a driver phoning in a reference number no longer marks an invoice paid on
their word alone, and the order stays on the to-collect list until the money is
seen.

### Cash in the field

A view of pending receipts grouped by holder, oldest first, showing which are
SO-EXT. Local rows should disappear the same day, so anything lingering is worth
a phone call. This doubles as the to-collect list: delivered, not yet settled.

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
- A field collection in cash is held by the order's driver, and the remittance
  clears the holder.
- A field-collected bank transfer leaves the invoice unpaid until it is
  confirmed, and confirming releases it.
- A counter bank transfer still settles immediately.
- A cheque behaves exactly as it does today.

## Deployment

Three migrations, on top of the shipping and delivery dates migration that is
already waiting for production. All four are additive. They must be applied
immediately after the deploy that carries them, since deploys here do not run
migrations.
