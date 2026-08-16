<?php

namespace App\Http\Requests\Modules;

use App\Models\ArInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user !== null && app(\App\Services\System\Permission\PermissionService::class)
            ->userHasAccess($user, 'sales', 'ar_invoices', 'encoder');
    }

    public function rules(): array
    {
        return [
            'balance_due' => 'required|numeric',
            'payment_date' => 'required|date',
            // A payment is either a single amount_paid/payment_mode pair, or a
            // 'splits' array (e.g. part Cash, part GCash) that together cover the
            // total being applied. The combined-total checks run in withValidator()
            // below since they need the DB-authoritative balance either way.
            'splits' => 'nullable|array|min:1',
            'splits.*.payment_mode' => 'required_with:splits|string',
            'splits.*.amount' => 'required_with:splits|numeric|min:0.01',
            'amount_paid' => [
                'required_without:splits',
                'nullable',
                'numeric',
                'min:1',
            ],
            'payment_mode' => 'required_without:splits|nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $invoiceId = $this->input('id');
            $invoice = $invoiceId ? ArInvoice::with('sales_order')->find($invoiceId) : null;
            $balanceDue = $invoice ? (float) $invoice->balance_due : (float) $this->input('balance_due');

            $total = $this->filled('splits')
                ? collect($this->input('splits', []))->sum(fn ($s) => (float) ($s['amount'] ?? 0))
                : (float) $this->input('amount_paid', 0);

            $field = $this->filled('splits') ? 'splits' : 'amount_paid';

            if ($total > $balanceDue + 0.00001) {
                $validator->errors()->add($field, 'Payment of PHP ' . number_format($total, 2) . ' exceeds the outstanding balance of PHP ' . number_format($balanceDue, 2) . '.');
                return;
            }

            $paymentMode = strtolower(trim((string) optional($invoice?->sales_order)->payment_mode));
            $isCredit = in_array($paymentMode, ['credit', 'credit sales'], true);

            if (!$isCredit && abs($total - $balanceDue) > 0.00001) {
                $validator->errors()->add($field, 'Partial payment is only allowed for credit sales. Cash sales must be paid in full.');
            }
        });
    }

    public function messages()
    {
        return [
            'amount_paid.required_without' => 'Amount paid is required',
            'payment_date.required' => 'Payment date is required',
        ];
    }
}
