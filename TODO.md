# Fix Payment Mode Select

## Issues Identified
1. In Create.vue, selectPaymentMode tries to access mode.name but mode is a string
2. payment_mode and payment_term fields are not saved to database (missing from model fillable, request validation, and save logic)
3. DropdownClass doesn't have payment_modes method causing errors

## Tasks
- [ ] Fix selectPaymentMode method in Create.vue
- [ ] Add payment_mode/payment_term to SalesOrder model fillable
- [ ] Add validation in SalesOrderRequest
- [ ] Update SalesOrderClass save method to handle payment fields
- [ ] Create migration to add payment_mode and payment_term columns to sales_orders table
- [ ] Add payment_modes method to DropdownClass or use hardcoded modes
