<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_id' => $this->loan_id,
            'action' => $this->action,
            'remarks' => $this->remarks,
            'actioned_by_id' => $this->actioned_by_id,
            'created_at' => $this->created_at->format('F d, Y h:i A'),
            'actioned_by' => $this->actionedBy?->employee?->fullname ?? $this->actionedBy?->username ?? 'System',
        ];
    }
}
