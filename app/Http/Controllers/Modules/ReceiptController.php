<?php

namespace App\Http\Controllers\Modules;

use App\Models\Receipt;
use App\Models\ArInvoice;
use App\Models\ListStatus;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Services\Libraries\ReceiptClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\Libraries\ReceiptResource;
use App\Services\PrintClass;
use App\Services\Modules\ArInvoiceClass;

class ReceiptController extends Controller
{
    use HandlesTransaction;
    use \App\Traits\AuthorizesPermission;
    public $receipt,$dropdown, $print;

    public function __construct(ReceiptClass $receipt, DropdownClass $dropdown, PrintClass $print, private ArInvoiceClass $arInvoice){
        $this->dropdown = $dropdown;
        $this->receipt = $receipt;
        $this->print = $print;
    }


    public function index(Request $request){
        $this->authorizePermission('sales', 'receipts', 'view');

        switch($request->option){
            case 'lists':
                return $this->receipt->lists($request);
            break;
            case 'dashboard':
                return $this->receipt->dashboard();
            break;
            default:
                return inertia('Modules/Sales/Index', [
                    'dropdowns' => [
                        'customers' => $this->dropdown->customers(),
                        'statuses' => $this->dropdown->statuses(),
                    ]
                ]);
            break;
        }
    }

    public function store(Request $request){
        $this->authorizePermission('sales', 'receipts', 'encoder');

        $result = $this->handleTransaction(function () use ($request) {
            return $this->receipt->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function show($id, Request $request)
    {
        $this->authorizePermission('sales', 'receipts', 'view');

        if ($request->option === 'detail') {
            return new ReceiptResource($this->receipt->show($id));
        }
        return $this->print->print($id, $request);
    }


    public function update(Request $request, $id){
        $this->authorizePermission('sales', 'receipts', 'encoder');

        $request->merge(['id' => $id]);
        $result = $this->handleTransaction(function () use ($request) {
            return $this->receipt->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function turnOver($id, Request $request)
    {
        $this->authorizePermission('sales', 'receipts', 'encoder');

        $request->validate([
            'held_by_employee_id' => 'required|exists:employees,id',
        ]);

        $result = $this->handleTransaction(function () use ($id, $request) {
            return app(\App\Services\Modules\RemittanceClass::class)
                ->turnOver($id, $request->input('held_by_employee_id'));
        });

        if (! ($result['status'] ?? false)) {
            $message = $result['info'] ?? 'Unable to record this handover.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'status' => false], 422);
            }

            return back()->withErrors(['held_by_employee_id' => $message]);
        }

        // The panel that calls this is a plain table, not an Inertia page, so it
        // asks for JSON and refreshes itself.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $result['message'],
                'info' => $result['info'],
                'status' => true,
            ]);
        }

        return back()->with([
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function confirmCheck($id, Request $request)
    {
        $this->authorizePermission('sales', 'receipts', 'encoder');

        $result = $this->handleTransaction(function () use ($id, $request) {
            return $this->arInvoice->confirmCheck($id, $request->input('bank_name'), $request->input('check_date'));
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $result['data'],
                'message' => $result['message'],
                'info' => $result['info'],
                'status' => $result['status'],
            ]);
        }

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function destroy($id){
        $this->authorizePermission('sales', 'receipts', 'admin');

        $result = $this->handleTransaction(function () use ($id) {
            return $this->receipt->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }


}
