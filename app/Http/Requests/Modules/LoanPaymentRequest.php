<?php

namespace App\Http\Requests\Modules;

use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;

class LoanPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'loan_id' => 'required|exists:loans,id',
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    $loanId = $this->input('loan_id');
                    if (!$loanId) {
                        return;
                    }

                    $loan = Loan::find($loanId);
                    if (!$loan) {
                        return;
                    }

                    $remainingBalance = (float) $loan->remaining_balance;
                    if ((float) $value > $remainingBalance) {
                        $fail('Payment amount cannot exceed the remaining balance of ' . number_format($remainingBalance, 2) . '.');
                    }
                },
            ],
            'paid_date' => 'nullable|string|max:255',
            'paid_term' => 'nullable|integer|min:1',
        ];
    }
}
