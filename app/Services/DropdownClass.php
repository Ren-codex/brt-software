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


class DropdownClass
{  

    public function roles(){
        $data = ListRole::get()->map(function ($item) {
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

    public function customers(){
        $data = Customer::get()->map(function ($item) {
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


}
