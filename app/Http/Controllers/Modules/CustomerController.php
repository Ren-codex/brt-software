<?php

namespace App\Http\Controllers\Modules;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Modules\CustomerClass;
use App\Http\Requests\Modules\CustomerRequest;

class CustomerController extends Controller
{
     use HandlesTransaction;

    public $customer,$dropdown;

    public function __construct(CustomerClass $customer, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->customer = $customer;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->customer->lists($request);
            break;
            default:
                return inertia('Modules/Customers/Index', [
                    'dropdowns' => [
                        'statuses' => $this->dropdown->statuses()
                    ]
                ]);
            break;
        }
    }

    public function store(CustomerRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->customer->save($request);
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
            return $this->customer->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function toggleActive(Request $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->customer->toggleActive($request);
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
            return $this->customer->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
