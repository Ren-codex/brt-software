<?php

namespace App\Http\Controllers\System;

use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Modules\SalesOrderClass;
// use App\Http\Requests\System\UserRequest;

class SalesOrderController extends Controller
{
    use HandlesTransaction;

    public $sales_order,$dropdown;

    public function __construct(SalesOrderClass $sales_order, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->sales_order = $sales_order;
    }

    public function index(Request $request)   
    {
        switch($request->option){
            case 'list':
                return $this->sales_order->list($request);
            break;
            default:
                return inertia('Modules/Sales-Orders/Index',[
                    'dropdowns' => [
                        'roles' => $this->dropdown->roles(),
                        // 'products' => $this->dropdown->products()
                    ]
                ]); 
            break;
            }
    }

    public function store(Request $request)
    {
        //
    }

    public function show(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
