<?php

namespace App\Services\Modules;

use App\Models\Loan;
use App\Http\Resources\Modules\LoanResource;

class LoanClass
{
    public function lists($request)
    {
        $data = LoanResource::collection(
            Loan::with(['employee'])
                ->when($request->keyword, function ($query, $keyword) {
                    $keyword = strtolower($keyword);
                    $query->where(function ($q) use ($keyword) {
                        $q->whereRaw('LOWER(loan_type) LIKE ?', ["%{$keyword}%"])
                          ->orWhereRaw('LOWER(status) LIKE ?', ["%{$keyword}%"])
                          ->orWhereRaw('LOWER(purpose) LIKE ?', ["%{$keyword}%"])
                          ->orWhereHas('employee', function ($q) use ($keyword) {
                              $q->whereRaw('LOWER(firstname) LIKE ?', ["%{$keyword}%"])
                                ->orWhereRaw('LOWER(lastname) LIKE ?', ["%{$keyword}%"])
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
        $data = Loan::create([
            'employee_id' => $request->employee_id,
            'loan_type' => $request->loan_type,
            'amount' => $request->amount,
            'interest_rate' => $request->interest_rate,
            'term_months' => $request->term_months,
            'status' => $request->status,
            'purpose' => $request->purpose,
            'added_by_id' => $userId ?: auth()->id(),
            'remaining_balance' => $request->amount,
            'remaining_term_to_pay' => $request->term_months * 2,
        ]);

        return [
            'data' => new LoanResource($data),
            'message' => 'Loan saved successfully!',
            'info' => "You've successfully saved the loan"
        ];
    }

    public function update($request)
    {
        $data = Loan::findOrFail($request->id);

        $data->update([
            'employee_id' => $request->employee_id,
            'loan_type' => $request->loan_type,
            'amount' => $request->amount,
            'interest_rate' => $request->interest_rate,
            'term_months' => $request->term_months,
            'status' => $request->status,
            'purpose' => $request->purpose,
        ]);

        return [
            'data' => new LoanResource($data),
            'message' => 'Loan updated successfully!',
            'info' => "You've successfully updated the loan"
        ];
    }

    public function delete($id)
    {
        $data = Loan::findOrFail($id);
        $data->delete();

        return [
            'data' => null,
            'message' => 'Loan deleted successfully!',
            'info' => "You've successfully deleted the loan"
        ];
    }
}
