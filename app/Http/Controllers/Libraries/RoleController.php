<?php

namespace App\Http\Controllers\Libraries;

use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\RoleClass;
use App\Http\Requests\Libraries\RoleRequest;

class RoleController extends Controller
{
    use HandlesTransaction;

    public $role,$dropdown;

    public function __construct(RoleClass $role, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->role = $role;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->role->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Roles/Index'); 
            break;
        }   
    }

  
    public function update(RoleRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->role->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function store(RoleRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->role->save($request);
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
            return $this->role->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
