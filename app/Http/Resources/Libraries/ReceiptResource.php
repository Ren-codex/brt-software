<?php

namespace App\Http\Resources\Libraries;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    private const RETURN_WINDOW_DAYS = 7;

    public function toArray($request)
    {
        $salesOrder = optional($this->arInvoice)->sales_order;
        $receiptDate = $this->receipt_date ? Carbon::parse($this->receipt_date) : null;
        $daysSinceReceipt = $receiptDate ? $receiptDate->startOfDay()->diffInDays(now()->startOfDay(), false) : null;
        $salesOrderStatus = optional($salesOrder->status)->slug;
        $receiptStatus = optional($this->status)->slug;
        $isWithinReturnWindow = $daysSinceReceipt !== null && $daysSinceReceipt >= 0 && $daysSinceReceipt <= self::RETURN_WINDOW_DAYS;
        $hasEligibleSalesOrder = $salesOrder && !in_array($salesOrderStatus, ['sales-returned', 'sales-return-approval', 'cancelled'], true);
        $hasEligibleReceiptStatus = !in_array($receiptStatus, ['cancelled'], true);
        $isReturnEligible = $isWithinReturnWindow && $hasEligibleSalesOrder && $hasEligibleReceiptStatus;

        $returnEligibilityReason = null;
        if (!$receiptDate) {
            $returnEligibilityReason = 'Receipt date is missing.';
        } elseif (!$isWithinReturnWindow) {
            $returnEligibilityReason = 'Return window has expired. Only receipts within 7 days are eligible.';
        } elseif (!$salesOrder) {
            $returnEligibilityReason = 'Receipt is not linked to a sales order.';
        } elseif (!$hasEligibleReceiptStatus) {
            $returnEligibilityReason = 'Cancelled receipts are not eligible for return.';
        } elseif (!$hasEligibleSalesOrder) {
            $returnEligibilityReason = 'This sales order is no longer eligible for return.';
        }

        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'receipt_type' => $this->receipt_type ?? 'payment',
            'source_receipt' => $this->sourceReceipt ? [
                'id' => $this->sourceReceipt->id,
                'receipt_number' => $this->sourceReceipt->receipt_number,
            ] : null,
            'receipt_date' => $this->receipt_date,
            'amount_paid' => $this->amount_paid,
            'balance_due' => $this->balance_due,
            'status_id' => $this->status_id,
            'customer_id' => $this->customer_id,
            'customer' => $this->customer ? $this->customer : null,
            'ar_invoice_id' => $this->ar_invoice_id,
            'status' => $this->status,
            'created_at' => Carbon::parse($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at,
            'payment_mode' => $this->payment_mode ?? optional($salesOrder)->payment_mode,
            'return_policy' => [
                'window_days' => self::RETURN_WINDOW_DAYS,
                'days_since_receipt' => $daysSinceReceipt,
                'days_remaining' => $daysSinceReceipt === null ? null : max(0, self::RETURN_WINDOW_DAYS - $daysSinceReceipt),
                'is_within_window' => $isWithinReturnWindow,
                'is_eligible' => $isReturnEligible,
                'reason' => $returnEligibilityReason,
            ],
            'sales_order' => $salesOrder ? [
                'id' => $salesOrder->id,
                'so_number' => $salesOrder->so_number,
                'order_date' => optional($salesOrder->order_date)->format('Y-m-d'),
                'status' => $salesOrder->status,
                'customer' => $salesOrder->customer,
                'items' => $salesOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => optional($item->product)->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'price_type' => $item->price_type,
                        'batch_code' => $item->batch_code,
                    ];
                })->values(),
            ] : null,
        ];
    }
}
