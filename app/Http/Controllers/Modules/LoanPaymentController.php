<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\LoanPaymentRequest;
use App\Services\Modules\LoanPaymentClass;
use App\Traits\HandlesTransaction;
use Illuminate\Http\Request;

class LoanPaymentController extends Controller
{
    use HandlesTransaction;

    public $loanPayment;

    public function __construct(LoanPaymentClass $loanPayment)
    {
        $this->loanPayment = $loanPayment;
    }

    public function index(Request $request)
    {
        return $this->loanPayment->lists($request);
    }

    public function store(LoanPaymentRequest $request)
    {
        $result = $this->handleTransaction(function () use ($request) {
            return $this->loanPayment->save($request);
        });

        return response()->json($result);
    }

    public function update(LoanPaymentRequest $request, $id)
    {
        $result = $this->handleTransaction(function () use ($request, $id) {
            return $this->loanPayment->update($request, $id);
        });

        return response()->json($result);
    }

    public function destroy($id)
    {
        $result = $this->handleTransaction(function () use ($id) {
            return $this->loanPayment->delete($id);
        });

        return response()->json($result);
    }
}
