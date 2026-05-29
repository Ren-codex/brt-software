<?php

namespace App\Http\Requests;

use App\Models\ReceivedStock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApplyReceivedStockPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_mode' => 'required|in:Cash,Bank Transfer',
            'payment_amount' => 'required|numeric|min:0.01',
            'bank_name' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var ReceivedStock|null $receivedStock */
            $receivedStock = $this->route('receivedStock');

            if (!$receivedStock) {
                $validator->errors()->add('payment_amount', 'Received stock record was not found.');
                return;
            }

            $receivedStock->loadMissing('items');

            $paymentMode = trim((string) $this->input('payment_mode'));
            $paymentAmount = round((float) $this->input('payment_amount', 0), 2);
            $bankName = trim((string) $this->input('bank_name', ''));
            $referenceNumber = trim((string) $this->input('reference_number', ''));

            $totalAmount = round((float) $receivedStock->items->sum('total_cost'), 2);
            $currentPaid = round((float) ($receivedStock->amount_paid ?? 0), 2);
            $remainingBalance = round(max($totalAmount - $currentPaid, 0), 2);
            if ($remainingBalance <= 0) {
                $validator->errors()->add('payment_amount', 'This payable has already been fully settled.');
            }

            if ($paymentAmount > $remainingBalance) {
                $validator->errors()->add('payment_amount', 'Payment amount cannot exceed the remaining payable balance.');
            }

            if ($paymentMode === 'Bank Transfer') {
                if ($bankName === '') {
                    $validator->errors()->add('bank_name', 'Bank name is required for bank transfer payments.');
                }

                if ($referenceNumber === '') {
                    $validator->errors()->add('reference_number', 'Reference number is required for bank transfer payments.');
                }
            }
        });
    }
}
