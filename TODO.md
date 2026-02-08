# Fix Display of Retail/Wholesale Price in Sales Order Add Item Modal

## Information Gathered
- The issue occurs when editing an existing item in the sales order.
- In the `edit` method of `AddItem.vue`, the price is set directly from the old data (`data.price`) instead of recalculating based on the current inventory's retail or wholesale price.
- The `onProductChange` method correctly calculates the price based on `price_type` (retail or wholesale) from the product's current inventory stocks.
- The `DropdownClass.php` provides `retail_price` and `wholesale_price` from `inventory_stocks` table.

## Plan
- [x] Modify the `edit` method in `resources/js/Pages/Modules/Sales/Components/SalesOrders/Modals/AddItem.vue` to call `onProductChange()` after setting `product_id` and `price_type`, ensuring the price is recalculated from current inventory.

## Implementation
- [x] Updated the `edit` method to remove direct assignment of `price` and `batch_code` from old data, and instead call `onProductChange()` to fetch current values from inventory.
- [x] Modified `DropdownClass.php` to fetch `retail_price` and `wholesale_price` directly from `inventory_stocks` table, without defaulting to product price.
- [x] Updated `onPriceTypeChange` to handle cases when no product is selected.

## Followup Steps
- [x] Updated the InventoryStocksTableSeeder to include sample retail_price and wholesale_price values.
- [x] Ran the seeder to populate the database with price data.
- [x] Fixed quantity restriction in AddItem modal to validate against available stock.
- [x] Added light red background to sales order rows that are due soon (within 7 days).
- [x] Set initial due date to 3 days ahead when creating new sales orders.
- [x] Set due date to 3 days ahead when payment mode is set to Credit.
- [ ] Test the edit functionality in the sales order to verify that the price updates correctly when changing price type or editing an item.
- [ ] Ensure batch_code is also updated correctly.
- [ ] Check for any side effects in the add new item flow.
- [ ] Test the due date highlighting in the sales orders index.
- [ ] Test the automatic due date setting for new orders and credit payments.
