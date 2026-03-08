<?php

namespace App\Http\Controllers\Modules;

use App\Models\Expense;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Modules\ExpenseClass;
use App\Http\Requests\Modules\ExpenseRequest;

class ExpenseController extends Controller
{
    use HandlesTransaction;

    public $expense, $dropdown;

    public function __construct(ExpenseClass $expense, DropdownClass $dropdown)
    {
        $this->dropdown = $dropdown;
        $this->expense = $expense;
    }

    public function index(Request $request)
    {
        switch ($request->option) {
            case 'lists':
                return $this->expense->lists($request);
                break;
            default:
                return inertia('Modules/Expenses/Index', [
                    'dropdowns' => []
                ]);
                break;
        }
    }

    public function store(ExpenseRequest $request)
    {
        $userId = auth()->id();
        $result = $this->handleTransaction(function () use ($request, $userId) {
            return $this->expense->save($request, $userId);
        });

        // For Inertia, return back with success message
        return back()->with([
            'success' => $result['message'],
            'info' => $result['info'],
            'data' => $result['data'] // Optional: if you want to pass data back
        ]);
    }

    public function update(ExpenseRequest $request)
    {
        $result = $this->handleTransaction(function () use ($request) {
            return $this->expense->update($request);
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
            return $this->expense->delete($id);
        });

        // For AJAX delete, return JSON
        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
        ]);
    }

    public function approve($id)
    {
        $result = $this->handleTransaction(function () use ($id) {
            return $this->expense->approve($id);
        });

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'] ?? 'success',
            'data' => $result['data'] ?? null,
        ]);
    }
}
