<?php

namespace App\Http\Requests\Modules;

use Illuminate\Foundation\Http\FormRequest;

class SalesOrderRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->input('action');

        if($action == 'adjustment'){
            return [
                'type' => 'required|string',
                'reason' => 'required|string',
            ];
        }
        else if($action == 'approve'){
            return [
                'id' => 'required|exists:sales_orders,id',
                'item_ids' => 'nullable|array',
                'item_ids.*' => 'integer|exists:sales_order_items,id',
            ];
        }
        else if($action == 'cancel'){
            return [
                'id' => 'required|exists:sales_orders,id',
            ];
        }
        else{
             $rules = [
                'order_date' => 'required|date',
                'customer_id' => 'required|exists:customers,id',
                'sales_rep_id' => 'nullable|exists:employees,id',
                'driver_id' => 'nullable|exists:employees,id',
                'payment_mode' => 'required|string',
                'due_date' => 'nullable|date|required_if:payment_mode,Credit',
                'location_id' => 'required|exists:list_locations,id',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.price_type' => 'required|string|in:retail,wholesale',
                'items.*.batch_code' => 'required|string|exists:inventory_stocks,batch_code',
                'items.*.discount_per_unit' => 'nullable|numeric|min:0',

            ];

            if ($this->input('is_external')) {
                $rules['location_id'] = 'required|exists:list_locations,id';
            }

            return $rules;
        }


    }
    public function messages()
    {
        return [
            'order_date.required' => 'This field is required',
            'customer_id.required' => 'This field is required',
            'type' => 'This field is required',
            'reason' => 'This field is required',
        ];

    }

}
