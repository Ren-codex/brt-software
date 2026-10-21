<?php

namespace App\Http\Requests\Modules;

use App\Services\System\Permission\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
     
        $employeeId = $this->route('employee');
        $ignoreUserId = null;
        if ($this->isMethod('put') && $employeeId) {
            $employee = \App\Models\Employee::find($employeeId);
            $ignoreUserId = $employee ? $employee->user_id : null;
        }
        return [
            'firstname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'lastname' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:10',
            // Optional even with a login account: they sign in by username.
            'email' => array_values(array_filter([
                $this->filled('username') ? 'required' : 'nullable',
                'email',
                'max:255',
                'unique:employees,email,' . $employeeId,
                // When given, the new account is signed up with it too.
                $this->wantsAccount() ? 'unique:users,email' : null,
            ])),
            'username' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($ignoreUserId),
            ],
            'create_account' => 'nullable|boolean',
            // The Login Account picker on Edit Employee: an existing account
            // to link, never one another employee is already linked to.
            'account_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('employees', 'user_id')->ignore($employeeId),
            ],
            'role_ids' => $this->filled('username') || $this->wantsAccount() ? 'required|array|min:1' : 'nullable|array',
            'role_ids.*' => 'exists:list_roles,id',
            'password' => $this->isMethod('post') || $this->filled('password') ? 'nullable|string|min:8|confirmed' : 'nullable',
            'password_confirmation' => $this->isMethod('post') || $this->filled('password') ? 'nullable|string|min:8' : 'nullable',
            'mobile' => [
                'required',
                'regex:/^09\d{9}$/',
                Rule::unique('employees', 'mobile')->ignore($employeeId),
            ],
            'birthdate' => 'required|date',
            'sex' => 'required|string|max:10',
            'religion' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'position_id' => 'required|exists:list_positions,id',
            'hours_per_day' => 'nullable|numeric|min:0|max:24',
            'overtime_rate' => 'nullable|numeric|min:0',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_regular' => 'required|boolean',
            'is_active' => 'required|boolean',
            'is_blacklisted' => 'nullable|boolean',
        ];
    }
    /**
     * The Add Employee form's "Create a login account" switch. Only on adding:
     * editing an employee never makes an account through this.
     */
    public function wantsAccount(): bool
    {
        return $this->isMethod('post') && $this->boolean('create_account');
    }

    /**
     * Whether an edit changes which account the employee is linked to. The
     * form always sends the current one, so an edit that leaves the picker
     * alone is not a change, and needs no extra grant.
     */
    public function changesAccount(): bool
    {
        if (! $this->isMethod('put') || ! $this->has('account_id')) {
            return false;
        }

        $current = \App\Models\Employee::find($this->route('employee'))?->user_id;

        return (int) ($this->input('account_id') ?: 0) !== (int) ($current ?: 0);
    }

    /**
     * Making or linking an account here is the same act as doing it in User
     * Management, so it needs that grant, not just the employees one this
     * route checks.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $permissions = app(PermissionService::class);
                $mayManageAccounts = fn () => $permissions->userHasAccess($this->user(), 'user_management', null, 'encoder');

                if ($this->wantsAccount() && ! $mayManageAccounts()) {
                    $validator->errors()->add('create_account', 'Creating a login account needs User Management access.');
                }
                if ($this->changesAccount() && ! $mayManageAccounts()) {
                    $validator->errors()->add('account_id', 'Linking a login account needs User Management access.');
                }
            },
        ];
    }

    public function messages()
    {
        return [
            'firstname.required' => 'First name is required',
            'lastname.required' => 'Last name is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already taken',
            'mobile.regex' => 'Mobile number must be in the format 09XXXXXXXXX',
            'mobile.unique' => 'This mobile number is already taken',
            'account_id.unique' => 'That account is already linked to another employee',
            'role_ids.required' => 'Please select at least one role',
            'role_ids.min' => 'Please select at least one role',
            'avatar.image' => 'Avatar must be an image file',
            'avatar.mimes' => 'Avatar must be a JPEG, PNG, JPG, or GIF file',
            'avatar.max' => 'Avatar file size must not exceed 2MB',
        ];

    }

}
