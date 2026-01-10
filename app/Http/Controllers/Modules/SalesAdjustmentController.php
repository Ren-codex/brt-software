<?php

namespace App\Http\Controllers\Modules;

use App\Models\SalesOrder;
use App\Models\SalesReturn;
use Illuminate\Http\Request;
use App\Services\Modules\SalesOrderClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;

class SalesAdjustmentController extends Controller
{
    use HandlesTransaction;

    public $sales_order;

    public function __construct(SalesOrderClass $sales_order){
        $this->sales_order = $sales_order;
    }

    public function store(Request $request, $id){
        $request->validate([
            'type' => 'required|in:Sales Return,Sales Allowance',
            'reason' => 'required|string',
        ]);

        $result = $this->handleTransaction(function () use ($request, $id) {
            $salesOrder = SalesOrder::findOrFail($id);

            // Set sub-status based on type
            if ($request->type == 'Sales Return') {
                $subStatusId = 3; // Sales Returned
            } elseif ($request->type == 'Sales Allowance') {
                $subStatusId = 4; // Allowance Applied
            }

            $salesOrder->update([ 'sub_status_id' => $subStatusId]);

            // Create SalesReturn record
            SalesReturn::create([
                'sales_order_id' => $id,
                'reason' => $request->reason,
            ]);

            return [
                'data' => $salesOrder,
                'message' => 'Sales adjustment applied successfully!',
                'info' => "You've successfully applied the sales adjustment"
            ];
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
