<?php

namespace App\Http\Controllers\Libraries;

use App\Models\Salary;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Libraries\SalaryClass;
use App\Http\Requests\Libraries\SalaryRequest;

class SalaryController extends Controller
{
     use HandlesTransaction;

    public $salary,$dropdown;

    public function __construct(SalaryClass $salary, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->salary = $salary;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->salary->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Salaries/Index'); 
            break;
        }
    }

    public function store(Request $request){
        dd($request->all());
        $result = $this->handleTransaction(function () use ($request) {
            return $this->salary->save($request);
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
            return $this->salary->update($request);
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
            return $this->salary->toggleActive($request);
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
            return $this->salary->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
