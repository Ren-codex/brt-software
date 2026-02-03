<?php

namespace App\Services\Modules;

use App\Http\Resources\Libraries\PayrollResource;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Services\SeriesService;
use DB;

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
        $payrolls = Payroll::when($request->keyword, function($query) use ($request){
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
        // Prevent creating a payroll when there's already an ongoing (not paid)
        // payroll for the same template and period.
        $exists = Payroll::where('payroll_template_id', $request->payroll_template_id)
            ->where('pay_period_start', $request->pay_period_start)
            ->where('pay_period_end', $request->pay_period_end)
            ->where('status', '!=', 'paid')
            ->exists();

        if ($exists) {
            return [
                'message' => 'An ongoing payroll already exists for this template and period',
                'info' => 'Please close or mark the existing payroll as paid before creating a new one',
                'status' => 'error'
            ];
        }

        DB::transaction(function () use ($request) {
            $payroll = Payroll::create([
                'payroll_no' => $this->series_service->get('payroll_number'),
                'pay_period_start' => $request->pay_period_start,
                'pay_period_end' => $request->pay_period_end,
                'status' => $request->status,
                'total_amount' => $request->total_amount,
                'payroll_template_id' => $request->payroll_template_id,
                'created_by' => auth()->user()->id,
            ]);

            foreach ($request->items as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $item['employee_id'],
                    'basic_salary' => $item['basic_salary'],
                    'overtime_hours' => $item['overtime_hours'],
                    'overtime_rate' => $item['overtime_rate'],
                    'total_days' => $item['total_days'],
                    'deductions' => $item['deductions'],
                    'net_salary' => $item['net_salary'],
                ]);
            }

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

        DB::transaction(function () use ($request, $payroll) {
            $payroll->update([
                'pay_period_start' => $request->pay_period_start,
                'pay_period_end' => $request->pay_period_end,
            ]);

            // Delete existing items
            $payroll->items()->delete();

            // Create new items
            foreach ($request->items as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $item['employee_id'],
                    'basic_salary' => $item['basic_salary'],
                    'overtime_hours' => $item['overtime_hours'] ?? 0,
                    'overtime_rate' => $item['overtime_rate'] ?? 0,
                    'deductions' => $item['deductions'] ?? 0,
                    'total_days' => $item['total_days'] ?? 0,
                    'net_salary' => $item['net_salary'],
                ]);
            }
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
}
