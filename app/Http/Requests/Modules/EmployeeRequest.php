<?php

namespace App\Http\Requests\Modules;

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
            'email' => $this->filled('username')
                ? 'required|email|max:255|unique:employees,email,' . $employeeId
                : 'nullable|email|max:255|unique:employees,email,' . $employeeId,
            'username' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($ignoreUserId),
            ],
            'role_ids' => $this->filled('username') ? 'required|array|min:1' : 'nullable|array',
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
            'address' => 'required|string|max:500',
            'position_id' => 'required|exists:list_positions,id',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_regular' => 'required|boolean',
            'is_active' => 'required|boolean',
            'is_blacklisted' => 'nullable|boolean',
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
            'role_ids.required' => 'Please select at least one role',
            'role_ids.min' => 'Please select at least one role',
            'avatar.image' => 'Avatar must be an image file',
            'avatar.mimes' => 'Avatar must be a JPEG, PNG, JPG, or GIF file',
            'avatar.max' => 'Avatar file size must not exceed 2MB',
        ];

    }

}
