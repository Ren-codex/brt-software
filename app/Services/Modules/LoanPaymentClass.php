<?php

namespace App\Services\Modules;

use App\Http\Resources\Modules\LoanPaymentResource;
use App\Models\Loan;
use App\Models\LoanLog;
use App\Models\LoanPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanPaymentClass
{
    public function lists($request)
    {
        return LoanPaymentResource::collection(
            LoanPayment::with(['loan.employee', 'addedBy.employee'])
                ->when($request->loan_id, function ($query, $loanId) {
                    $query->where('loan_id', $loanId);
                })
                ->when($request->keyword, function ($query, $keyword) {
                    $keyword = strtolower($keyword);
                    $query->where(function ($q) use ($keyword) {
                        $q->whereRaw('LOWER(remarks) LIKE ?', ["%{$keyword}%"])
                            ->orWhereHas('loan', function ($loanQuery) use ($keyword) {
                                $loanQuery->whereRaw('LOWER(loan_no) LIKE ?', ["%{$keyword}%"]);
                            });
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->paginate($request->count ?? 10)
        );
    }

    public function save($request)
    {
        DB::beginTransaction();

        try {
            $loan = Loan::findOrFail($request->loan_id);
            $amount = (float) $request->amount;
            $remainingBalance = (float) $loan->remaining_balance;
            $paidTermMonths = max(1, (int) $request->input('paid_term', 1));

            if ($amount > $remainingBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed remaining balance.',
                ]);
            }

            $payment = LoanPayment::create([
                'loan_id' => $loan->id,
                'amount' => $amount,
                'paid_date' => $request->paid_date,
                'paid_term' => $paidTermMonths,
                'remarks' => $request->remarks,
                'added_by_id' => auth()->id(),
            ]);

            $newAmtPaid = (float) $loan->amtpaid + $amount;
            $newRemainingBalance = max(0, $remainingBalance - $amount);
            $newRemainingTerm = max(0, ((int) $loan->remaining_term_to_pay) - ($paidTermMonths * 2));

            $loan->update([
                'amtpaid' => $newAmtPaid,
                'remaining_balance' => $newRemainingBalance,
                'remaining_term_to_pay' => $newRemainingTerm,
                'status' => $newRemainingBalance <= 0 ? 'completed' : ($loan->status === 'pending' ? 'active' : $loan->status),
            ]);

            $this->log($loan->id, 'payment added', 'Loan payment recorded: ' . number_format($amount, 2));

            DB::commit();

            return [
                'data' => new LoanPaymentResource($payment->load(['loan.employee', 'addedBy.employee'])),
                'message' => 'Loan payment saved successfully!',
                'info' => "You've successfully saved the loan payment",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update($request, $id)
    {
        DB::beginTransaction();

        try {
            $payment = LoanPayment::findOrFail($id);
            $loan = Loan::findOrFail($payment->loan_id);
            $oldAmount = (float) $payment->amount;
            $newAmount = (float) $request->amount;

            // Temporarily add back old amount to validate new amount against actual balance.
            $availableBalance = (float) $loan->remaining_balance + $oldAmount;
            if ($newAmount > $availableBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed remaining balance.',
                ]);
            }

            $payment->update([
                'amount' => $newAmount,
                'paid_date' => $request->paid_date,
                'paid_term' => $request->paid_term,
                'remarks' => $request->remarks,
            ]);

            $updatedAmtPaid = max(0, ((float) $loan->amtpaid - $oldAmount) + $newAmount);
            $updatedRemainingBalance = max(0, $availableBalance - $newAmount);

            $loan->update([
                'amtpaid' => $updatedAmtPaid,
                'remaining_balance' => $updatedRemainingBalance,
                'status' => $updatedRemainingBalance <= 0 ? 'completed' : 'active',
            ]);

            $this->log($loan->id, 'payment updated', 'Loan payment updated: ' . number_format($newAmount, 2));

            DB::commit();

            return [
                'data' => new LoanPaymentResource($payment->load(['loan.employee', 'addedBy.employee'])),
                'message' => 'Loan payment updated successfully!',
                'info' => "You've successfully updated the loan payment",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function delete($id)
    {
        DB::beginTransaction();

        try {
            $payment = LoanPayment::findOrFail($id);
            $loan = Loan::findOrFail($payment->loan_id);
            $amount = (float) $payment->amount;

            $updatedAmtPaid = max(0, (float) $loan->amtpaid - $amount);
            $updatedRemainingBalance = max(0, (float) $loan->remaining_balance + $amount);

            $loan->update([
                'amtpaid' => $updatedAmtPaid,
                'remaining_balance' => $updatedRemainingBalance,
                'status' => $updatedRemainingBalance <= 0 ? 'completed' : 'active',
            ]);

            $payment->delete();

            $this->log($loan->id, 'payment deleted', 'Loan payment deleted: ' . number_format($amount, 2));

            DB::commit();

            return [
                'data' => null,
                'message' => 'Loan payment deleted successfully!',
                'info' => "You've successfully deleted the loan payment",
                'status' => true,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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
}
