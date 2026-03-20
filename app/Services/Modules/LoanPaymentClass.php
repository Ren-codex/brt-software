<?php

namespace App\Services\Modules;

use App\Http\Resources\Modules\LoanPaymentResource;
use App\Models\Loan;
use App\Models\LoanLog;
use App\Models\LoanPayment;
use Carbon\Carbon;
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
            $remainingTermToPay = (float) $loan->remaining_term_to_pay;
            $paymentType = strtolower((string) $request->input('type', ''));
            $paidTermUnits = max(1, (float) $request->input('paid_term', 1));
            $paidDate = $request->paid_date;

            if ($amount > $remainingBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed remaining balance.',
                ]);
            }

            if ($paymentType === 'advance') {
                if ($remainingTermToPay <= 0 || $remainingBalance <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Unable to compute covered terms for this payment.',
                    ]);
                }

                $termAmount = $remainingBalance / $remainingTermToPay;
                $paidTermUnits = min($remainingTermToPay, round($amount / $termAmount, 2));
                $paidDate = $this->buildAdvanceCoveredPeriodLabel($loan, $paidTermUnits);
            }

            $payment = LoanPayment::create([
                'loan_id' => $loan->id,
                'amount' => $amount,
                'paid_date' => $paidDate,
                'paid_term' => $paidTermUnits,
                'remarks' => $request->remarks,
                'added_by_id' => auth()->id(),
            ]);

            $newAmtPaid = (float) $loan->amtpaid + $amount;
            $newRemainingBalance = max(0, $remainingBalance - $amount);
            $newRemainingTerm = max(0, $remainingTermToPay - $paidTermUnits);

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

    protected function buildAdvanceCoveredPeriodLabel(Loan $loan, float $paidTermUnits): string
    {
        $loanStartDate = $loan->approved_at
            ? Carbon::parse($loan->approved_at)
            : Carbon::parse($loan->created_at);
        $nextCoveredTerm = $this->getNextCoveredTermStart($loan, $loanStartDate);
        $coveredTerms = max(1, (int) ceil($paidTermUnits));
        $endCoveredTerm = $this->getTermByOffset($nextCoveredTerm, $coveredTerms - 1);

        return $this->formatCoveredTermRange($nextCoveredTerm, $endCoveredTerm);
    }

    protected function determineTermUnitFactor(Loan $loan): int
    {
        return (float) $loan->remaining_term_to_pay > (float) $loan->term_months ? 2 : 1;
    }

    protected function getNextCoveredTermStart(Loan $loan, Carbon $loanStartDate): array
    {
        $totalTermUnits = max(0, (float) $loan->term_months * 2);
        $alreadyPaidUnits = max(0, $totalTermUnits - (float) $loan->remaining_term_to_pay);
        $startTerm = $this->getBaseTermFromDate($loanStartDate);
        $nextTerm = $this->getTermByOffset($startTerm, (int) floor($alreadyPaidUnits));

        $latestCoveredTerm = $this->getLatestCoveredTerm($loan);
        if ($latestCoveredTerm && $this->compareTerms($latestCoveredTerm, $nextTerm) >= 0) {
            return $this->getTermByOffset($latestCoveredTerm, 1);
        }

        return $nextTerm;
    }

    protected function getLatestCoveredTerm(Loan $loan): ?array
    {
        $payments = $loan->relationLoaded('payments')
            ? $loan->payments
            : $loan->payments()->orderBy('created_at')->get();

        $latestCoveredTerm = null;

        foreach ($payments as $payment) {
            $coveredTerm = $this->extractCoveredTermFromPayment($payment);

            if (!$coveredTerm) {
                continue;
            }

            if (!$latestCoveredTerm || $this->compareTerms($coveredTerm, $latestCoveredTerm) > 0) {
                $latestCoveredTerm = $coveredTerm;
            }
        }

        return $latestCoveredTerm;
    }

    protected function extractCoveredTermFromPayment(LoanPayment $payment): ?array
    {
        $paidDate = trim((string) $payment->paid_date);

        if ($paidDate !== '') {
            if (preg_match('/^[A-Za-z]+\s+(1-15|16-\d{1,2}),\s+\d{4}$/', $paidDate) === 1) {
                return $this->parseSemiMonthlyTermLabel($paidDate);
            }

            if (preg_match('/to\s+[A-Za-z]+\s+(1-15|16-\d{1,2}),\s+\d{4}$/', $paidDate) === 1) {
                preg_match('/to\s+([A-Za-z]+\s+(?:1-15|16-\d{1,2}),\s+\d{4})$/', $paidDate, $matches);
                return $this->parseSemiMonthlyTermLabel($matches[1]);
            }

            try {
                return $this->getBaseTermFromDate(Carbon::parse($paidDate));
            } catch (\Throwable $e) {
                // Fall through to created_at when the label is not directly parseable.
            }
        }

        return $payment->created_at
            ? $this->getBaseTermFromDate(Carbon::parse($payment->created_at))
            : null;
    }

    protected function parseSemiMonthlyTermLabel(string $label): ?array
    {
        if (preg_match('/^([A-Za-z]+)\s+(1-15|16-\d{1,2}),\s+(\d{4})$/', $label, $matches) !== 1) {
            return null;
        }

        $monthDate = Carbon::createFromFormat('F Y', $matches[1] . ' ' . $matches[3])->startOfMonth();

        return [
            'date' => $monthDate,
            'half' => str_starts_with($matches[2], '1-15') ? 1 : 2,
        ];
    }

    protected function getBaseTermFromDate(Carbon $date): array
    {
        return [
            'date' => $date->copy()->startOfMonth(),
            'half' => $date->day <= 15 ? 1 : 2,
        ];
    }

    protected function getTermByOffset(array $term, int $offset): array
    {
        $sequence = (($term['date']->year * 12) + ($term['date']->month - 1)) * 2 + ($term['half'] - 1) + $offset;
        $monthSequence = (int) floor($sequence / 2);
        $half = ($sequence % 2) + 1;
        $year = (int) floor($monthSequence / 12);
        $month = ($monthSequence % 12) + 1;

        return [
            'date' => Carbon::create($year, $month, 1)->startOfMonth(),
            'half' => $half,
        ];
    }

    protected function compareTerms(array $left, array $right): int
    {
        $leftSequence = (($left['date']->year * 12) + $left['date']->month) * 2 + $left['half'];
        $rightSequence = (($right['date']->year * 12) + $right['date']->month) * 2 + $right['half'];

        return $leftSequence <=> $rightSequence;
    }

    protected function formatCoveredTermRange(array $startTerm, array $endTerm): string
    {
        if ($this->compareTerms($startTerm, $endTerm) === 0) {
            return $this->formatSingleTermLabel($startTerm);
        }

        return $this->formatSingleTermLabel($startTerm) . ' to ' . $this->formatSingleTermLabel($endTerm);
    }

    protected function formatSingleTermLabel(array $term): string
    {
        $date = $term['date']->copy()->startOfMonth();
        $range = $term['half'] === 1
            ? '1-15'
            : '16-' . $date->copy()->endOfMonth()->day;

        return $date->format('F') . ' ' . $range . ', ' . $date->format('Y');
    }
}
