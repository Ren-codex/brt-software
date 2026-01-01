<?php

namespace App\Http\Controllers\Modules;

use App\Models\Receipt;
use App\Models\ArInvoice;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiptController extends Controller
{
    use HandlesTransaction;

    public $dropdown;

    public function __construct(DropdownClass $dropdown){
        $this->dropdown = $dropdown;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return Receipt::with(['arInvoice.salesOrder.customer', 'status'])
                    ->when($request->keyword, function($query) use ($request) {
                        $query->whereHas('arInvoice.salesOrder.customer', function($q) use ($request) {
                            $q->where('name', 'like', '%' . $request->keyword . '%');
                        })->orWhere('receipt_number', 'like', '%' . $request->keyword . '%');
                    })
                    ->orderBy('created_at', 'desc')
                    ->paginate($request->count ?? 10);
            break;
            case 'dashboard':
                return [
                    'total_receipts' => Receipt::count(),
                    'total_amount_collected' => Receipt::sum('amount_paid'),
                ];
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
            $receipt = Receipt::create([
                'ar_invoice_id' => $request->ar_invoice_id,
                'status_id' => $request->status_id,
                'receipt_number' => $this->generateReceiptNumber(),
                'receipt_date' => $request->receipt_date,
                'amount_paid' => $request->amount_paid,
                'payment_mode' => $request->payment_mode,
                'created_by' => auth()->id(),
            ]);

            // Update AR Invoice balance
            $arInvoice = ArInvoice::find($request->ar_invoice_id);
            $arInvoice->amount_paid += $request->amount_paid;
            $arInvoice->balance_due = $arInvoice->amount_balance - $arInvoice->amount_paid;

            // Update status based on balance
            if ($arInvoice->balance_due <= 0) {
                $arInvoice->status_id = 4; // Paid
            } elseif ($arInvoice->amount_paid > 0) {
                $arInvoice->status_id = 3; // Partially Paid
            }

            $arInvoice->save();

            return $receipt;
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function show($id){
        return Receipt::with(['arInvoice.salesOrder.customer', 'arInvoice.salesOrder.items.product', 'status'])->findOrFail($id);
    }

    public function update(Request $request, $id){
        $result = $this->handleTransaction(function () use ($request, $id) {
            $receipt = Receipt::findOrFail($id);
            $oldAmount = $receipt->amount_paid;

            $receipt->update($request->only([
                'ar_invoice_id', 'status_id', 'receipt_date', 'amount_paid', 'payment_mode'
            ]));

            // Update AR Invoice balance
            $arInvoice = ArInvoice::find($receipt->ar_invoice_id);
            $arInvoice->amount_paid = $arInvoice->amount_paid - $oldAmount + $request->amount_paid;
            $arInvoice->balance_due = $arInvoice->amount_balance - $arInvoice->amount_paid;

            // Update status based on balance
            if ($arInvoice->balance_due <= 0) {
                $arInvoice->status_id = 4; // Paid
            } elseif ($arInvoice->amount_paid > 0) {
                $arInvoice->status_id = 3; // Partially Paid
            } else {
                $arInvoice->status_id = 2; // Unpaid
            }

            $arInvoice->save();

            return $receipt;
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
            $receipt = Receipt::findOrFail($id);
            $arInvoice = ArInvoice::find($receipt->ar_invoice_id);

            // Reverse the payment
            $arInvoice->amount_paid -= $receipt->amount_paid;
            $arInvoice->balance_due = $arInvoice->amount_balance - $arInvoice->amount_paid;

            // Update status
            if ($arInvoice->balance_due >= $arInvoice->amount_balance) {
                $arInvoice->status_id = 2; // Unpaid
            } elseif ($arInvoice->amount_paid > 0) {
                $arInvoice->status_id = 3; // Partially Paid
            }

            $arInvoice->save();
            $receipt->delete();

            return $receipt;
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    private function generateReceiptNumber(){
        $year = now()->format('Y');
        $month = now()->format('m');

        $lastReceipt = Receipt::whereYear('created_at', $year)
                             ->whereMonth('created_at', $month)
                             ->orderBy('id', 'desc')
                             ->first();

        $sequence = $lastReceipt ? intval(substr($lastReceipt->receipt_number, -4)) + 1 : 1;

        return 'OR-' . $year . $month . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
