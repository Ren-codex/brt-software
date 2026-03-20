<?php

namespace App\Services;

use App\Models\ListAcademic;
use Carbon\Carbon;
use App\Models\User;
use App\Models\ListRole;
use App\Models\ListPosition;
use App\Models\ListSalary;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\ListBrand;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ListSupplier;
use App\Models\Product;
use App\Models\InventoryStocks;
use App\Models\PayrollSetting;
use App\Models\PayrollTemplate;
use App\Models\ListLocation;
use App\Models\ListPayrollItem;

class DropdownClass
{  

    public function roles(){
        $data = ListRole::where('is_active', 1)->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->name
            ];
        });
        return  $data;
    }

    public function statuses(){
        $data = ListStatus::get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->name
            ];
        });
        return  $data;
    }

    public function units(){
        $data = ListUnit::get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->name
            ];
        });
      
        return  $data;
    }

    
    public function brands(){
        $data = ListBrand::get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->name
            ];
        });
      
        return  $data;
    }

    public function customers(){
        $data = Customer::get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->name,
                'address' => $item->address,
                'contact_number' => $item->contact_number,
                'email' => $item->email,
            ];
        });
        return  $data;
    }

    public function positions(){
        $data = ListPosition::where('is_active',1)->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'title' => $item->title,
            ];
        });
        return  $data;
    }

    public function salaries(){
        $data = ListSalary::where('is_active',1)->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'amount' => $item->amount,   
            ];
        });
        return  $data;
    }
        
    public function suppliers(){
        $data = ListSupplier::get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->name
            ];
        });
        return  $data;
    }

    public function products(){
        $data = Product::with(['brand', 'unit'])->get()->map(function ($item) {
            $batchStocks = InventoryStocks::query()
                ->with('receivedItem')
                ->whereHas('receivedItem', function ($query) use ($item) {
                    $query->where('product_id', $item->id);
                })
                ->where('quantity', '>', 0)
                ->orderBy('created_at')
                ->get();

            $batchStocksByCode = $batchStocks->groupBy('batch_code');

            $available_quantity = (int) $batchStocksByCode->sum(function ($stocks) {
                return $stocks->sum('quantity');
            });

            $batch_stocks = $batchStocksByCode->map(function ($stocks, $batchCode) {
                $firstStock = $stocks->sortBy('created_at')->first();

                return [
                    'batch_code' => $batchCode,
                    'quantity' => (int) $stocks->sum('quantity'),
                    'unit_cost' => $firstStock?->receivedItem?->unit_cost,
                    'retail_price' => $firstStock?->retail_price,
                    'wholesale_price' => $firstStock?->wholesale_price,
                ];
            })->values()->sortBy('batch_code')->values()->all();

            $batch_code = null;
            $batch_available = 0;
            $firstInventoryStock = null;
            if ($available_quantity > 0 && count($batch_stocks) > 0) {
                $batch_code = $batch_stocks[0]['batch_code'];
                $batch_available = $batch_stocks[0]['quantity'];
                $firstInventoryStock = InventoryStocks::query()
                    ->where('batch_code', $batch_code)
                    ->whereHas('receivedItem', function ($query) use ($item) {
                        $query->where('product_id', $item->id);
                    })
                    ->where('quantity', '>', 0)
                    ->orderBy('created_at')
                    ->first();
            }
            $retail_price = null;
            $wholesale_price = null;
            if ($firstInventoryStock) {
                $retail_price = $firstInventoryStock->retail_price;
                $wholesale_price = $firstInventoryStock->wholesale_price;
            }
            return [
                'value' => $item->id,
                'name' => ($item->brand ? $item->brand->name : '') . ' ' . ($item->pack_size ?? '') . ' ' . ($item->unit ? $item->unit->name : '') ,
                'batch_code' => $batch_code,
                'batch_available' => $batch_available,
                'batch_stocks' => $batch_stocks,
                'available_quantity' => $available_quantity,
                'retail_price' => $retail_price,
                'wholesale_price' => $wholesale_price,
                'price' => $retail_price, // default to retail for backward compatibility
                'available' => $available_quantity,
            ];
        });
        return  $data;
    }

    public function batch_codes(){
        $data = InventoryStocks::get()->map(function ($item) {
            return [
                'value' => $item->id,
                'code' => $item->batch_code
            ];
        });
        return  $data;
    }

    public function employees(){
        $data = Employee::with('position')->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->lastname . ', ' . $item->firstname . ' ' . ($item->middlename ? strtoupper($item->middlename[0]) . '.' : ''),
                'position_name' => $item->position ? $item->position->title : null,
                'basic_salary' => $item->position ? $item->position->rate_per_day : null,
            ];
        });
        return  $data;
    }

    public function sales_reps(){
        $data = Employee::where('position_id', ListPosition::getID('Sales Rep'))->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->fullname,
            ];
        });
        return  $data;
    }

    public function drivers(){
        $data = Employee::where('position_id', ListPosition::getID('Driver'))->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->fullname,
            ];
        });
        return  $data;
    }

    public function locations(){
        $data = ListLocation::where('is_active', 1)->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->name
            ];
        });
        return  $data;
    }

    public function sales_statuses(){
        // Return all statuses so tab filters show the full list
        $data = ListStatus::orderBy('name')->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug
            ];
        });
        return  $data;
    }


    public function payroll_settings(){
        $data = PayrollSetting::where('is_active',1)->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'slug' => $item->slug,
                'field' => $item->field,
                'formula' => $item->formula,
                'value' => $item->value,
            ];
        });
        return  $data;
    }

    public function payroll_templates(){
        $data = PayrollTemplate::where('is_active',1)->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->name,
                'employees' => $item->employees->map(function ($emp) {
                    return [
                        'id' => $emp->id,
                        'name' => $emp->lastname . ', ' . $emp->firstname . ' ' . ($emp->middlename ? strtoupper($emp->middlename[0]) . '.' : ''),
                        'basic_salary' => $emp->position ? $emp->position->rate_per_day : null,
                    ];
                }),
            ];
        });
        return  $data;
    }

    public function payroll_items(){
        $data = ListPayrollItem::where('is_active',1)->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
            ];
        });
        return  $data;
    }
}
