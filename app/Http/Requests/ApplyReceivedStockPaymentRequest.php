<?php

namespace App\Http\Requests;

use App\Models\ReceivedStock;
use App\Services\Accounting\CashManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A payment may be split across several methods in one transaction, so the
 * request carries a list of lines. A body without `lines` is treated as a
 * single line, which keeps the previous single-payment callers working.
 */
class ApplyReceivedStockPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Normalise the legacy single-payment body into a one-line split. */
    protected function prepareForValidation(): void
    {
        if (!$this->has('lines') && $this->filled('payment_mode')) {
            $this->merge([
                'lines' => [[
                    'payment_mode'     => $this->input('payment_mode'),
                    'payment_amount'   => $this->input('payment_amount'),
                    'bank_account_id'  => $this->input('bank_account_id'),
                    'bank_name'        => $this->input('bank_name'),
                    'reference_number' => $this->input('reference_number'),
                ]],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'lines'                    => 'required|array|min:1',
            'lines.*.payment_mode'     => 'required|in:Check,Bank Transfer,Cash on Hand',
            'lines.*.payment_amount'   => 'required|numeric|min:0.01',
            'lines.*.bank_account_id'  => 'nullable|exists:bank_accounts,id',
            'lines.*.bank_name'        => 'nullable|string|max:255',
            'lines.*.reference_number' => 'nullable|string|max:255',
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

            $lines = $this->input('lines', []);
            if (!is_array($lines) || $lines === []) {
                return; // the rules above already reported this
            }

            $receivedStock->loadMissing('items');

            $totalAmount      = round((float) $receivedStock->items->sum('total_cost'), 2);
            $currentPaid      = round((float) ($receivedStock->amount_paid ?? 0), 2);
            $remainingBalance = round(max($totalAmount - $currentPaid, 0), 2);

            if ($remainingBalance <= 0) {
                $validator->errors()->add('lines', 'This payable has already been fully settled.');
                return;
            }

            $cash = app(CashManagementService::class);

            // Per-source running totals. Two lines drawing on the same source
            // must be checked against that source's balance combined — checked
            // individually, two affordable lines can jointly overdraw it.
            $requestedPerSource = [];
            $grandTotal = 0.0;

            foreach ($lines as $index => $line) {
                $mode      = trim((string) ($line['payment_mode'] ?? ''));
                $amount    = round((float) ($line['payment_amount'] ?? 0), 2);
                $bankName  = trim((string) ($line['bank_name'] ?? ''));
                $reference = trim((string) ($line['reference_number'] ?? ''));
                $bankId    = $line['bank_account_id'] ?? null;

                $grandTotal += $amount;

                if ($mode === 'Check' && $reference === '') {
                    $validator->errors()->add("lines.$index.reference_number", 'Reference number is required for check payments.');
                }

                if ($mode === 'Bank Transfer') {
                    if ($bankName === '') {
                        $validator->errors()->add("lines.$index.bank_name", 'Bank name is required for bank transfer payments.');
                    }
                    if ($reference === '') {
                        $validator->errors()->add("lines.$index.reference_number", 'Reference number is required for bank transfer payments.');
                    }
                }

                $sourceKey = $mode === 'Bank Transfer' ? 'bank:'.$bankId : $mode;
                $requestedPerSource[$sourceKey] = ($requestedPerSource[$sourceKey] ?? 0) + $amount;
            }

            $grandTotal = round($grandTotal, 2);
            if ($grandTotal > $remainingBalance) {
                $validator->errors()->add(
                    'lines',
                    'Payments total ₱' . number_format($grandTotal, 2) . ', which exceeds the remaining payable of ₱' . number_format($remainingBalance, 2) . '.'
                );
            }

            foreach ($requestedPerSource as $sourceKey => $requested) {
                $requested = round($requested, 2);

                if ($sourceKey === 'Cash on Hand') {
                    $available = $cash->getCashOnHandBalance();
                    if ($requested > $available) {
                        $validator->errors()->add(
                            'lines',
                            'Cash payments total ₱' . number_format($requested, 2) . ', which exceeds available Cash on Hand (₱' . number_format($available, 2) . ').'
                        );
                    }
                    continue;
                }

                if (str_starts_with($sourceKey, 'bank:')) {
                    $bankId = substr($sourceKey, 5);
                    if ($bankId === '' || $bankId === 'null') {
                        continue; // no account chosen; the field rules cover it
                    }
                    $available = $cash->getBankAccountBalance((int) $bankId);
                    if ($requested > $available) {
                        $validator->errors()->add(
                            'lines',
                            'Bank transfers total ₱' . number_format($requested, 2) . ' against an account holding ₱' . number_format($available, 2) . '.'
                        );
                    }
                }
            }
        });
    }
}
