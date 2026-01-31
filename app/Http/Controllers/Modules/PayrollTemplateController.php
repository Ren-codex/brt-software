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
use App\Http\Requests\Modules\AddEmployeesRequest;

class PayrollTemplateController extends Controller
{
    use HandlesTransaction;

    public $payrollTemplate;
    public function __construct(PayrollTemplateService $payrollTemplate)
    {
        $this->payrollTemplate = $payrollTemplate;
    }

    public function index(Request $request)
    {
        $payrollTemplate = $this->payrollTemplate->getAll($request);
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

        return new PayrollTemplateResource($result['data']);
    }

    public function destroy($id)
    {
        $result = $this->handleTransaction(function () use ($id) {
            return $this->payrollTemplate->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function removeEmployee($templateId, $employeeId)
    {
        $result = $this->handleTransaction(function () use ($templateId, $employeeId) {
            return $this->payrollTemplate->removeEmployee($templateId, $employeeId);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function addEmployees(AddEmployeesRequest $request, $templateId)
    {
        $result = $this->handleTransaction(function () use ($request, $templateId) {
            return $this->payrollTemplate->addEmployees($request, $templateId);
        });

        return new PayrollTemplateResource($result['data']);
    }
}
