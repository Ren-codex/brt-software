<?php

namespace App\Http\Requests;

use App\Services\Accounting\CashManagementService;
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
            'payment_mode' => 'required|in:Cash,Bank Transfer,Check,Credit',
            'due_date' => 'nullable|date|required_if:payment_mode,Credit',
            'amount_paid' => 'nullable|numeric|min:0',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
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

            if ($paymentMode === 'Cash' && $amountPaid !== null && $amountPaid !== '' && (float) $amountPaid > 0) {
                $cashOnHand = app(CashManagementService::class)->getCashOnHandBalance();
                if ((float) $amountPaid > $cashOnHand) {
                    $validator->errors()->add(
                        'amount_paid',
                        'Amount paid exceeds available Cash on Hand (₱' . number_format($cashOnHand, 2) . '). Reduce the amount, use Bank Transfer, or record this as Credit and settle it later.'
                    );
                }
            }

            if ($paymentMode === 'Credit') {
                $dueDate = $this->input('due_date');
                if ($dueDate && $dueDate < now()->toDateString()) {
                    $validator->errors()->add('due_date', 'Due date cannot be earlier than today.');
                }
            }

            if ($paymentMode === 'Bank Transfer') {
                if ($bankName === '') {
                    $validator->errors()->add('bank_name', 'Bank name is required for bank transfer payments.');
                }

                if ($referenceNumber === '') {
                    $validator->errors()->add('reference_number', 'Reference number is required for bank transfer payments.');
                }

                $bankAccountId = $this->input('bank_account_id');
                if ($bankAccountId && $amountPaid !== null && $amountPaid !== '' && (float) $amountPaid > 0) {
                    $bankBalance = app(CashManagementService::class)->getBankAccountBalance((int) $bankAccountId);
                    if ((float) $amountPaid > $bankBalance) {
                        $validator->errors()->add(
                            'amount_paid',
                            'Amount exceeds this bank account\'s available balance (₱' . number_format($bankBalance, 2) . ').'
                        );
                    }
                }
            }

            if ($paymentMode === 'Check') {
                if ($referenceNumber === '') {
                    $validator->errors()->add('reference_number', 'Reference number is required for check payments.');
                }
            }
        });
    }
}
