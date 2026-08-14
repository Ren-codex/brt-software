<?php

namespace App\Http\Resources\System\User;

use Hashids\Hashids;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->employee;
        $displayName = $employee?->name
            ?: $employee?->fullname
            ?: $this->username
            ?: $this->email;

        // Male is the default whenever sex is unknown/blank.
        $sex = $employee?->sex;
        $defaultAvatar = $sex === 'Female' ? asset('images/female-profile.png') : asset('images/male-profile.png');

        return [
            'avatar' => ($employee && $employee->avatar && $employee->avatar !== 'noavatar.jpg')
                ? asset('storage/' . $employee->avatar)
                : $defaultAvatar,
            'name' => $displayName,
            'fullname' => $employee?->fullname ?: $displayName,
            'mobile' => $employee?->mobile,
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
