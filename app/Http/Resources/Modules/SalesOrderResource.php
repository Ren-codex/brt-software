<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'return_item_ids' => DB::table('sales_return_items')
                ->join('sales_order_items', 'sales_order_items.id', '=', 'sales_return_items.sales_order_item_id')
                ->where('sales_order_items.sales_order_id', $this->id)
                ->pluck('sales_return_items.sales_order_item_id')
                ->map(fn ($id) => (int) $id)
                ->values(),
            'invoices' => $this->arInvoices,
            'created_at' => $this->created_at->format('M d, Y'),
            'updated_at' => $this->updated_at?->format('M d, Y'),
            'approved_by' => $this->approved_by,
        ];
    }
}
