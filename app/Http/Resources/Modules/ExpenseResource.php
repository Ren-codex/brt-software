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
            'id' => $this->id,
            'expense_type' => $this->expense_type,
            'amount' => $this->amount,
            'expense_date' => $this->expense_date,
            'description' => $this->description,
            'status' => $this->status,
            'status_info' => $this->whenLoaded('status_info', function() {
                return [
                    'name' => $this->status_info->name,
                    'slug' => $this->status_info->slug,
                    'text_color' => $this->status_info->text_color,
                    'bg_color' => $this->status_info->bg_color,
                ];
            }),
            'added_by' => $this->whenLoaded('added_by', function() {
                return [
                    'id' => $this->added_by->id,
                    'name' => $this->added_by->name,
                    'avatar' => $this->added_by->avatar ?? null,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
