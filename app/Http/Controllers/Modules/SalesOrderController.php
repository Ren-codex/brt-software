<?php

namespace App\Http\Controllers\Modules;

use App\Models\SalesOrder;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Modules\SalesOrderClass;
// use App\Http\Requests\Libraries\SalesOrderRequest;


class SalesOrderController extends Controller
{
    use HandlesTransaction;

    public $role,$dropdown;

    public function __construct(SalesOrderClass $sales_order, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->sales_order = $sales_order;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->sales_order->lists($request);
            break;
            default:
                return inertia('Modules/SalesOrders/Index'); 
            break;
        }   
    }
}
