<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Derived from eager-loaded items.salesReturnItems — no extra query
        $returnItems = $this->items->flatMap->salesReturnItems;

        // Derived from eager-loaded arInvoices.receipts — no extra query
        $refundReceiptId = $this->arInvoices->first()?->receipts
            ->whereIn('receipt_type', ['refund', 'updated'])
            ->sortByDesc('id')
            ->first()?->id;

        return [
            'id' => $this->id,
            'so_number' => $this->so_number,
            'customer' => $this->customer,
            'order_date' => $this->order_date->format('F d, Y'),
            'order_date_raw' => $this->order_date->format('Y-m-d'),
            'status' => $this->status,
            'sub_status' => $this->sub_status,
            'total_amount' => $this->total_amount,
            'added_by' => $this->created_by ? $this->created_by->employee : null,
            'sales_rep_id' => $this->sales_rep_id,
            'driver_id' => $this->driver_id,
            'payment_mode' => $this->payment_mode,
            'due_date' => $this->due_date?->format('M d, Y'),
            'due_date_raw' => $this->due_date?->format('Y-m-d'),
            'transferred_to' => $this->transferred_to,
            'transferred_at' => $this->transferred_at,
            'items' => $this->items,
            'return_item_ids' => $returnItems
                ->pluck('sales_order_item_id')
                ->map(fn ($id) => (int) $id)
                ->values(),
            'return_items' => $returnItems
                ->mapWithKeys(fn ($item) => [(int) $item->sales_order_item_id => (int) ($item->return_quantity ?? 0)]),
            'return_conditions' => $returnItems
                ->mapWithKeys(fn ($item) => [(int) $item->sales_order_item_id => (string) ($item->return_condition ?? 'restockable')]),
            'refund_receipt_id' => $refundReceiptId ? (int) $refundReceiptId : null,
            'invoices' => $this->arInvoices->map(fn($inv) => [
                'id'           => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'amount_due'   => $inv->amount_due,
                'amount_paid'  => $inv->amount_paid,
                'balance_due'  => $inv->balance_due,
                'status'       => $inv->status,
                'receipts'     => $inv->receipts->map(fn($r) => [
                    'id'             => $r->id,
                    'receipt_number' => $r->receipt_number,
                    'receipt_date'   => $r->receipt_date,
                    'amount_paid'    => $r->amount_paid,
                    'payment_mode'   => $r->payment_mode,
                    'receipt_type'   => $r->receipt_type,
                    'status'         => $r->status,
                ])->values(),
            ])->values(),
            'created_at' => $this->created_at->format('M d, Y'),
            'updated_at' => $this->updated_at?->format('M d, Y'),
            'approved_by' => $this->approved_by,
        ];
    }
}
