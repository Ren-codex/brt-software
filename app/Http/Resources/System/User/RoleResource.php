<?php

namespace App\Http\Resources\System\User;

use Hashids\Hashids;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $addedBy = $this->added;
        $removedBy = $this->removed;

        $addedByName = $addedBy?->employee?->fullname
            ?: $addedBy?->username
            ?: $addedBy?->email
            ?: '-';

        $removedByName = $removedBy?->employee?->fullname
            ?: $removedBy?->username
            ?: $removedBy?->email
            ?: '-';

        return [
            'user' => $this->user,
            'role' => $this->role,
            'added' => $addedByName,
            'removed' => $removedByName,
            'removed_at' => $this->removed_at,
            'created_at' => $this->created_at,
            'is_active' => $this->is_active
        ];
    }
}
