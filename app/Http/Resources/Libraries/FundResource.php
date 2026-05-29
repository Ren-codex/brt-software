<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'gl_code'       => $this->gl_code,
            'balance'       => (float) $this->balance,
            'weekly_budget'         => $this->weekly_budget ? (float) $this->weekly_budget : null,
            'low_balance_threshold' => $this->low_balance_threshold ? (float) $this->low_balance_threshold : null,
            'is_active'             => (bool) $this->is_active,
            'created_at'    => $this->created_at,
        ];
    }
}
