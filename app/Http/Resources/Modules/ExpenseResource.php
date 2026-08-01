<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'fund_id'                  => $this->fund_id,
            'fund_name'                => optional($this->fund)->name,
            'replenishment_request_id' => $this->replenishment_request_id,
            'expense_type'             => $this->expense_type,
            'amount'                   => $this->amount,
            'expense_date'             => $this->expense_date,
            'description'              => $this->description,
            'receipt_path'             => $this->receipt_path,
            'status'                   => $this->status,
            'added_by'                 => $this->whenLoaded('added_by', fn() => [
                'id'     => $this->added_by->id,
                'name'   => $this->added_by->name,
                'avatar' => $this->added_by->avatar ?? null,
            ]),
            'created_at'               => $this->created_at,
        ];
    }
}
