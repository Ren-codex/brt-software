<?php

namespace App\Http\Controllers;

use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\System\PurchaseOrder\PurchaseOrderClass as PurchaseOrderService;
use App\Http\Requests\PurchaseOrderRequest;
use App\Services\PrintClass;

class PurchaseOrderController extends Controller
{
    use HandlesTransaction;

    public $purchaseOrder, $dropdown, $print;

    public function __construct(PurchaseOrderService $purchaseOrder, DropdownClass $dropdown, PrintClass $print)
    {
        $this->dropdown = $dropdown;
        $this->purchaseOrder = $purchaseOrder;
        $this->print = $print;
    }

    public function index(Request $request)
    {
        switch ($request->option) {
            case 'list':
                return $this->purchaseOrder->list($request);
                break;
            default:
                return inertia('Modules/PurchaseOrders/Index', [
                    'dropdowns' => [
                        'statuses' => $this->dropdown->statuses()
                    ]
                ]);
        }
    }

    public function store(PurchaseOrderRequest $request)
    {
        $result = $this->handleTransaction(function () use ($request) {
            return $this->purchaseOrder->save($request);
        });
        
        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ]);
    }

    public function update(PurchaseOrderRequest $request)
    {
        $result = $this->handleTransaction(function () use ($request) {
            return $this->purchaseOrder->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ]);
    }

    public function updateStatus(Request $request)
    {
        $result = $this->handleTransaction(function () use ($request) {
            return $this->purchaseOrder->updateStatus($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ]);
    }

    public function show($id)
    {
        return inertia('Modules/Inventory/Components/PurchaseOrders/View', [
            'purchase_order_data' => $this->purchaseOrder->view($id),
            'dropdowns' => [
                'suppliers' => $this->dropdown->suppliers(),
                'statuses' => $this->dropdown->statuses(),
                'products' => $this->dropdown->products(),
            ],
        ]);
    }

    public function destroy($id)
    {
        $result = $this->handleTransaction(function () use ($id) {
            return $this->purchaseOrder->delete($id);
        });

        return redirect('/inventory')->with([
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ]);
    }

    public function getNextPoNumber()
    {
        return response()->json(['po_number' => $this->purchaseOrder->generatePoNumber()]);
    }

    public function printPO($id, Request $request)
    {
        return $this->print->print($id, $request);
    }
}
