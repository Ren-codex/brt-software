<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the signed-in user's own profile edit. Kept apart from
// Modules\EmployeeRequest, which also demands position, status and role
// fields the profile page has no way to send.
class ProfileRequest extends FormRequest
{
    // Accounts that may save their profile with any field left blank.
    protected const ADMIN_ROLES = ['Administrator', 'Super Admin', 'Mini Admin'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->user()->employee?->id;
        $required = $this->isAdmin() ? 'nullable' : 'required';

        return [
            'firstname' => "$required|string|max:255",
            'middlename' => 'nullable|string|max:255',
            'lastname' => "$required|string|max:255",
            'suffix' => 'nullable|string|max:10',
            'mobile' => [
                $required,
                'regex:/^09\d{9}$/',
                Rule::unique('employees', 'mobile')->ignore($employeeId),
            ],
            'gender' => "$required|in:Male,Female",
        ];
    }

    protected function isAdmin(): bool
    {
        return $this->user()->roles()
            ->where('user_roles.is_active', 1)
            ->whereIn('name', self::ADMIN_ROLES)
            ->exists();
    }

    public function messages()
    {
        return [
            'firstname.required' => 'First name is required',
            'lastname.required' => 'Last name is required',
            'mobile.regex' => 'Mobile number must be in the format 09XXXXXXXXX',
            'mobile.unique' => 'This mobile number is already taken',
            'gender.required' => 'Please select a gender',
        ];
    }
}
