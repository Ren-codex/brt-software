<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\Controller;
use App\Http\Requests\Libraries\PayrollItemRequest;
use App\Services\DropdownClass;
use App\Services\Libraries\PayrollItemClass;
use App\Traits\HandlesTransaction;
use Illuminate\Http\Request;
use App\Models\ListPayrollItem;

class PayrollItemController extends Controller
{
    use HandlesTransaction;

    public $payrollItem;
    public $dropdown;

    public function __construct(PayrollItemClass $payrollItem, DropdownClass $dropdown)
    {
        $this->payrollItem = $payrollItem;
        $this->dropdown = $dropdown;
    }

    public function index(Request $request)
    {
        switch ($request->option) {
            case 'lists':
                return $this->payrollItem->lists($request);
            default:
                return inertia('Modules/Libraries/PayrollItems/Index');
        }
    }

    public function store(PayrollItemRequest $request)
    {
        $result = $this->handleTransaction(function () use ($request) {
            return $this->payrollItem->save($request);
        });

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ], $result['status'] ? 200 : 400);
    }

    public function update(PayrollItemRequest $request, $id)
    {
        $result = $this->handleTransaction(function () use ($request, $id) {
            return $this->payrollItem->update($request, $id);
        });

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ], $result['status'] ? 200 : 400);
    }

    public function destroy($id)
    {
        $result = $this->handleTransaction(function () use ($id) {
            return $this->payrollItem->delete($id);
        });

        return response()->json([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ], $result['status'] ? 200 : 400);
    }

    public function toggleActive(Request $request, $id)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $item = ListPayrollItem::findOrFail($id);
        $item->is_active = $request->boolean('is_active');
        $item->save();

        return response()->json([
            'status' => true,
            'message' => 'Payroll item status updated successfully',
            'data' => $item,
        ]);
    }
}
