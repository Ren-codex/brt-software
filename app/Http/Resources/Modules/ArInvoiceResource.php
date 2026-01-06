<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_order_id' => $this->sales_order_id,
            'status_id' => $this->status_id,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date->format('F d, Y'),
            'amount_paid' => (float) $this->amount_paid,
            'balance_due' => (float) $this->balance_due,
            'total_discount' => (float) $this->total_discount,
            'created_by' => $this->created_by,
            'sales_order' => $this->sales_order,
            'status' => $this->status,
            'created_at' => $this->created_at->format('F d, Y'),
            'updated_at' => $this->updated_at?->format('F d, Y'),
        ];
    }
}
