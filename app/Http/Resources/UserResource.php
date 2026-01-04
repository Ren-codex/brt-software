<?php

namespace App\Http\Resources;

use Crypt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'avatar' => ($this->employee && $this->employee->avatar && $this->employee->avatar !== 'noavatar.jpg')
            ? asset('storage/' . $this->employee->avatar) 
            : asset('images/avatars/avatar.jpg'), 
            'name' => $this->employee->firstname.' '.$this->employee->lastname,
            'firstname' => $this->employee->firstname,
            'lastname' => $this->employee->lastname,
            'middlename' => $this->employee->middlename,
            'gender' => $this->employee->gender,
            'suffix' => $this->employee->suffix,
            'mobile' => $this->employee->mobile,
            'employee_id' => $this->employee->id,
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
