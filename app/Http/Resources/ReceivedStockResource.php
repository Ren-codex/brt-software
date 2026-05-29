<?php

namespace App\Http\Resources;

use App\Http\Resources\Libraries\ListSupplierResource;
use App\Http\Resources\System\PurchaseOrder\PurchaseOrderResource;
use App\Http\Resources\System\User\ViewResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ReceivedStockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $receivedTotal = round((float) $this->items->sum('total_cost'), 2);
        $amountPaid = round((float) ($this->amount_paid ?? 0), 2);
        $payments = $this->buildPaymentHistory($amountPaid);
        $latestPayment = $payments->first();
        $resolvedPaymentMode = ($latestPayment['payment_mode'] ?? null) === 'Payment'
            ? $this->payment_mode
            : ($latestPayment['payment_mode'] ?? $this->payment_mode);

        return [
            'id' => $this->id,
            'received_no' => $this->received_no,
            'purchase_order' => $this->purchaseOrder ? new PurchaseOrderResource($this->purchaseOrder) : [],
            'supplier' => $this->supplier ? new ListSupplierResource($this->supplier) : [],
            'received_by' => $this->receivedBy ? new ViewResource($this->receivedBy) : null,
            'received_date' => $this->received_date,
            'payment_mode' => $resolvedPaymentMode,
            'original_payment_mode' => $this->payment_mode,
            'amount_paid' => $amountPaid,
            'received_total' => $receivedTotal,
            'remaining_balance' => round(max($receivedTotal - $amountPaid, 0), 2),
            'is_fully_paid' => $receivedTotal > 0 && $amountPaid >= $receivedTotal,
            'bank_name' => $latestPayment['bank_name'] ?? $this->bank_name,
            'reference_number' => $latestPayment['reference_number'] ?? $this->reference_number,
            'payment_count' => $payments->count(),
            'payments' => $payments->values()->all(),
            'batch_code' => $this->batch_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function buildPaymentHistory(float $amountPaid): Collection
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payment_date' => optional($payment->payment_date)->toDateString() ?: $payment->payment_date,
                    'payment_mode' => $payment->payment_mode,
                    'amount_paid' => round((float) $payment->amount_paid, 2),
                    'bank_name' => $payment->bank_name,
                    'reference_number' => $payment->reference_number,
                    'is_legacy' => false,
                    'created_by' => $payment->createdBy ? new ViewResource($payment->createdBy) : null,
                    'created_at' => optional($payment->created_at)->toDateTimeString(),
                ];
            })
            : collect();

        $recordedPaymentsTotal = round((float) $payments->sum('amount_paid'), 2);
        $legacyAmount = round(max($amountPaid - $recordedPaymentsTotal, 0), 2);

        if ($legacyAmount > 0) {
            $payments->push([
                'id' => 'legacy-' . $this->id,
                'payment_date' => $this->received_date,
                'payment_mode' => strtolower((string) $this->payment_mode) === 'bank transfer'
                    ? 'Bank Transfer'
                    : (strtolower((string) $this->payment_mode) === 'cash' ? 'Cash' : 'Payment'),
                'amount_paid' => $legacyAmount,
                'bank_name' => $this->bank_name,
                'reference_number' => $this->reference_number,
                'is_legacy' => true,
                'created_by' => $this->receivedBy ? new ViewResource($this->receivedBy) : null,
                'created_at' => optional($this->created_at)->toDateTimeString(),
            ]);
        }

        return $payments
            ->sortByDesc(function (array $payment) {
                return ($payment['payment_date'] ?? '') . ' ' . ($payment['created_at'] ?? '');
            })
            ->values();
    }
}
