<?php

namespace App\Http\Controllers\Libraries;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\BrandClass;
use App\Http\Requests\Libraries\BrandRequest;

class BrandController extends Controller
{
    use HandlesTransaction;

    public $brand,$dropdown;

    public function __construct(BrandClass $brand, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->brand = $brand;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->brand->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Brands/Index'); 
            break;
        }   
    }

  
    public function update(BrandRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->brand->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function store(BrandRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->brand->save($request);
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
            return $this->brand->delete($id);
        });

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ], $result['status'] ? 200 : 400);
    }
 
}