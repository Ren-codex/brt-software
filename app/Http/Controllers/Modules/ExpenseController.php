<?php

namespace App\Http\Controllers\Modules;

use App\Models\Expense;
use App\Models\PettyCashFund;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Services\Modules\ExpenseClass;
use App\Http\Requests\Modules\ExpenseRequest;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    use HandlesTransaction;

    public function __construct(
        protected ExpenseClass $expense,
        protected DropdownClass $dropdown
    ) {}

    public function index(Request $request)
    {
        switch ($request->option) {
            case 'lists':
                return $this->expense->lists($request);
            case 'budgets':
                return $this->expense->getBudgets($request);
            case 'funds':
                return response()->json($this->fundsWithBudget());
            case 'pending':
                $fundId = $request->fund_id;
                $rows = \App\Models\Expense::where('fund_id', $fundId)
                    ->whereIn('status', ['recorded'])
                    ->whereNull('replenishment_request_id')
                    ->orderBy('expense_date')
                    ->get(['id', 'expense_date', 'expense_type', 'amount', 'description']);
                return response()->json($rows);
            default:
                return inertia('Modules/Expenses/Index', [
                    'dropdowns' => [
                        'funds' => $this->fundsWithBudget(),
                    ],
                ]);
        }
    }

    public function store(ExpenseRequest $request)
    {
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts/expenses', 'public');
        }
        $request->merge(['receipt_path' => $receiptPath, 'status' => 'recorded']);

        $userId = auth()->id();
        $result = $this->handleTransaction(fn() => $this->expense->save($request, $userId));

        return back()->with([
            'success' => $result['message'],
            'info'    => $result['info'],
            'data'    => $result['data'],
        ]);
    }

    public function update(ExpenseRequest $request, $id)
    {
        $expense = Expense::findOrFail($id);

        // Cannot edit expenses already submitted/reimbursed
        if (in_array($expense->status, ['submitted', 'reimbursed'])) {
            return back()->withErrors(['status' => 'Cannot edit an expense that is part of an active replenishment request.']);
        }

        $request->merge(['id' => $id]);

        if ($request->hasFile('receipt')) {
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $receiptPath = $request->file('receipt')->store('receipts/expenses', 'public');
            $request->merge(['receipt_path' => $receiptPath]);
        }

        $result = $this->handleTransaction(fn() => $this->expense->update($request));

        return back()->with([
            'success' => $result['message'],
            'info'    => $result['info'],
        ]);
    }

    public function destroy($id)
    {
        $expense = Expense::findOrFail($id);
        if (in_array($expense->status, ['submitted', 'reimbursed'])) {
            return response()->json([
                'message' => 'Cannot delete an expense that is part of an active replenishment request.',
                'status'  => 'error',
            ], 422);
        }

        $result = $this->handleTransaction(fn() => $this->expense->delete($id));

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
        ]);
    }

    public function approve($id)
    {
        $result = $this->handleTransaction(fn() => $this->expense->approve($id));

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
            'data'    => $result['data'] ?? null,
        ]);
    }

    public function void($id)
    {
        $result = $this->handleTransaction(fn() => $this->expense->void($id));

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'] ?? 'success',
            'data'    => $result['data'] ?? null,
        ]);
    }

    public function storeBudget(Request $request)
    {
        $request->validate([
            'expense_type' => 'required|in:operational,utilities,supplies,transportation,maintenance,others',
            'month'        => 'required|integer|min:1|max:12',
            'year'         => 'required|integer|min:2020|max:2099',
            'amount'       => 'required|numeric|min:0',
        ]);

        $result = $this->handleTransaction(fn() => $this->expense->saveBudget($request));

        return response()->json([
            'message' => $result['message'],
            'status'  => 'success',
            'data'    => $result['data'],
        ]);
    }

    public function destroyBudget($id)
    {
        $result = $this->handleTransaction(fn() => $this->expense->deleteBudget($id));

        return response()->json([
            'message' => $result['message'],
            'status'  => 'success',
        ]);
    }

    private function fundsWithBudget(): array
    {
        $weekStart = Carbon::now()->startOfWeek()->toDateString();
        $weekEnd   = Carbon::now()->endOfWeek()->toDateString();

        return PettyCashFund::orderBy('name')
            ->get(['id', 'name', 'balance', 'weekly_budget'])
            ->map(function ($f) use ($weekStart, $weekEnd) {
                $weeklySpent = (float) Expense::where('fund_id', $f->id)
                    ->whereIn('status', ['recorded', 'submitted', 'reimbursed'])
                    ->whereBetween('expense_date', [$weekStart, $weekEnd])
                    ->sum('amount');

                return [
                    'id'            => $f->id,
                    'name'          => $f->name,
                    'balance'       => (float) $f->balance,
                    'weekly_budget' => (float) $f->weekly_budget,
                    'weekly_spent'  => round($weeklySpent, 2),
                    'weekly_remaining' => $f->weekly_budget > 0
                        ? round((float) $f->weekly_budget - $weeklySpent, 2)
                        : null,
                ];
            })
            ->values()
            ->all();
    }
}
