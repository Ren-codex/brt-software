<?php

namespace App\Http\Controllers\Libraries;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\StatusClass;
use App\Http\Requests\Libraries\StatusRequest;

class StatusController extends Controller
{
    use HandlesTransaction;

    public $status,$dropdown;

    public function __construct(StatusClass $status, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->status = $status;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->status->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Statuses/Index'); 
            break;
        }   
    }

  
    public function update(StatusRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->status->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function store(StatusRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->status->save($request);
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
            return $this->status->delete($id);
        });

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ], $result['status'] ? 200 : 400);
    }
 
}
