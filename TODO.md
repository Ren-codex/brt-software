# Employee CRUD Fixes

## Backend Issues
- [x] Fix EmployeeRequest validation rules to match EmployeeClass fields
- [x] Fix EmployeeResource to use correct relationship name ('user' instead of 'account')
- [x] Fix EmployeeResource to properly handle encrypted fields
- [x] Fix EmployeeClass to use correct relationship names and improve search
- [x] Fix typos in EmployeeClass messages

## Frontend Issues
- [x] Fix Index.vue to use correct field names (firstname, lastname, mobile instead of name, contact_number)
- [x] Fix Create.vue form to use correct field names and validation
- [x] Add missing delete modal component reference

## Profile Picture Feature
- [x] Add avatar validation to EmployeeRequest
- [x] Update EmployeeClass save method to handle avatar uploads
- [x] Update EmployeeClass update method to handle avatar uploads
- [x] Add profile picture UI to Create.vue modal
- [x] Add avatar handling methods to Create.vue
- [x] Add CSS styles for profile picture UI
- [x] Add previewImage data property to Create.vue
- [x] Update EmployeeResource to include avatar field
- [x] Display avatar in employee list (Index.vue)
- [x] Fix database migration - add missing email and status columns to employees table
- [x] Fix avatar display after upload - ensure list refreshes immediately after save
- [x] Fix contact number validation and input field for proper mobile number format
- [x] Change sex field from text input to dropdown with Male/Female options
- [x] Fix employee creation modal not closing after save - move event emission outside setTimeout

## Testing
- [x] Test create employee functionality
- [x] Test update employee functionality
- [x] Test delete employee functionality
- [x] Test search functionality
- [x] Test toggle active functionality

## Purchase Requests Tab Enhancement
- [x] Add Disapproved tab to PurchaseRequestsTab.vue
- [x] Update filteredAndSortedList computed property to handle disapproved tab
- [x] Make empty state message dynamic based on active tab
