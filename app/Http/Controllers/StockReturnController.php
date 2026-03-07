<?php

namespace App\Http\Controllers;

use App\Services\System\PurchaseOrder\StockReturnClass as StockReturnService;
use Illuminate\Http\Request;

class StockReturnController extends Controller
{
    protected $stockReturn;

    public function __construct(StockReturnService $stockReturn)
    {
        $this->stockReturn = $stockReturn;
    }

    public function index(Request $request)
    {
        return $this->stockReturn->list($request);
    }

    public function store(Request $request)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.po_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
            'items.*.status_id' => ['nullable', 'integer', 'exists:list_statuses,id'],
        ]);

        $result = $this->stockReturn->store($request);

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ], 201);
    }

    public function show($id)
    {
        return $this->stockReturn->view($id);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'in:approved,disapproved'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->stockReturn->approve($request, $id);

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ]);
    }
}
