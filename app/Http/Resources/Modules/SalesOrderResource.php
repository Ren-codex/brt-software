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
            'added_by' => $this->added_by,
            'transferred_to' => $this->transferred_to,
            'transferred_at' => $this->transferred_at,
            'payment_mode' => $this->payment_mode,
            'items' => $this->items,
            'created_at' => $this->created_at->format('F d, Y'),
            'updated_at' => $this->updated_at?->format('F d, Y'),

        ];
    }
}
