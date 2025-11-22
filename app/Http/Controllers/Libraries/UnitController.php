<?php

namespace App\Http\Controllers\Libraries;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\UnitClass;
use App\Http\Requests\Libraries\UnitRequest;

class UnitController extends Controller
{
    use HandlesTransaction;

    public $unit,$dropdown;

    public function __construct(UnitClass $unit, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->unit = $unit;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->unit->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Units/Index'); 
            break;
        }   
    }

  
    public function update(UnitRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->unit->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function store(UnitRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->unit->save($request);
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
            return $this->unit->delete($id);
        });

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ], $result['status'] ? 200 : 400);
    }
}
