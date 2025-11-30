<?php

namespace App\Http\Controllers\Modules;

use App\Models\SalesOrder;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Modules\SalesOrderClass;
use App\Http\Requests\Modules\SalesOrderRequest;


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
                return inertia('Modules/Sales/Index', [
                    'dropdowns' => [
                        'customers' => $this->dropdown->customers(),
                        'brands' => $this->dropdown->brands(),
                        'units' => $this->dropdown->units()
                    ]
                ]);
            break;
        }   
    }

    public function store(SalesOrderRequest $request){
        return $this->handleTransaction(function () use ($request) {
            return $this->sales_order->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
