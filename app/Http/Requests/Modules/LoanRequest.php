<?php

namespace App\Http\Requests\Modules;

use App\Models\LoanTypeLimit;
use Illuminate\Foundation\Http\FormRequest;

class LoanRequest extends FormRequest
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
            'employee_id' => 'required|exists:employees,id',
            'loan_type' => 'required|in:personal,salary,emergency,cash_advance',
            'payment_type' => 'required|in:cash,check',
            'amount' => ['required', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                $limit = LoanTypeLimit::where('loan_type', $this->input('loan_type'))->first();

                if ($limit && $value > $limit->max_amount) {
                    $fail('The withdrawal amount must not exceed ₱'.number_format($limit->max_amount, 2).' for this loan type.');
                }
            }],
            'interest_rate' => 'required|numeric|min:0|max:100',
            'term_months' => 'required|integer|min:1',
            'status' => 'nullable|in:pending,approved,rejected,active,completed',
            'purpose' => 'nullable|string|max:1000',
        ];
    }
}
