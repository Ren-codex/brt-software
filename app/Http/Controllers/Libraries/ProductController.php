<?php

namespace App\Http\Controllers\Libraries;

use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\ProductClass;
use App\Http\Requests\Libraries\ProductRequest;
use App\Services\DropdownClass;

class ProductController extends Controller
{
    use HandlesTransaction;

    public $product,$dropdown;

    public function __construct(ProductClass $product, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->product = $product;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->product->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Products/Index');
            break;
        }
    }

    public function update(ProductRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->product->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ]);
    }

    public function store(ProductRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->product->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ]);
    }

    public function destroy($id){
        $result = $this->handleTransaction(function () use ($id) {
            return $this->product->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
