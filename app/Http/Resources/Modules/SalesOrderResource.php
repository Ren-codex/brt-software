<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $returnItems = DB::table('sales_return_items')
            ->join('sales_order_items', 'sales_order_items.id', '=', 'sales_return_items.sales_order_item_id')
            ->where('sales_order_items.sales_order_id', $this->id)
            ->select('sales_return_items.sales_order_item_id', 'sales_return_items.return_quantity', 'sales_return_items.return_condition')
            ->get();
        $refundReceiptId = DB::table('receipts')
            ->where('ar_invoice_id', optional($this->arInvoices->first())->id)
            ->whereIn('receipt_type', ['refund', 'updated'])
            ->orderByDesc('id')
            ->value('id');

        return [
            'id' => $this->id,
            'so_number' => $this->so_number,
            'customer' => $this->customer,
            'order_date' => $this->order_date->format('F d, Y'),
            'status' => $this->status,
            'sub_status' => $this->sub_status,
            'total_amount' => $this->total_amount,
            'added_by' => $this->created_by ? $this->created_by->employee : null,
            'sales_rep_id' => $this->sales_rep_id,
            'driver_id' => $this->driver_id,
            'payment_mode' => $this->payment_mode,
            'due_date' => $this->due_date?->format('M d, Y'),
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
            'invoices' => $this->arInvoices,
            'created_at' => $this->created_at->format('M d, Y'),
            'updated_at' => $this->updated_at?->format('M d, Y'),
            'approved_by' => $this->approved_by,
        ];
    }
}
