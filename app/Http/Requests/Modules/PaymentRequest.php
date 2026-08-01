<?php

namespace App\Http\Requests\Modules;

use App\Models\ArInvoice;
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
                        $fail('Amount paid cannot exceed the outstanding balance of PHP ' . number_format($this->input('balance_due'), 2));
                        return;
                    }

                    $invoiceId = $this->input('id');
                    $invoice = $invoiceId ? ArInvoice::with('sales_order')->find($invoiceId) : null;
                    $paymentMode = strtolower(trim((string) optional($invoice?->sales_order)->payment_mode));
                    $isCredit = in_array($paymentMode, ['credit', 'credit sales'], true);

                    if (!$isCredit && abs((float) $value - (float) $this->input('balance_due')) > 0.00001) {
                        $fail('Partial payment is only allowed for credit sales. Cash sales must be paid in full.');
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
