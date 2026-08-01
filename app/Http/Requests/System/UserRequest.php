<?php

namespace App\Http\Requests\System;

use Hashids\Hashids;
use App\Models\Employee;
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
        }

        return $rules;
    }
}
