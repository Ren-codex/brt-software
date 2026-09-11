<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckMonitoringResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'reference_number' => $this->reference_number,
            'amount_paid' => (float) $this->amount_paid,
            'receipt_date' => $this->receipt_date,
            'check_date' => $this->check_date?->format('Y-m-d'),
            'check_status' => $this->check_status,
            'released_at' => $this->released_at?->format('F d, Y h:i A'),
            'bank_name' => $this->bank_name,
            'confirmed_at' => $this->confirmed_at?->format('F d, Y h:i A'),
            'confirmed_by' => $this->confirmedBy?->username,
            'customer' => $this->customer?->name,
            'sales_rep' => $this->arInvoice?->sales_order?->salesRep?->fullname,
            'ar_invoice_id' => $this->ar_invoice_id,
        ];
    }
}
