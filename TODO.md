# Employee CRUD Fixes & Payment Mode Select Fixes

## Backend Issues
- [x] Fix EmployeeRequest validation rules to match EmployeeClass fields
- [x] Fix EmployeeResource to use correct relationship name ('user' instead of 'account')
- [x] Fix EmployeeResource to properly handle encrypted fields
- [x] Fix EmployeeClass to use correct relationship names and improve search
- [x] Fix typos in EmployeeClass messages

## Frontend Issues
- [ ] Fix selectPaymentMode method in Create.vue

## Issues Identified
1. In Create.vue, selectPaymentMode tries to access mode.name but mode is a string
2. payment_mode and payment_term fields are not saved to database (missing from model fillable, request validation, and save logic)
3. DropdownClass doesn't have payment_modes method causing errors

## Tasks
- [ ] Fix selectPaymentMode method in Create.vue
