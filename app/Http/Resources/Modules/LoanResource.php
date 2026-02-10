<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'loan_type' => $this->loan_type,
            'amount' => $this->amount,
            'interest_rate' => $this->interest_rate,
            'term_months' => $this->term_months,
            'status' => $this->status,
            'purpose' => $this->purpose,
            'added_by_id' => $this->added_by_id,
            'amtpaid' => $this->amtpaid,
            'remaining_balance' => $this->remaining_balance,
            'remaining_term_to_pay' => $this->remaining_term_to_pay,
            'created_at' => $this->created_at->format('F d, Y'),
            'updated_at' => $this->updated_at->format('F d, Y'),
        ];
    }
}
