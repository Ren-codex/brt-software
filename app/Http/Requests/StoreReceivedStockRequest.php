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
            'received_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:1000',
            'payment_mode' => 'required|in:Cash,Bank Transfer,Check,Credit,Split',
            // A receipt may be settled with several methods at once.
            'payment_lines' => 'nullable|array',
            'payment_lines.*.payment_mode' => 'required_with:payment_lines|in:Cash on Hand,Cash,Bank Transfer,Check',
            'payment_lines.*.payment_amount' => 'required_with:payment_lines|numeric|min:0.01',
            'payment_lines.*.bank_account_id' => 'nullable|exists:bank_accounts,id',
            'payment_lines.*.bank_name' => 'nullable|string|max:255',
            'payment_lines.*.reference_number' => 'nullable|string|max:255',
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

            // A split carries its own lines; the single-mode rules below do not
            // apply to it. Each line is checked on its own terms, and lines
            // drawing on the same source are checked against that source's
            // balance combined — two affordable cash lines can otherwise
            // jointly overdraw cash.
            $lines = $this->input('payment_lines', []);
            if (is_array($lines) && $lines !== []) {
                $cash = app(CashManagementService::class);
                $perSource = [];
                $grand = 0.0;

                foreach ($lines as $i => $line) {
                    $mode = trim((string) ($line['payment_mode'] ?? ''));
                    $amount = round((float) ($line['payment_amount'] ?? 0), 2);
                    $ref = trim((string) ($line['reference_number'] ?? ''));
                    $bank = trim((string) ($line['bank_name'] ?? ''));
                    $bankId = $line['bank_account_id'] ?? null;
                    $grand += $amount;

                    if ($mode === 'Check' && $ref === '') {
                        $validator->errors()->add("payment_lines.$i.reference_number", 'Reference number is required for check payments.');
                    }
                    if ($mode === 'Bank Transfer') {
                        if ($bank === '') {
                            $validator->errors()->add("payment_lines.$i.bank_name", 'Bank name is required for bank transfer payments.');
                        }
                        if ($ref === '') {
                            $validator->errors()->add("payment_lines.$i.reference_number", 'Reference number is required for bank transfer payments.');
                        }
                    }

                    $key = $mode === 'Bank Transfer' ? 'bank:'.$bankId : 'cash';
                    $perSource[$key] = ($perSource[$key] ?? 0) + ($mode === 'Check' ? 0 : $amount);
                }

                if (round($grand, 2) > $totalAmount) {
                    $validator->errors()->add('payment_lines', 'Payments total ₱' . number_format($grand, 2) . ', which exceeds the total purchase cost of ₱' . number_format($totalAmount, 2) . '.');
                }

                foreach ($perSource as $key => $requested) {
                    $requested = round($requested, 2);
                    if ($requested <= 0) {
                        continue;
                    }
                    if ($key === 'cash') {
                        $available = $cash->getCashOnHandBalance();
                        if ($requested > $available) {
                            $validator->errors()->add('payment_lines', 'Cash payments total ₱' . number_format($requested, 2) . ', which exceeds available Cash on Hand (₱' . number_format($available, 2) . ').');
                        }
                    } elseif (str_starts_with($key, 'bank:')) {
                        $bankId = substr($key, 5);
                        if ($bankId !== '' && $bankId !== 'null') {
                            $available = $cash->getBankAccountBalance((int) $bankId);
                            if ($requested > $available) {
                                $validator->errors()->add('payment_lines', 'Bank transfers total ₱' . number_format($requested, 2) . ' against an account holding ₱' . number_format($available, 2) . '.');
                            }
                        }
                    }
                }

                return; // split validated; skip the single-mode rules
            }

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
