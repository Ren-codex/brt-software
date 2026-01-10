<?php

namespace App\Http\Resources\Libraries;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'receipt_date' => $this->receipt_date,
            'amount_paid' => $this->amount_paid,
            'balance_due' => $this->balance_due,
            'payment_mode' => $this->payment_mode,
            'status_id' => $this->status_id,
            'customer_id' => $this->customer_id,
            'customer' => $this->customer,
            'ar_invoice_id' => $this->ar_invoice_id,
            'created_at' => Carbon::parse($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at,
        ];
    }
}
