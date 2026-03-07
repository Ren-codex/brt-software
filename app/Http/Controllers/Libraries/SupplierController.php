<?php

namespace App\Http\Controllers\Libraries;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\SupplierClass;
use App\Http\Requests\Libraries\SupplierRequest;
use App\Models\ListSupplier;
use App\Http\Resources\Libraries\ListSupplierResource;

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

    public function show($id){
        $supplier = ListSupplier::findOrFail($id);
        return new ListSupplierResource($supplier);
    }

    public function toggleActive(Request $request, $id){
        $supplier = ListSupplier::findOrFail($id);
        $supplier->update(['is_active' => $request->is_active]);
        
        return response()->json([
            'message' => 'Supplier status updated successfully',
            'data' => new ListSupplierResource($supplier)
        ]);
    }

    public function toggleBlacklist(Request $request, $id){
        $supplier = ListSupplier::findOrFail($id);
        $supplier->update(['is_blacklisted' => $request->is_blacklisted]);
        
        return response()->json([
            'message' => 'Supplier blacklist status updated successfully',
            'data' => new ListSupplierResource($supplier)
        ]);
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
