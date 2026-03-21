<?php

namespace App\Services\Modules;

use App\Http\Resources\Libraries\PayrollResource;
use App\Models\ListStatus;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\LoanPayment;
use App\Models\LoanLog;
use App\Services\SeriesService;
use DB;
use App\Models\PayrollLog;

class PayrollClass
{
    protected $series_service;

    public function __construct(
        SeriesService $series_service,
    ) { 
        $this->series_service = $series_service;
    }

    public function lists($request)
    {
        $payrolls = Payroll::with(['status', 'template', 'creator.employee', 'approvedBy.employee', 'items.employee', 'logs.actionedBy.employee'])
                ->when($request->keyword, function($query) use ($request){
                    $query->whereHas('items.employee', function($q) use ($request){
                        $q->where('firstname', 'like', '%'.$request->keyword.'%')
                        ->orWhere('lastname', 'like', '%'.$request->keyword.'%')
                        ->orWhere('email', 'like', '%'.$request->keyword.'%');
                    });
                })
                ->paginate($request->count ?? 10);

        return PayrollResource::collection($payrolls);
    }

    public function store($request)
    {
        // Prevent creating a payroll when any of the selected employees already
        // has an ongoing payroll for the same pay period.
        $employeeIds = collect($request->items ?? [])
            ->pluck('employee_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $exists = !empty($employeeIds) && Payroll::where('pay_period_start', $request->pay_period_start)
            ->where('pay_period_end', $request->pay_period_end)
            ->where('status_id', '!=', ListStatus::where('slug', 'disapproved')->first()->id)
            ->whereHas('items', function ($query) use ($employeeIds) {
                $query->whereIn('employee_id', $employeeIds);
            })
            ->exists();

        if ($exists) {
            return [
                'message' => 'An ongoing payroll already exists for one or more employees in this period',
                'info' => 'Please close or release the existing payroll before creating a new one',
                'status' => 'error'
            ];
        }

        DB::transaction(function () use ($request) {
            $payroll = Payroll::create([
                'payroll_no' => $this->series_service->get('payroll_number'),
                'pay_period_start' => $request->pay_period_start,
                'pay_period_end' => $request->pay_period_end,
                'status_id' => ListStatus::where('slug', $request->status)->first()->id,
                'total_amount' => $request->total_amount,
                'payroll_template_id' => $request->payroll_template_id,
                'created_by' => auth()->user()->id,
            ]);

            foreach ($request->items as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $item['employee_id'],
                    'basic_salary' => $item['basic_salary'],
                    'total_days' => $item['total_days'],
                    'earnings' => $item['earnings'] ?? [],
                    'deductions' => $item['deductions'] ?? [],
                    'total_earnings' => $item['total_earnings'] ?? 0,
                    'total_deductions' => $item['total_deductions'] ?? 0,
                    'net_salary' => $item['net_salary'],
                    'loans' => $item['loans'] ?? [],
                ]);
            }

            PayrollLog::create([
                'payroll_id' => $payroll->id,
                'action' => 'created',
                'actioned_by_id' => auth()->user()->id,
            ]);

        });
        return [
            'message' => 'Payroll created successfully',
            'info' => "You've successfully created the payroll"
        ];
    }

    public function show($id)
    {
        $payroll = Payroll::with('items.employee')->findOrFail($id);
        return $payroll;
    }

    public function update($request, $id)
    {
                // create payroll items
        $payroll = Payroll::findOrFail($id);
         // Prevent updating to a payroll when there's already an ongoing (not paid)
        DB::transaction(function () use ($request, $payroll) {
            $payroll->update([
                'pay_period_start' => $request->pay_period_start,
                'pay_period_end' => $request->pay_period_end,
                'status_id' => ListStatus::where('slug', $request->status)->first()->id,
                'total_amount' => $request->total_amount,
                'payroll_template_id' => $request->payroll_template_id,
            ]);

            // Delete existing items
            $payroll->items()->delete();

            // Create new items
            foreach ($request->items as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $item['employee_id'],
                    'basic_salary' => $item['basic_salary'],
                    'earnings' => $item['earnings'] ?? [],
                    'deductions' => $item['deductions'] ?? [],
                    'total_earnings' => $item['total_earnings'] ?? 0,
                    'total_deductions' => $item['total_deductions'] ?? 0,
                    'total_days' => $item['total_days'] ?? 0,
                    'net_salary' => $item['net_salary'],
                    'loans' => $item['loans'] ?? [],
                ]);
            }

            PayrollLog::create([
                'payroll_id' => $payroll->id,
                'action' => 'updated',
                'actioned_by_id' => auth()->user()->id,
            ]);
        });

        return [
            'message' => 'Payroll updated successfully',
            'info' => "You've successfully updated the payroll"
        ];
    }

    public function destroy($id)
    {
        $payroll = Payroll::findOrFail($id);
        $payroll->delete();

        return [
            'data' => $payroll,
            'message' => 'Payroll deleted successfully',
            'info' => "You've successfully deleted the payroll"
        ];
    }

    public function updateStatus($request, $id)
    {
        try{
            DB::beginTransaction();
            $payroll = Payroll::findOrFail($id);

            if($request->status == 'released'){
                $payroll->update([
                    'status_id' => ListStatus::where('slug', 'completed')->first()->id,
                ]);

                PayrollLog::create([
                    'payroll_id' => $payroll->id,
                    'action' => 'released',
                    'actioned_by_id' => auth()->user()->id,
                    'remarks' => $request->remarks ?? null,
                ]);

                // Loan deduction logic
                foreach ($payroll->items as $item) {
                    $loans = $item->loans ?? [];
                    foreach ($loans as $loanData) {
                        if (isset($loanData['remaining_balance']) && isset($loanData['payroll_deduction']) && isset($loanData['term_months'])) {
                            // Find the loan by employee and remaining balance
                            $loan = \App\Models\Loan::findOrFail($loanData['id']);
                            if ($loan) {
                                $deduct = floatval($loanData['payroll_deduction']);
                                $deductionTerm = $loan->divisor == 1 ? 2 : 1;
                                $newRemainingBalance = max(0, floatval($loan->remaining_balance) - $deduct);
                                $periodStart = \Carbon\Carbon::parse($payroll->pay_period_start);
                                $periodEnd = \Carbon\Carbon::parse($payroll->pay_period_end);
                                $paidDateLabel = $periodStart->isSameMonth($periodEnd) && $periodStart->isSameYear($periodEnd)
                                    ? $periodStart->format('F j') . '-' . $periodEnd->format('j, Y')
                                    : $periodStart->format('F j') . '-' . $periodEnd->format('F j, Y');

                                LoanPayment::create([
                                    'loan_id' => $loan->id,
                                    'amount' => $deduct,
                                    'paid_date' => $paidDateLabel,
                                    'paid_term' => $deductionTerm,
                                    'remarks' => 'Auto deduction from payroll #' . $payroll->payroll_no,
                                    'added_by_id' => auth()->id(),
                                ]);

                                LoanLog::create([
                                    'loan_id' => $loan->id,
                                    'action' => 'payment added',
                                    'actioned_by_id' => auth()->id(),
                                    'remarks' => 'Payroll deduction recorded: ' . number_format($deduct, 2) . ' (Payroll #' . $payroll->payroll_no . ')',
                                ]);

                                $loan->remaining_balance = max(0, floatval($loan->remaining_balance) - $deduct);
                                $loan->amtpaid = floatval($loan->amtpaid) + $deduct;
                                $loan->remaining_term_to_pay = max(0, intval($loan->remaining_term_to_pay) - $deductionTerm);
                                // Optionally update status if fully paid
                                if ($newRemainingBalance <= 0) {
                                    $loan->status = 'completed';
                                }
                                $loan->save();
                            }
                        }
                    }
                }
            }else{
                $status = '';
                if($request->status == 'approved'){
                    $status = ListStatus::where('slug', 'for-release')->first();
                }else{
                    $status = ListStatus::where('slug', $request->status)->first();
                }
    
                $payload = [
                    'status_id' => $status->id,
                ];

                if ($request->status === 'approved') {
                    $payload['approved_by_id'] = auth()->id();
                    $payload['approved_at'] = now();
                } elseif (in_array($request->status, ['draft', 'disapproved'])) {
                    $payload['approved_by_id'] = null;
                    $payload['approved_at'] = null;
                }

                $payroll->update($payload);
        
                PayrollLog::create([
                    'payroll_id' => $payroll->id,
                    'action' => 'status updated to '.$status->slug,
                    'actioned_by_id' => auth()->user()->id,
                    'remarks' => $request->remarks ?? null,
                ]);
            }
    
            DB::commit();

            return [
                'data' => $payroll,
                'message' => 'Payroll status updated successfully',
                'info' => "You've successfully updated the payroll status"
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'data' => null,
                'message' => 'Failed to update payroll status',
                'info' => $e->getMessage(),
                'status' => 'error'
            ];
        }
    }
}
