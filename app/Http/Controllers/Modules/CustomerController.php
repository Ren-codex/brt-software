<?php

namespace App\Http\Controllers\Modules;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Modules\SalesOrderClass;

class CustomerController extends Controller
{
     use HandlesTransaction;

    public $role,$dropdown;

    public function __construct(CustomerClass $sales_order, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->customer = $customer;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->customer->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Customer/Index'); 
            break;
        }   
    }
}
