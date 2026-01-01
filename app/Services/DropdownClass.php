<?php

namespace App\Services;

use App\Models\ListAcademic;
use Carbon\Carbon;
use App\Models\User;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\ListBrand;
use App\Models\Customer;
use App\Models\ListSupplier;
use App\Models\Product;
use App\Models\ReceivedStock;


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
                $batch_code = $item->receivedItems->first(function ($receivedItem) {
                    return $receivedItem->inventoryStocks->sum('quantity') > 0;
                })?->receivedStock?->batch_code ?? null;
            }
            return [
                'value' => $item->id,
                'name' => ($item->brand ? $item->brand->name : '') . ' ' . ($item->pack_size ?? '') . ' ' . ($item->unit ? $item->unit->name : '') ,
                'batch_code' => $batch_code,
                'available_quantity' => $available_quantity,
                'price' => $item->price,
                'available' => $available_quantity,
            ];
        });
        return  $data;
    }

    public function batch_codes(){
        $data = ReceivedStock::get()->map(function ($item) {
            return [
                'value' => $item->id,
                'code' => $item->batch_code
            ];
        });
        return  $data;
    }


}
