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
    use \App\Http\Controllers\Concerns\RequiresSupervisorAuthorization;

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
            case 'detail':
                return response()->json(['data' => $this->inventoryStock->view($request->id)]);
                break;
            case 'consolidation':
                return response()->json($this->inventoryStock->consolidation());
                break;
            default:
                return inertia('Modules/Inventory/Index');
                break;
        }
    }

    public function show($id)
    {
        return redirect("/inventory?tab=inventoryStocks&stock_id={$id}");
    }

    public function update(Request $request, $id)
    {
        // Changing a price sets what every future sale charges, so it is
        // authorised by a supervisor at the keyboard before it is applied.
        $this->requireSupervisor($request, 'inventory.price_change', InventoryStocks::class, (int) $id);

        $result = $this->handleTransaction(function () use ($request) {
            return $this->inventoryStock->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function settings(Request $request, $id)
    {
        $result = $this->handleTransaction(function () use ($request, $id) {
            return $this->inventoryStock->updateSettings($request, $id);
        });

        return back()->with([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
        ]);
    }
}
