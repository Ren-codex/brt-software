<?php

namespace App\Services\Modules;

use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\PettyCashFund;
use App\Http\Resources\Modules\ExpenseResource;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Validation\ValidationException;

class ExpenseClass
{
    public function __construct(protected JournalEntryService $journalEntryService)
    {
    }

    public function lists($request)
    {
        $data = ExpenseResource::collection(
            Expense::with(['added_by', 'fund'])
                ->when($request->keyword, function ($query, $keyword) {
                    $keyword = strtolower($keyword);
                    $query->where(function ($q) use ($keyword) {
                        $q->whereRaw('LOWER(expense_type) LIKE ?', ["%{$keyword}%"])
                          ->orWhereRaw('LOWER(status) LIKE ?', ["%{$keyword}%"])
                          ->orWhereRaw('LOWER(description) LIKE ?', ["%{$keyword}%"])
                          ->orWhereHas('added_by', function ($q) use ($keyword) {
                              $q->whereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"])
                                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$keyword}%"]);
                          });
                    });
                })
                ->when($request->fund_id, fn($q, $id) => $q->where('fund_id', $id))
                ->when($request->status, fn($q, $s) => $q->where('status', $s))
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count ?? 15)
        );
        return $data;
    }

    public function getBudgets($request)
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year ?? now()->year);

        $budgets = ExpenseBudget::where('month', $month)->where('year', $year)->get();

        $types = ['operational', 'utilities', 'supplies', 'transportation', 'maintenance', 'others'];

        $result = collect($types)->map(function ($type) use ($budgets, $month, $year) {
            $budget = $budgets->firstWhere('expense_type', $type);
            $used   = Expense::where('expense_type', $type)
                ->where('status', 'released')
                ->whereMonth('expense_date', $month)
                ->whereYear('expense_date', $year)
                ->sum('amount');

            return [
                'id'           => $budget?->id,
                'expense_type' => $type,
                'amount'       => (float) ($budget?->amount ?? 0),
                'used'         => round((float) $used, 2),
                'remaining'    => round((float) ($budget?->amount ?? 0) - (float) $used, 2),
                'month'        => $month,
                'year'         => $year,
            ];
        });

        return response()->json($result);
    }

    public function saveBudget($request)
    {
        $budget = ExpenseBudget::updateOrCreate(
            [
                'expense_type' => $request->expense_type,
                'month'        => $request->month,
                'year'         => $request->year,
            ],
            [
                'amount'         => $request->amount,
                'created_by_id'  => auth()->id(),
            ]
        );

        return [
            'data'    => $budget,
            'message' => 'Budget saved successfully!',
        ];
    }

    public function deleteBudget($id)
    {
        ExpenseBudget::findOrFail($id)->delete();

        return [
            'data'    => null,
            'message' => 'Budget deleted successfully!',
        ];
    }

    public function save($request, $userId = null)
    {
        $amount = (float) $request->amount;

        if ($request->fund_id) {
            $fund = PettyCashFund::findOrFail($request->fund_id);

            if (! $fund->is_active) {
                throw ValidationException::withMessages([
                    'fund_id' => "The selected fund '{$fund->name}' is inactive and cannot receive new expenses.",
                ]);
            }

            if ($fund->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => "Amount (₱" . number_format($amount, 2) . ") exceeds available fund balance (₱" . number_format($fund->balance, 2) . ").",
                ]);
            }

            $fund->decrement('balance', $amount);
        }

        $data = Expense::create([
            'fund_id'      => $request->fund_id ?? null,
            'expense_type' => $request->expense_type,
            'amount'       => $amount,
            'expense_date' => $request->expense_date,
            'description'  => $request->description,
            'receipt_path' => $request->receipt_path ?? null,
            'status'       => $request->status ?? 'recorded',
            'added_by_id'  => $userId ?: auth()->id(),
        ]);

        return [
            'data'    => new ExpenseResource($data),
            'message' => 'Expense saved successfully!',
            'info'    => "You've successfully saved the expense",
        ];
    }

    public function update($request)
    {
        $data = Expense::findOrFail($request->id);
        $wasReleased = $data->status === 'released';

        if ($wasReleased) {
            $this->journalEntryService->reverseEntriesForSource($data, 'Expense updated. Previous expense release entry reversed.', $request->expense_date);
        }

        // Adjust fund balance for the amount difference
        $newAmount = (float) $request->amount;
        $oldAmount = (float) $data->amount;
        $delta     = $newAmount - $oldAmount;

        if ($delta != 0 && $data->fund_id && in_array($data->status, ['recorded', 'submitted'])) {
            $fund = PettyCashFund::find($data->fund_id);
            if ($fund) {
                $fund->decrement('balance', $delta);
            }
        }

        $updateData = [
            'expense_type' => $request->expense_type,
            'amount'       => $newAmount,
            'expense_date' => $request->expense_date,
            'description'  => $request->description,
            'status'       => $request->status,
        ];

        if ($request->has('receipt_path')) {
            $updateData['receipt_path'] = $request->receipt_path;
        }

        $data->update($updateData);

        if ($data->status === 'released') {
            $this->journalEntryService->recordExpenseReleaseEntry($data->fresh());
        }

        return [
            'data'    => new ExpenseResource($data),
            'message' => 'Expense updated successfully!',
            'info'    => "You've successfully updated the expense",
        ];
    }

    public function delete($id)
    {
        $data = Expense::findOrFail($id);

        if ($data->status === 'released') {
            $this->journalEntryService->reverseEntriesForSource($data, 'Released expense deleted. Original expense entry reversed.', now()->toDateString());
        }

        // Restore fund balance for unposted expenses
        if ($data->fund_id && in_array($data->status, ['recorded', 'submitted'])) {
            PettyCashFund::where('id', $data->fund_id)->increment('balance', (float) $data->amount);
        }

        if ($data->receipt_path) {
            \Storage::disk('public')->delete($data->receipt_path);
        }

        $data->delete();

        return [
            'data'    => null,
            'message' => 'Expense deleted successfully!',
            'info'    => "You've successfully deleted the expense",
        ];
    }

    public function release($id)
    {
        $data = Expense::findOrFail($id);

        if ($data->status !== 'approved') {
            return [
                'data'    => new ExpenseResource($data->fresh(['added_by', 'status_info'])),
                'message' => 'Expense is not in approved status.',
                'status'  => 'error',
            ];
        }

        $this->checkBudget($data);

        $data->update(['status' => 'released']);
        $this->journalEntryService->recordExpenseReleaseEntry($data->fresh());

        return [
            'data'    => new ExpenseResource($data->fresh(['added_by', 'status_info'])),
            'message' => 'Expense released successfully!',
            'info'    => "You've successfully released the expense",
            'status'  => 'success',
        ];
    }

    private function checkBudget(Expense $expense): void
    {
        $month = (int) date('m', strtotime($expense->expense_date));
        $year  = (int) date('Y', strtotime($expense->expense_date));

        $budget = ExpenseBudget::where('expense_type', $expense->expense_type)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $budget || $budget->amount <= 0) {
            return;
        }

        $used = Expense::where('expense_type', $expense->expense_type)
            ->where('status', 'released')
            ->whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->where('id', '!=', $expense->id)
            ->sum('amount');

        if (($used + $expense->amount) > $budget->amount) {
            throw ValidationException::withMessages([
                'budget' => sprintf(
                    'Budget exceeded for %s (%s/%s). Budget: ₱%s | Used: ₱%s | This expense: ₱%s | Over by: ₱%s',
                    ucfirst($expense->expense_type),
                    $month,
                    $year,
                    number_format($budget->amount, 2),
                    number_format($used, 2),
                    number_format($expense->amount, 2),
                    number_format(($used + $expense->amount) - $budget->amount, 2)
                ),
            ]);
        }
    }
}
