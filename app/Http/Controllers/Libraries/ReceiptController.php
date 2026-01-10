<?php

namespace App\Http\Controllers\Libraries;

use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\ReceiptClass;
use App\Http\Requests\Libraries\ReceiptRequest;
use App\Services\PrintClass;

class ReceiptController extends Controller
{
    use HandlesTransaction;

    public $receipt,$dropdown, $print;

    public function __construct(ReceiptClass $receipt, DropdownClass $dropdown, PrintClass $print){
        $this->dropdown = $dropdown;
        $this->receipt = $receipt;
        $this->print = $print;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->receipt->lists($request);
            break;
            case 'dashboard':
                return $this->getDashboardMetrics();
            break;
            default:
                return inertia('Modules/Libraries/Receipts/Index');
            break;
        }
    }

    private function getDashboardMetrics()
    {
        $totalReceipts = \App\Models\Receipt::count();
        $totalAmountCollected = \App\Models\Receipt::sum('amount_paid');

        return response()->json([
            'total_receipts' => $totalReceipts,
            'total_amount_collected' => $totalAmountCollected
        ]);
    }

    public function print($id, Request $request)
    {
        return $this->print->print($id, $request);
    }
}
