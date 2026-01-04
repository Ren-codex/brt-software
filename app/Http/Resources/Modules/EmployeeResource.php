<?php

namespace App\Http\Resources\Modules;

use Crypt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'middlename' => $this->middlename,
            'lastname' => $this->lastname,
            'suffix' => $this->suffix,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'birthdate' => $this->birthdate,
            'sex' => $this->sex,
            'religion' => $this->religion,
            'address' => $this->address,
            'position_id' => $this->position_id,
            'is_regular' => $this->is_regular,
            'is_active' => $this->is_active,
            'is_blacklisted' => $this->is_blacklisted,
            'account' =>$this->account,
            'position' => $this->position,
            'added_by' => $this->added_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
