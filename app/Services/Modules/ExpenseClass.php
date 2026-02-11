<?php

namespace App\Services\Modules;

use App\Models\Expense;
use App\Http\Resources\Modules\ExpenseResource;

class ExpenseClass
{
    public function lists($request)
    {
        $data = ExpenseResource::collection(
            Expense::with(['added_by'])
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

        $data->update([
            'expense_type' => $request->expense_type,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date,
            'description' => $request->description,
            'status' => $request->status,
        ]);

        return [
            'data' => new ExpenseResource($data),
            'message' => 'Expense updated successfully!',
            'info' => "You've successfully updated the expense"
        ];
    }

    public function delete($id)
    {
        $data = Expense::findOrFail($id);
        $data->delete();

        return [
            'data' => null,
            'message' => 'Expense deleted successfully!',
            'info' => "You've successfully deleted the expense"
        ];
    }
}
