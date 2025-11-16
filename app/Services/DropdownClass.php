<?php

namespace App\Services;

use App\Models\ListAcademic;
use Carbon\Carbon;
use App\Models\User;
use App\Models\ListRole;


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

}
