<?php

namespace App\Http\Controllers;

use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Modules\InventoryStockClass as InventoryStockService;
use App\Http\Requests\InventoryStockRequest;
use App\Models\InventoryStocks;

class InventoryStockController extends Controller
{
    use HandlesTransaction;

    public $inventoryStock;

    public function __construct(InventoryStockService $inventoryStock)
    {
        $this->inventoryStock = $inventoryStock;
    }

    public function index(Request $request)
    {
        switch ($request->option) {
            case 'lists':
                return $this->inventoryStock->lists($request);
                break;
            default:
                return inertia('Modules/Inventory/Index');
                break;
        }
    }

    public function show($id)
    {
        return inertia('Modules/Inventory/Components/InventoryStocks/View', [
            'inventory_stock_data' => $this->inventoryStock->view($id)
        ]);
    }
}
