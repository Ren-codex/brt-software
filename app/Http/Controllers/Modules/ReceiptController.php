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


class ReceiptController extends Controller
{
    use HandlesTransaction;

    public $dropdown, $receipt;

    public function __construct(DropdownClass $dropdown, ReceiptClass $receipt){
        $this->dropdown = $dropdown;
        $this->receipt = $receipt;
    }

    public function index(Request $request){
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
                        'ar_invoices' => $this->dropdown->arInvoices(),
                    ]
                ]);
            break;
        }
    }

    public function store(Request $request){
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

    public function show($id){
        return $this->receipt->show($id);
    }

    public function update(Request $request, $id){
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

    public function destroy($id){
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
