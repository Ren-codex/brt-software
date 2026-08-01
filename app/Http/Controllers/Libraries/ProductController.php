<?php

namespace App\Http\Controllers\Libraries;

use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\ProductClass;
use App\Http\Requests\Libraries\ProductRequest;
use App\Models\Product;
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
            case 'next-code':
                $prefix = strtoupper($request->prefix ?? '');
                $last   = Product::where('code', 'LIKE', $prefix . '%')
                              ->orderByRaw('CAST(SUBSTRING(code, ?) AS UNSIGNED) DESC', [strlen($prefix) + 1])
                              ->value('code');
                $num  = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
                return response()->json(['code' => $prefix . str_pad($num, 3, '0', STR_PAD_LEFT)]);
            break;
            default:
                return inertia('Modules/Libraries/Products/Index',[
                    'dropdowns' => [
                        'brands'     => $this->dropdown->brands(),
                        'units'      => $this->dropdown->units(),
                        'packagings' => $this->dropdown->packagings(),
                    ]
                ]); 
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

    public function store(Request $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->product->save($request);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data'    => $result['data'],
                'message' => $result['message'],
                'info'    => $result['info'],
                'status'  => $result['status'] ?? true,
            ]);
        }

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

    // New method for toggling active status
    public function toggleActive(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $product->is_active = $request->input('is_active');
        $product->save();

        return response()->json([
            'status' => true,
            'message' => 'Product active status updated successfully',
            'data' => $product
        ]);
    }
}
