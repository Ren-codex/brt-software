<?php

namespace App\Http\Controllers\Modules;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollItem;
use DB;
use App\Services\DropdownClass;

class PayrollController extends Controller
{
    public $employee,$dropdown;

    public function __construct(DropdownClass $dropdown){
        $this->dropdown = $dropdown;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->lists($request);
            break;
            default:
                return inertia('Modules/Payroll/Index', [
                    'dropdowns' => [
                        'employees' => $this->dropdown->employees(),
                        'payroll_settings' => $this->dropdown->payroll_settings(),
                        'payroll_templates' => $this->dropdown->payroll_templates(),
                    ]
                ]);
            break;
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.employee_id' => 'required|exists:employees,id',
            'items.*.basic_salary' => 'required|numeric|min:0',
            'items.*.overtime_hours' => 'nullable|numeric|min:0',
            'items.*.deductions' => 'nullable|numeric|min:0',
            'items.*.total_days' => 'nullable|integer|min:0',
            'items.*.net_salary' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            $payroll = Payroll::create([
                'pay_period_start' => $request->pay_period_start,
                'pay_period_end' => $request->pay_period_end,
                'status' => 'draft'
            ]);

            foreach ($request->items as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $item['employee_id'],
                    'basic_salary' => $item['basic_salary'],
                    'overtime_hours' => $item['overtime_hours'] ?? 0,
                    'deductions' => $item['deductions'] ?? 0,
                    'net_salary' => $item['net_salary'],
                ]);
            }
        });

        return response()->json(['message' => 'Payroll created successfully']);
    }

    public function show(Request $request, $id)
    {
        $payroll = Payroll::with('items.employee')->findOrFail($id);
        return response()->json($payroll);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.employee_id' => 'required|exists:employees,id',
            'items.*.basic_salary' => 'required|numeric|min:0',
            'items.*.overtime_hours' => 'nullable|numeric|min:0',
            'items.*.overtime_rate' => 'nullable|numeric|min:0',
            'items.*.deductions' => 'nullable|numeric|min:0',
            'items.*.total_days' => 'nullable|integer|min:0',
            'items.*.net_salary' => 'required|numeric|min:0',
        ]);

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

        return response()->json(['message' => 'Payroll updated successfully']);
    }

    public function destroy($id)
    {
        $payroll = Payroll::findOrFail($id);
        $payroll->delete();

        return response()->json(['message' => 'Payroll deleted successfully']);
    }

    private function lists(Request $request){
        $employees = \App\Models\Employee::with(['position.salary'])
            ->where('is_active', 1)
            ->when($request->keyword, function($query) use ($request){
                $query->where(function($q) use ($request){
                    $q->where('firstname', 'like', '%'.$request->keyword.'%')
                      ->orWhere('lastname', 'like', '%'.$request->keyword.'%')
                      ->orWhere('email', 'like', '%'.$request->keyword.'%');
                });
            })
            ->paginate($request->count ?? 10);

        return response()->json($employees);
    }
}
