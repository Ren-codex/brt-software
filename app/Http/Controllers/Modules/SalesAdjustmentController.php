<?php

namespace App\Http\Controllers\Modules;

use App\Models\SalesOrder;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Http\Controllers\Controller;
use App\Services\Modules\SalesOrderClass;
use App\Traits\HandlesTransaction;


class SalesAdjustmentController extends Controller
{
    use HandlesTransaction;
    
    public $sales_order, $dropdown;

    public function __construct(SalesOrderClass $sales_order, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->sales_order = $sales_order;
    }

    public function store(Request $request, $id){
        $result = $this->handleTransaction(function () use ($request, $id) {
            return $this->sales_order->adjustment($request, $id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
