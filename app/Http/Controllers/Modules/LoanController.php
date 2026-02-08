<?php

namespace App\Http\Controllers\Modules;

use App\Models\Loan;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Modules\LoanClass;
use App\Http\Requests\Modules\LoanRequest;

class LoanController extends Controller
{
    use HandlesTransaction;

    public $loan, $dropdown;

    public function __construct(LoanClass $loan, DropdownClass $dropdown)
    {
        $this->dropdown = $dropdown;
        $this->loan = $loan;
    }

    public function index(Request $request)
    {
        switch ($request->option) {
            case 'lists':
                return $this->loan->lists($request);
                break;
            default:
                return inertia('Modules/Loans/Index', [
                    'dropdowns' => [
                        'employees' => $this->dropdown->employees()
                    ]
                ]);
                break;
        }
    }

    public function store(LoanRequest $request)
    {
        $userId = auth()->id();
        $result = $this->handleTransaction(function () use ($request, $userId) {
            return $this->loan->save($request, $userId);
        });

        // For Inertia, return back with success message
        return back()->with([
            'success' => $result['message'],
            'info' => $result['info'],
            'data' => $result['data'] // Optional: if you want to pass data back
        ]);
    }

    public function update(LoanRequest $request)
    {
        $result = $this->handleTransaction(function () use ($request) {
            return $this->loan->update($request);
        });

        // For Inertia, return back with success message
        return back()->with([
            'success' => $result['message'],
            'info' => $result['info'],
        ]);
    }

    public function destroy($id)
    {
        $result = $this->handleTransaction(function () use ($id) {
            return $this->loan->delete($id);
        });

        // For AJAX delete, return JSON
        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
        ]);
    }
}