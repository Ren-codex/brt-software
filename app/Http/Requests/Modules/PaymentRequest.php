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
                    // Validate against the invoice's ACTUAL balance in the DB, not
                    // the client-supplied balance_due (which could be stale/tampered).
                    $invoiceId = $this->input('id');
                    $invoice = $invoiceId ? ArInvoice::with('sales_order')->find($invoiceId) : null;
                    $balanceDue = $invoice ? (float) $invoice->balance_due : (float) $this->input('balance_due');

                    if ($value > $balanceDue) {
                        $fail('Amount paid cannot exceed the outstanding balance of PHP ' . number_format($balanceDue, 2));
                        return;
                    }

                    $paymentMode = strtolower(trim((string) optional($invoice?->sales_order)->payment_mode));
                    $isCredit = in_array($paymentMode, ['credit', 'credit sales'], true);

                    if (!$isCredit && abs((float) $value - $balanceDue) > 0.00001) {
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
