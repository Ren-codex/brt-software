# TODO - Approve Sales Order to Return Feature

## Task: Approve Sales Order to Return feature in sales order return

### Current Issues:
1. **Index.vue**: The `onApprove` method doesn't pass the sales order items to the Approval modal
2. **SalesOrderClass.php**: The `approve` method doesn't handle `item_ids` from the form for partial returns
3. The `SalesReturn` and `SalesReturnItem` models are not being used in the approval flow

### Implementation Plan:

- [x] 1. Fix Index.vue - pass items to the Approval modal
- [x] 2. Update SalesOrderClass::approve() to accept and process item_ids for partial returns
- [x] 3. Add inventory restoration for returned items
- [x] 4. Handle partial receipt voiding for selected items only (Full returns void receipts/invoices, partial returns adjust invoice balance)
