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
        $data = Product::with(['brand', 'unit', 'receivedItems.inventoryStocks', 'receivedItems.receivedStock'])->get()->map(function ($item) {
            $available_quantity = $item->receivedItems->sum(function ($receivedItem) {
                return $receivedItem->inventoryStocks->sum('quantity');
            });
            $batch_code = null;
            if ($available_quantity > 0) {
                $firstReceivedItemWithStock = $item->receivedItems->first(function ($receivedItem) {
                    return $receivedItem->inventoryStocks->sum('quantity') > 0;
                });
                if ($firstReceivedItemWithStock) {
                    $firstInventoryStock = $firstReceivedItemWithStock->inventoryStocks->first();
                    if ($firstInventoryStock) {
                        $batch_code = $firstInventoryStock->batch_code;
                    }
                }
            }
            $retail_price = $item->price; // default
            $wholesale_price = $item->price; // default
            if ($available_quantity > 0) {
                $firstReceivedItemWithStock = $item->receivedItems->first(function ($receivedItem) {
                    return $receivedItem->inventoryStocks->sum('quantity') > 0;
                });
                if ($firstReceivedItemWithStock) {
                    $firstInventoryStock = $firstReceivedItemWithStock->inventoryStocks->first();
                    if ($firstInventoryStock) {
                        if ($firstInventoryStock->retail_price) {
                            $retail_price = $firstInventoryStock->retail_price;
                        }
                        if ($firstInventoryStock->wholesale_price) {
                            $wholesale_price = $firstInventoryStock->wholesale_price;
                        }
                    }
                }
            }
            return [
                'value' => $item->id,
                'name' => ($item->brand ? $item->brand->name : '') . ' ' . ($item->pack_size ?? '') . ' ' . ($item->unit ? $item->unit->name : '') ,
                'batch_code' => $batch_code,
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
        $data = Employee::where('position_id', 2)->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->fullname,
            ];
        });
        return  $data;
    }

    public function drivers(){
        $data = Employee::where('position_id', 3)->get()->map(function ($item) {
            return [
                'value' => $item->id,
                'name' => $item->fullname,
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
}
