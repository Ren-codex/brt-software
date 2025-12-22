# TODO: Fix Activate Set Active and Remove Role Bugs

## Completed Tasks
- [x] Fix backend bug in UserClass.php: Correct ID assignment for role removal (changed $id = $role_request->user_id; to $id = $data->id;)
- [x] Fix frontend success message in Activate.vue: Update message from "Role has been removed successfully!" to "Role has been set to active successfully!"

## Followup Steps
- [ ] Test the role activation functionality to ensure it sets roles to active correctly
- [ ] Test the role removal functionality to ensure it removes roles without errors
- [ ] Verify that success messages display correctly in the UI
