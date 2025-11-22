<?php

namespace App\Http\Controllers\Libraries;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\SupplierClass;
use App\Http\Requests\Libraries\SupplierRequest;

class SupplierController extends Controller
{
    use HandlesTransaction;

    public $supplier,$dropdown;

    public function __construct(SupplierClass $supplier, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->supplier = $supplier;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->supplier->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Suppliers/Index',[
                    'dropdowns' => [
                        'statuses' => $this->dropdown->statuses()
                    ]
                ]); 
            break;
        }   
    }

  
    public function update(SupplierRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->supplier->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function store(SupplierRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->supplier->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }



 
}
