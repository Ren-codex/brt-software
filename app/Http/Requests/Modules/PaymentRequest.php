<?php

namespace App\Http\Requests\Modules;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'balance_due' => 'required|numeric',
            'amount_paid' => [
                'required',
                'numeric',
                'min:1',
                function ($attribute, $value, $fail) {
                    if ($value > $this->input('balance_due')) {
                        $fail('Amount paid cannot exceed the outstanding balance of ₱' . number_format($this->input('balance_due'), 2));
                    }
                },
            ],
            'payment_date' => 'required|date',
        ];

    }
    public function messages()
    {
        return [
            'amount_paid.required' => 'Amount paid is required',
            'payment_date.required' => 'Payment date is required',
        ];

    }

}
