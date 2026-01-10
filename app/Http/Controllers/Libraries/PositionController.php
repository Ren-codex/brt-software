<?php

namespace App\Http\Controllers\Libraries;

use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\PositionClass;
use App\Http\Requests\Libraries\PositionRequest;

class PositionController extends Controller
{
    use HandlesTransaction;

    public $position,$dropdown;

    public function __construct(PositionClass $position, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->position = $position;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->position->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Positions/Index'); 
            break;
        }   
    }

  
    public function update(PositionRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->position->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function store(PositionRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->position->save($request);
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
            return $this->position->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
