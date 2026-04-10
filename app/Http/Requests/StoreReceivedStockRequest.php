<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReceivedStockRequest extends FormRequest
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
            'po_id' => 'required|exists:purchase_orders,id',
            'supplier_id' => 'required|exists:list_suppliers,id',
            'payment_mode' => 'required|in:Cash,Bank Transfer,Credit',
            'amount_paid' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.total_cost' => 'required|numeric|min:0',
            'items.*.to_received_quantity' => 'required|numeric|min:0',
            'items.*.po_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.retail_price' => 'nullable|numeric|min:0',
            'items.*.wholesale_price' => 'nullable|numeric|min:0',
            'items.*.expiration_date' => 'nullable|date',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $paymentMode = (string) $this->input('payment_mode');
            $amountPaid = $this->input('amount_paid');
            $bankName = trim((string) $this->input('bank_name', ''));
            $referenceNumber = trim((string) $this->input('reference_number', ''));

            $totalAmount = collect($this->input('items', []))
                ->sum(function (array $item) {
                    $quantity = (float) ($item['to_received_quantity'] ?? 0);
                    $unitCost = (float) ($item['unit_cost'] ?? 0);

                    return round($quantity * $unitCost, 2);
                });

            if ($paymentMode !== 'Credit') {
                if ($amountPaid === null || $amountPaid === '') {
                    $validator->errors()->add('amount_paid', 'Amount paid is required when an immediate payment mode is selected.');
                    return;
                }

                if ((float) $amountPaid <= 0) {
                    $validator->errors()->add('amount_paid', 'Amount paid must be greater than zero.');
                }

                if ((float) $amountPaid > $totalAmount) {
                    $validator->errors()->add('amount_paid', 'Amount paid cannot exceed the total purchase cost.');
                }
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
