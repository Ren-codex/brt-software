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
                'password' => 'required|string|min:8|regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'confirm_password' =>  'nullable|string|same:password',
            ];
        }
        else if($this->option === 'deactivate') {
            return [
                'id' => 'required|exists:users,id',
            ];
        }

        $userParam = $this->route('user');
        $userId = null;

        if ($userParam) {
            $userId = is_object($userParam) ? $userParam->id : $userParam;
        }

        $rules = [
            'username' => 'required|string',
            'email' => 'required|email',
        ];

        if ($userId) {
            $rules['username'] .= '|unique:users,username,' . $userId;
            $rules['email'] .= '|unique:users,email,' . $userId;
            $rules['password'] = 'nullable|string';
            $rules['confirm_password'] = 'nullable|string|same:password';
        } else {
            $rules['username'] .= '|unique:users,username';
            $rules['email'] .= '|unique:users,email';
            $rules['password'] = 'required|string';
            $rules['confirm_password'] = 'required|string|same:password';
        }

        return $rules;
    }
}
