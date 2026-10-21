<?php

namespace App\Http\Requests\System;

use Hashids\Hashids;
use App\Models\Employee;
use App\Models\ListRole;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {


        if ($this->option === 'reset_password') {
            return [
                'id' => 'required|exists:users,id',
            ];
        }
        else if($this->option === 'deactivate') {
            return [
                'id' => 'required|exists:users,id',
            ];
        }
        else if($this->option === 'role' || $this->option === 'set_role_active') {
            return [
                'user_id' => 'required|exists:users,id',
                'role_id' => 'required|exists:list_roles,id',
                'type' => 'required|string|in:add,remove,set_role_active',
            ];
        }

        $userParam = $this->route('user');
        $userId = null;

        if ($userParam) {
            $userId = is_object($userParam) ? $userParam->id : $userParam;
        }

        $rules = [
            'email' => 'required|email',
        ];

        if ($userId) {
            $rules['username'] = 'required|string|unique:users,username,' . $userId;
            $rules['email'] .= '|unique:users,email,' . $userId;
            $rules['password'] = 'nullable|string';
            $rules['confirm_password'] = 'nullable|string|same:password';
        } else {
            $rules['username'] = $this->filled('employee_id') ? 'nullable|string' : 'required|string|unique:users,username';
            $rules['email'] .= '|unique:users,email';
            // An account with no employee record can't save its own profile,
            // so only an admin account may be made without one.
            $rules['employee_id'] = [
                $this->hasAdminRole() ? 'nullable' : 'required',
                Rule::exists('employees', 'id')->whereNull('user_id'),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Choose the employee this account is for. Only Administrator, Super Admin and Mini Admin accounts can be made without one.',
            'employee_id.exists' => 'That employee already has an account.',
        ];
    }

    protected function hasAdminRole(): bool
    {
        return ListRole::whereIn('id', (array) $this->role_ids)
            ->whereIn('name', ListRole::ADMIN_ROLES)
            ->exists();
    }
}
