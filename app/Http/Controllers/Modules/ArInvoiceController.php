<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\ArInvoice;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\PrintClass;
use App\Services\Modules\ArInvoiceClass;
use App\Http\Requests\Modules\PaymentRequest;
use App\Traits\HandlesTransaction;

class ArInvoiceController extends Controller
{
    use HandlesTransaction;

    public $print, $invoice ;

    public function __construct(PrintClass $print , ArInvoiceClass $invoice)
    {
        $this->print = $print;
        $this->invoice = $invoice;
    }
    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->invoice->lists($request);
            break;
            case 'dashboard':
                return $this->invoice->dashboard();
            break;
            case 'stock':
                return $this->invoice->stockAvailability();
            break;
            case 'print':
                return $this->invoice->print($request);
            break;
            default:
                return inertia('Modules/Sales/Components/ARInvoices/Index', [
                    'dropdowns' => [

                    ]
                ]);
            break;
        }
    }

    public function update(PaymentRequest $request, $id)
    {
        $result = $this->handleTransaction(function () use ($request, $id) {
            switch($request->option){
                case 'payment':
                    return $this->invoice->payment($request, $id);
                break;
            }
        });


        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }


    
    public function show($id , Request $request){
        dd($request->all());
        return $this->print->print($id, $request);
    }



   
}
