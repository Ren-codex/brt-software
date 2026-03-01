<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanPaymentResource extends JsonResource
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
            'loan_no' => $this->loan?->loan_no,
            'employee' => $this->loan?->employee?->fullname,
            'amount' => $this->amount,
            'paid_date' => $this->paid_date?->format('Y-m-d'),
            'paid_term' => $this->paid_term,
            'remarks' => $this->remarks,
            'added_by_id' => $this->added_by_id,
            'added_by' => $this->addedBy?->employee?->fullname ?? $this->addedBy?->username,
            'created_at' => $this->created_at?->format('F d, Y h:i A'),
            'updated_at' => $this->updated_at?->format('F d, Y h:i A'),
        ];
    }
}
