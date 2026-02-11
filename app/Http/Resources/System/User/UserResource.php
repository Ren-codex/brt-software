<?php

namespace App\Http\Resources\System\User;

use Hashids\Hashids;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'avatar' => ($this->employee && $this->employee->avatar && $this->employee->avatar !== 'noavatar.jpg')
            ? asset('storage/' . $this->employee->avatar)
            : asset('images/avatars/avatar.jpg'),
            'name' => $this->employee ? $this->employee->fullname : $this->username,
            'fullname' => $this->employee ? $this->employee->fullname : $this->username,
            'mobile' => $this->employee ? $this->employee->mobile : null,
            'email' => $this->email,
            'username' => $this->username,
            'roles' => RoleResource::collection($this->myroles),
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'two_factor_confirmed_at' => $this->two_factor_confirmed_at,
            'password_changed_at' => $this->password_changed_at,
            'created_at' => $this->created_at
        ];
    }
}
