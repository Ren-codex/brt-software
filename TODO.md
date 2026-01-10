# TODO: Implement Sub-Status for Sales Order Adjustments

## Tasks
- [x] Add migration to add `sub_status_id` column to `sales_orders` table
- [x] Update `SalesOrder` model to include `sub_status_id` in fillable
- [x] Modify `SalesAdjustmentController` to set `sub_status_id` for adjustments and set main status to 'Adjusted'
- [x] Run the migration to update the database
- [x] Test the adjustment functionality to ensure sub-statuses are applied correctly
