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

    public $sales_order,$dropdown;

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

    public function store(Request $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->sales_order->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }


    public function update(Request $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->sales_order->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);

        
    }

    public function destroy($id){
        $result = $this->handleTransaction(function () use ($id) {
            return $this->sales_order->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
