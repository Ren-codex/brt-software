<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'so_number' => $this->so_number,
            'customer' => $this->customer,
            'order_date' => $this->order_date,
            'status' => $this->status,
            'sub_status' => $this->sub_status,
            'total_amount' => $this->total_amount,
            'added_by' => $this->added_by,
            'sales_rep_id' => $this->sales_rep_id,
            'driver_id' => $this->driver_id,
            'payment_mode' => $this->payment_mode,
            'due_date' => $this->due_date,
            'transferred_to' => $this->transferred_to,
            'transferred_at' => $this->transferred_at,
            'items' => $this->items,
            'invoices' => $this->arInvoices,
            'created_at' => $this->created_at->format('F d, Y'),
            'updated_at' => $this->updated_at?->format('F d, Y'),
            'approved_by' => $this->approved_by,
            // 'approved_at' => $this->approved_at?->format('F d, Y'),

        ];
    }
}
