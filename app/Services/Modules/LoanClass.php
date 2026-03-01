<?php

namespace App\Services\Modules;

use App\Models\Loan;
use App\Models\LoanLog;
use App\Services\SeriesService;
use App\Http\Resources\Modules\LoanResource;
use Illuminate\Support\Facades\DB;

class LoanClass
{
    protected $series_service;

    public function __construct(
        SeriesService $series_service,
    ) {
        $this->series_service = $series_service;
    }

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
        try {
            db::beginTransaction();

            $principal = (float) $request->amount;
            $interestRate = (float) $request->interest_rate;
            $totalWithInterest = $principal + ($principal * ($interestRate / 100));

            $data = Loan::create([
                'loan_no' => $this->series_service->get('loan_number'),
                'employee_id' => $request->employee_id,
                'loan_type' => $request->loan_type,
                'amount' => $request->amount,
                'interest_rate' => $request->interest_rate,
                'term_months' => $request->term_months,
                'status' => $request->status,
                'purpose' => $request->purpose,
                'added_by_id' => $userId ?: auth()->id(),
                'remaining_balance' => $totalWithInterest,
                'remaining_term_to_pay' => $request->term_months * 2,
            ]);
    
            $this->log($data->id, 'created', 'Loan created');
    
            db::commit();
            return [
                'data' => new LoanResource($data),
                'message' => 'Loan saved successfully!',
                'info' => "You've successfully saved the loan"
            ];

        } catch (\Exception $e) {
            db::rollback();
            throw $e;
        }
    }

    public function update($request)
    {
        try {
            db::beginTransaction();
            
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
    
            $this->log($data->id, 'updated', 'Loan details updated');

            db::commit();
    
            return [
                'data' => new LoanResource($data),
                'message' => 'Loan updated successfully!',
                'info' => "You've successfully updated the loan"
            ];
        } catch (\Exception $e) {
            db::rollback();
            throw $e;
        }
    }

    public function delete($id)
    {
        $data = Loan::findOrFail($id);
        $this->log($data->id, 'deleted', 'Loan deleted');
        $data->delete();

        return [
            'data' => null,
            'message' => 'Loan deleted successfully!',
            'info' => "You've successfully deleted the loan"
        ];
    }

    protected function log($loanId, $action, $remarks = null): void
    {
        LoanLog::create([
            'loan_id' => $loanId,
            'action' => $action,
            'actioned_by_id' => auth()->id(),
            'remarks' => $remarks,
        ]);
    }

    public function updateStatus($request, $id)
    {
        $loan = Loan::findOrFail($id);
        $oldStatus = $loan->status;
        $newStatus = $request->status;

        $loan->update(['status' => $newStatus == 'approved' ? 'approved' : 'rejected']);
        $this->log($loan->id, $newStatus, $request->remarks);

        return [
            'data' => new LoanResource($loan),
            'message' => "Loan status updated to '{$newStatus}' successfully!",
            'info' => "You've successfully updated the loan status from '{$oldStatus}' to '{$newStatus}'"
        ];
    }
}
