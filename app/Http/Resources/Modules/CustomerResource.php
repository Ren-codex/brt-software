<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
            'is_active' => $this->is_active,
            'status' => $this->status,
            'is_regular' => $this->is_regular,
            'is_blacklisted' => $this->is_blacklisted,
            // The edit form starts from this; without it every edit saved a
            // limit of 0 (no limit) over whatever the customer had.
            'credit_limit' => $this->credit_limit,
            'added_by' => $this->added_by,
            'created_at' => $this->created_at->format('F d, Y'),
            'updated_at' => $this->updated_at?->format('F d, Y'),

        ];
    }
}
