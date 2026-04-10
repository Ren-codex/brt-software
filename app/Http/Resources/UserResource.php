<?php

namespace App\Http\Resources;

use Crypt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->employee;
        $firstname = $employee?->firstname;
        $lastname = $employee?->lastname;
        $fullname = trim(collect([$firstname, $lastname])->filter()->implode(' '));

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'avatar' => ($employee && $employee->avatar && $employee->avatar !== 'noavatar.jpg')
            ? asset('storage/' . $employee->avatar) 
            : asset('images/avatars/avatar.jpg'), 
            'name' => $fullname !== '' ? $fullname : $this->username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'middlename' => $employee?->middlename,
            'gender' => $employee?->gender,
            'suffix' => $employee?->suffix,
            'mobile' => $employee?->mobile,
            'employee_id' => $employee?->id,
            'is_active' => $this->is_active,
            'is_new' => $this->is_new,
            'two_factor_enabled' => ($this->two_factor_secret) ? true : false,
            'two_factor_confirmed' => ($this->two_factor_confirmed_at) ? true : false,
            'password_changed_at' => $this->password_changed_at,
            'password_confirmed_at' => session('auth'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
