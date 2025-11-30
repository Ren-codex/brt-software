<?php

namespace App\Http\Controllers;

use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\ProductClass;
use App\Services\DropdownClass;

class InventoryManagementController extends Controller
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
                return inertia('Modules/Inventory/InventoryManagement', [
                    'dropdowns' => [
                        'units' => $this->dropdown->units(),
                        'suppliers' => $this->dropdown->suppliers(),
                        'products' => $this->dropdown->products(),
                    ]
                ]);
            break;
        }
    }
}
