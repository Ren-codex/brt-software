<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'middlename' => $this->middlename,
            'lastname' => $this->lastname,
            'suffix' => $this->suffix,
            'fullname' => $this->fullname,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'birthdate' => $this->birthdate,
            'sex' => $this->sex,
            'religion' => $this->religion,
            'address' => $this->address,
            'position_id' => $this->position_id,
            'avatar' => $this->avatar,
            'is_regular' => $this->is_regular,
            'is_active' => $this->is_active,
            'is_blacklisted' => $this->is_blacklisted,
            'user' => $this->user,
            'position' => $this->position,
            'added_by' => $this->added_by,
            'created_at' => $this->created_at->format('F d, Y'),
            'updated_at' => $this->updated_at->format('F d, Y'),
            'basic_salary' => $this->position->rate_per_day ?? null,
            'loans' => $this->whenLoaded('loans', function () {
                return $this->loans
                    ->map(function ($loan) {
                        return [
                            'id' => $loan->id,
                            'loan_no' => $loan->loan_no,
                            'loan_type' => $loan->loan_type,
                            'amount' => $loan->amount,
                            'interest_rate' => $loan->interest_rate,
                            'term_months' => $loan->term_months,
                            'status' => $loan->status,
                            'purpose' => $loan->purpose,
                            'amtpaid' => $loan->amtpaid,
                            'remaining_balance' => $loan->remaining_balance,
                            'remaining_term_to_pay' => $loan->remaining_term_to_pay,
                            'approved_at' => $loan->approved_at?->format('F d, Y h:i A'),
                            'created_at' => $loan->created_at?->format('F d, Y'),
                            'updated_at' => $loan->updated_at?->format('F d, Y'),
                            'payments' => $loan->payments
                                ->sortByDesc('created_at')
                                ->values()
                                ->map(function ($payment) {
                                    return [
                                        'id' => $payment->id,
                                        'amount' => $payment->amount,
                                        'payment_date' => $payment->payment_date,
                                        'paid_date' => $payment->paid_date,
                                        'created_at' => $payment->created_at?->format('F d, Y h:i A'),
                                    ];
                                }),
                        ];
                    })
                    ->values();
            }),
        ];
    }
}
