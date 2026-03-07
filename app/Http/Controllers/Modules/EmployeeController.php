<?php

namespace App\Http\Controllers\Modules;

use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Models\SalesOrderIncentive;
use App\Services\Modules\EmployeeClass;
use App\Http\Requests\Modules\EmployeeRequest;

class EmployeeController extends Controller
{
     use HandlesTransaction;

    public $employee,$dropdown;

    public function __construct(EmployeeClass $employee, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->employee = $employee;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->employee->lists($request);
            break;
            default:
                return inertia('Modules/Employees/Index', [
                    'dropdowns' => [
                        'positions' => $this->dropdown->positions(),
                        'roles' => $this->dropdown->roles(),
                    ]
                ]);
            break;
        }
    }

    public function store(EmployeeRequest $request){
        $userId = auth()->id();
        $result = $this->handleTransaction(function () use ($request, $userId) {
            return $this->employee->save($request, $userId);
        });

        return back()->with([
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function update(EmployeeRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->employee->update($request);
        });

        return back()->with([
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function toggleActive(Request $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->employee->toggleActive($request);
        });

        return back()->with([
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

   public function destroy($id){
        $result = $this->handleTransaction(function () use ($id) {
            return $this->employee->delete($id);
        });

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
        ]);
    }

    public function incentivesSummary(Request $request, $id)
    {
        Employee::findOrFail($id);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        if ($month < 1 || $month > 12 || $year < 1900 || $year > 9999) {
            return response()->json([
                'message' => 'Invalid month or year.',
            ], 422);
        }

        $currentStart = Carbon::create($year, $month, 1)->startOfMonth()->startOfDay();
        $currentEnd = (clone $currentStart)->endOfMonth()->endOfDay();
        $previousStart = (clone $currentStart)->subMonth()->startOfMonth()->startOfDay();
        $previousEnd = (clone $previousStart)->endOfMonth()->endOfDay();

        $current = $this->getIncentiveTotals((int) $id, $currentStart, $currentEnd);
        $previous = $this->getIncentiveTotals((int) $id, $previousStart, $previousEnd);

        return response()->json([
            'year' => $year,
            'month' => $month,
            'totals' => [
                'total_amount' => $current['total_amount'],
                'total_rice_sold_kg' => $current['total_rice_sold_kg'],
                'total_points_earned' => $current['total_points_earned'],
            ],
            'changes' => [
                'amount_change_percent' => $this->computeChangePercent($current['total_amount'], $previous['total_amount']),
                'rice_change_percent' => $this->computeChangePercent($current['total_rice_sold_kg'], $previous['total_rice_sold_kg']),
                'points_change_percent' => $this->computeChangePercent($current['total_points_earned'], $previous['total_points_earned']),
            ],
        ]);
    }

    private function getIncentiveTotals(int $employeeId, Carbon $from, Carbon $to): array
    {
        $totals = SalesOrderIncentive::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(SUM(product_total_kg), 0) as total_rice_sold_kg,
                COALESCE(SUM(sold_quantity), 0) as total_points_earned
            ')
            ->first();

        return [
            'total_amount' => (float) ($totals->total_amount ?? 0),
            'total_rice_sold_kg' => (float) ($totals->total_rice_sold_kg ?? 0),
            'total_points_earned' => (int) ($totals->total_points_earned ?? 0),
        ];
    }

    private function computeChangePercent(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return round(((($current - $previous) / $previous) * 100), 2);
    }
}
