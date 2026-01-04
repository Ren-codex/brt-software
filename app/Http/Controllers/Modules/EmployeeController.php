<?php

namespace App\Http\Controllers\Modules;

use App\Models\Employee;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Modules\EmployeeClass;
use App\Http\Requests\Modules\EmployeeRequest;

class EmployeeController extends Controller
{
     use HandlesTransaction;

    public $employee,$dropdown;

    public function __construct(EmployeeClass $employee, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->employee = $employee;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->employee->lists($request);
            break;
            default:
                return inertia('Modules/Employees/Index', [
                    'dropdowns' => [
                        'positions' => $this->dropdown->positions()
                    ]
                ]);
            break;
        }
    }

    public function store(EmployeeRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->employee->save($request);
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
            return $this->employee->update($request);
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
            return $this->employee->toggleActive($request);
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
            return $this->employee->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
