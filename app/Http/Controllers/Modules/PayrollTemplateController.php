<?php

namespace App\Http\Controllers\Modules;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Http\Resources\Modules\EmployeeResource;
use App\Http\Requests\Modules\PayrollTemplateRequest;
use App\Traits\HandlesTransaction;
use App\Services\Modules\PayrollTemplateClass as PayrollTemplateService;
use App\Http\Resources\Modules\PayrollTemplateResource;

class PayrollTemplateController extends Controller
{
    use HandlesTransaction;

    public $payrollTemplate;
    public function __construct(PayrollTemplateService $payrollTemplate)
    {
        $this->payrollTemplate = $payrollTemplate;
    }

    public function index()
    {
        $payrollTemplate = $this->payrollTemplate->getAll();
        return PayrollTemplateResource::collection($payrollTemplate);
    }

    public function getAvailableEmployees(Request $request)
    {
        $employees = Employee::where('is_active', 1)
            ->whereNotIn('id', function ($query) {
                $query->select('employee_id')
                      ->from('payroll_template_employees');
            })
            ->get();

        return response()->json([
            'data' => EmployeeResource::collection($employees)
        ]);
    }

    public function store(PayrollTemplateRequest $request)
    {
        $result = $this->handleTransaction(function () use ($request) {
            return $this->payrollTemplate->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ]);
    }

    public function update(PayrollTemplateRequest $request, $id)
    {
        $result = $this->handleTransaction(function () use ($request, $id) {
            return $this->payrollTemplate->update($request, $id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'] ?? true,
        ]);
    }
}
