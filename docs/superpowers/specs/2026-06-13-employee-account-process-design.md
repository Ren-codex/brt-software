# Employee Account Process — Design Spec
**Date:** 2026-06-13

## Overview

Separate employee profiling from user account management. Account creation and editing moves entirely into the Accounts/Users module. The Employee module becomes read-only with respect to accounts.

## Goals

- Remove account creation from the employee onboarding form.
- Remove account management actions (Edit Account, Reset Password) from the employee details page.
- Allow admins to link a new user account to an existing employee directly from the Accounts/Users Create modal.

---

## Section 1 — Employee Profiling Changes

### `Employees/Modals/Create.vue`
Remove the following fields from the employee creation form:
- Username
- Password / Confirm Password
- Roles

No backend change required. `EmployeeClass@save()` already skips user creation when those fields are absent.

### `Employees/Details.vue`
- Remove the **Edit Account** button and its `@click` handler (`openAccountModal`).
- Remove the **Reset Password** button and its `@click` handler (`openResetPasswordModal`).
- Remove the `AccountEdit` component import, registration, and `<AccountEdit>` template tag.
- Remove the `ResetPassword` component import, registration, and `<ResetPassword>` template tag.
- Remove the `openAccountModal()` and `openResetPasswordModal()` methods.
- Keep the read-only **Account Details** card (username, roles, account status) — display only, no actions.

### Files to delete (no longer referenced after above changes)
- `resources/js/Pages/Modules/Employees/Modals/AccountEdit.vue`
- `resources/js/Pages/Modules/Employees/Modals/ResetPassword.vue`

---

## Section 2 — Accounts/Users Module Changes

### `Accounts/Users/Modals/Create.vue`
Add an optional **Link to Employee** Multiselect field at the top of the create form (create mode only, hidden in edit mode).

- Options: employees who have no user account (`user_id IS NULL`), displayed as full name + position.
- Selecting an employee auto-fills the **Email** field from `employee.email`.
- Field is optional — standalone accounts not linked to any employee remain supported.
- Add `employee_id` to the Inertia form data (null by default).

### `Accounts/Users/Index.vue`
Pass unlinked employees as a new Inertia prop: `dropdowns.unlinked_employees`.

### Backend — `DropdownClass`
Add a method `employeesWithoutAccount()`:
```php
Employee::whereNull('user_id')
    ->with('position')
    ->orderBy('lastname')
    ->get()
    ->map(fn($e) => ['value' => $e->id, 'label' => $e->fullname . ($e->position ? ' — ' . $e->position->title : '')]);
```

### Backend — `POST /users` handler (UserClass or UsersController)
After successfully creating the user, if `employee_id` is present in the request:
```php
if ($request->filled('employee_id')) {
    Employee::where('id', $request->employee_id)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);
}
```
The `whereNull('user_id')` guard prevents overwriting an existing link if the request is replayed.

### Backend — Controller index action for Accounts/Users
Add `unlinked_employees` to the props passed on the default render:
```php
'dropdowns' => [
    'roles' => $this->dropdown->roles(),
    'unlinked_employees' => $this->dropdown->employeesWithoutAccount(),
]
```

---

## Data Model

No migrations required. The `employees.user_id` foreign key already exists. The change is purely in who sets it and when.

```
employees.user_id (FK → users.id, nullable)
```

- **Before:** Set during employee creation if username/password provided.
- **After:** Set during user account creation if an employee is selected.

---

## Out of Scope

- Unlinking an account from an employee (not requested).
- Bulk account creation.
- Email notifications on account creation.
- Any changes to the Reset Password or Edit Account flows inside Accounts/Users (they already exist and are sufficient).
