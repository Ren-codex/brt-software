<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RemittanceResource extends JsonResource
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
            'remittance_no' => $this->remittance_no,
            'remittance_date' => $this->remittance_date,
            'summary' => $this->summary,
            'total_amount' => $this->total_amount,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->createdBy,
            'status' => $this->status,
            'approved_by' => $this->approvedBy,
        ];
    }
}
