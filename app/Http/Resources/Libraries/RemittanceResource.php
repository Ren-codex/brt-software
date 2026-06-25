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
            'received_amount' => $this->received_amount,
            'variance' => $this->variance,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->createdBy ? $this->createdBy->employee : null,
            'status' => $this->status,
            'approved_by' => $this->approvedBy ? $this->approvedBy->employee : null,
            'approved_at' => $this->approved_at ? Carbon::parse($this->approved_at)->format('Y-m-d H:i:s') : null,
            'remarks' => $this->remarks,
            'receipts' => $this->receipts->map(fn($r) => [
                'id'             => $r->id,
                'receipt_number' => $r->receipt_number,
                'amount_paid'    => $r->amount_paid,
                'receipt_date'   => $r->receipt_date,
                'payment_mode'   => $r->payment_mode,
                'customer'       => $r->customer,
                'status'         => $r->status,
            ])->values(),
        ];
    }

}
