<?php

namespace App\Http\Controllers\Libraries;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\LocationClass;
use App\Http\Requests\Libraries\LocationRequest;

class LocationController extends Controller
{
    use HandlesTransaction;

    public $location,$dropdown;

    public function __construct(LocationClass $location, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->location = $location;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->location->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Locations/Index');
            break;
        }
    }


    public function update(LocationRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->location->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function store(LocationRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->location->save($request);
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
            return $this->location->delete($id);
        });

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ], $result['status'] ? 200 : 400);
    }

}
