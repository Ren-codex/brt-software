# Fix AR Invoice Index

## Frontend Fixes
- [x] Remove edit and cancel buttons from ARInvoices/Index.vue template
- [x] Fix onPayment method to open the Payment modal
- [x] Fix onPrint URL to use /ar-invoices instead of /sales-orders

## Payment Modal Fixes
- [x] Change form.action to 'payment' in Payment.vue
- [x] Change form.amount_paid to form.payment_amount in Payment.vue

## Backend Fixes
- [x] Implement payment method in ArInvoiceClass.php
- [x] Add print option in ArInvoiceController.php and ArInvoiceClass.php if needed

## Testing
- [x] Simplified styles to fix display bugs (removed backdrop-filter and bg-opacity for better browser compatibility)
- [ ] Test payment functionality (requires running the app)
- [ ] Test print functionality (requires running the app)
