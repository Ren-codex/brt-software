<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->createdBy,
            'status' => $this->status,
            'approved_by' => $this->approvedBy,
            // Carbon::parse(null) returns now(), which made every unapproved
            // remittance report the current time as its approval time.
            'approved_at' => $this->approved_at
                ? Carbon::parse($this->approved_at)->format('Y-m-d H:i:s')
                : null,
            'remarks' => $this->remarks,
            'receipts' => $this->receipts ? ReceiptResource::collection($this->receipts) : null,
        ];
    }
}
