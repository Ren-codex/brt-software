<?php

namespace App\Http\Resources\System\User;

use Hashids\Hashids;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => $this->user,
            'role' => $this->role,
            'added' => $this->added?->profile->fullname,
            'removed' => ($this->removed) ? $this->removed?->profile->fullname : '-',
            'removed_at' => $this->removed_at,
            'created_at' => $this->created_at,
            'is_active' => $this->is_active
        ];
    }
}
