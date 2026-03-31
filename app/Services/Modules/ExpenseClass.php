<?php

namespace App\Services\Modules;

use App\Models\Expense;
use App\Http\Resources\Modules\ExpenseResource;
use App\Services\Accounting\JournalEntryService;

class ExpenseClass
{
    public function __construct(protected JournalEntryService $journalEntryService)
    {
    }

    public function lists($request)
    {
        $data = ExpenseResource::collection(
            Expense::with(['added_by', 'status_info'])
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
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request, $userId = null)
    {
        $data = Expense::create([
            'expense_type' => $request->expense_type,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date,
            'description' => $request->description,
            'status' => $request->status,
            'added_by_id' => $userId ?: auth()->id(),
        ]);

        return [
            'data' => new ExpenseResource($data),
            'message' => 'Expense saved successfully!',
            'info' => "You've successfully saved the expense"
        ];
    }

    public function update($request)
    {
        $data = Expense::findOrFail($request->id);
        $wasReleased = $data->status === 'released';

        if ($wasReleased) {
            $this->journalEntryService->reverseEntriesForSource($data, 'Expense updated. Previous expense release entry reversed.', $request->expense_date);
        }

        $data->update([
            'expense_type' => $request->expense_type,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date,
            'description' => $request->description,
            'status' => $request->status,
        ]);

        if ($data->status === 'released') {
            $this->journalEntryService->recordExpenseReleaseEntry($data->fresh());
        }

        return [
            'data' => new ExpenseResource($data),
            'message' => 'Expense updated successfully!',
            'info' => "You've successfully updated the expense"
        ];
    }

    public function delete($id)
    {
        $data = Expense::findOrFail($id);

        if ($data->status === 'released') {
            $this->journalEntryService->reverseEntriesForSource($data, 'Released expense deleted. Original expense entry reversed.', now()->toDateString());
        }

        $data->delete();

        return [
            'data' => null,
            'message' => 'Expense deleted successfully!',
            'info' => "You've successfully deleted the expense"
        ];
    }

    public function approve($id)
    {
        $data = Expense::findOrFail($id);

        if ($data->status !== 'approved') {
            $data->update([
                'status' => 'approved',
            ]);
        }

        return [
            'data' => new ExpenseResource($data->fresh(['added_by', 'status_info'])),
            'message' => 'Expense approved successfully!',
            'info' => "You've successfully approved the expense",
            'status' => 'success',
        ];
    }

    public function release($id)
    {
        $data = Expense::findOrFail($id);

        if ($data->status === 'approved') {
            $data->update([
                'status' => 'released',
            ]);

            $this->journalEntryService->recordExpenseReleaseEntry($data->fresh());
        }

        return [
            'data' => new ExpenseResource($data->fresh(['added_by', 'status_info'])),
            'message' => 'Expense released successfully!',
            'info' => "You've successfully released the expense",
            'status' => 'success',
        ];
    }
}
