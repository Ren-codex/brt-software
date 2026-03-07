<?php

namespace App\Http\Controllers\Modules;

use App\Models\SalesOrder;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Services\PrintClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Modules\SalesOrderClass;
use App\Http\Requests\Modules\SalesOrderRequest;


class SalesOrderExternalController extends Controller
{
    use HandlesTransaction;

    public $sales_order,$dropdown , $print;

    public function __construct(SalesOrderClass $sales_order, DropdownClass $dropdown , PrintClass $print){
        $this->dropdown = $dropdown;
        $this->sales_order = $sales_order;
        $this->print = $print;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                $request->merge(['is_external' => true]);
                return $this->sales_order->lists($request);
            break;
            case 'dashboard':
                return $this->sales_order->dashboard();
            break;
            case 'stock':
                return $this->sales_order->stockAvailability();
            break;
            default:
                return inertia('Modules/SalesExternal/Index', [
                    'dropdowns' => [
                        'customers' => $this->dropdown->customers(),
                        'brands' => $this->dropdown->brands(),
                        'products' => $this->dropdown->products(),
                        'batch_codes' => $this->dropdown->batch_codes(),
                        'sales_reps' => $this->dropdown->sales_reps(),
                        'drivers' => $this->dropdown->drivers(),
                        'locations' => $this->dropdown->locations(),
                    ],
                    'isExternal' => true,

                ]);
            break;
        }
    }

    public function store(SalesOrderRequest $request){
        $request->merge(['is_external' => true]);
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


    public function update(SalesOrderRequest $request, $id){

        $result = $this->handleTransaction(function () use ($request) {
                switch($request->action){
                    case 'update':
                        $request->merge(['is_external' => true]);
                        return $this->sales_order->update($request->id);
                    break;
                    case 'approve':
                        return $this->sales_order->approve($request->id);
                    break;
                    case 'cancel':
                        return $this->sales_order->cancel($request->id);
                    break;
                    case 'adjustment':
                        return $this->sales_order->adjustment($request);
                    break;
                }
            });



        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);

    }


    public function show($id , Request $request){
        return $this->print->print($id, $request);
    }

    public function destroy($id){
        $result = $this->handleTransaction(function () use ($id) {
            return $this->sales_order->cancel($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
