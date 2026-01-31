<?php

namespace App\Services\Modules;

use App\Models\PayrollTemplate;
use App\Models\PayrollTemplateEmployee;
use Illuminate\Support\Facades\Auth;

class PayrollTemplateClass
{
    public function getAll($request)
    {
        $query = PayrollTemplate::where('is_active', 1);

        if ($request->keyword) {
            $query->where('name', 'like', '%' . $request->keyword . '%');
        }

        return $query->paginate($request->count ?? 10);
    }

    public function save($request)
    {
        $payrollTemplate = PayrollTemplate::create([
            'name' => ucwords($request->name),
            'is_active' => $request->is_active ?? true,
            'created_by_id' => Auth::user()->id,
        ]);

        // Associate employees
        foreach ($request->employee_ids as $employeeId) {
            PayrollTemplateEmployee::create([
                'payroll_template_id' => $payrollTemplate->id,
                'employee_id' => $employeeId,
            ]);
        }

        return [
            'data' => $payrollTemplate,
            'message' => 'Payroll template saved successfully!',
            'info' => "You've successfully saved the payroll template"
        ];
    }

    public function update($request, $id)
    {
        $payrollTemplate = PayrollTemplate::findOrFail($id);
        $payrollTemplate->update([
            'name' => ucwords($request->name),
        ]);

        return [
            'data' => $payrollTemplate,
            'message' => 'Payroll template updated successfully!',
            'info' => "You've successfully updated the payroll template"
        ];
    }

    public function delete($id)
    {
        $payrollTemplate = PayrollTemplate::findOrFail($id);
        $payrollTemplate->delete();

        return [
            'message' => 'Payroll template deleted successfully!',
            'info' => "You've successfully deleted the payroll template"
        ];
    }

    public function removeEmployee($templateId, $employeeId)
    {
        $payrollTemplateEmployee = PayrollTemplateEmployee::where('payroll_template_id', $templateId)
            ->where('employee_id', $employeeId)
            ->firstOrFail();

        $payrollTemplateEmployee->delete();

        return [
            'message' => 'Employee removed from payroll template successfully!',
            'info' => "You've successfully removed the employee from the payroll template"
        ];
    }

    public function addEmployees($request, $templateId)
    {
        $payrollTemplate = PayrollTemplate::findOrFail($templateId);

        foreach ($request->employee_ids as $employeeId) {
            PayrollTemplateEmployee::create([
                'payroll_template_id' => $templateId,
                'employee_id' => $employeeId,
            ]);
        }

        return [
            'data' => $payrollTemplate,
            'message' => 'Employees added to payroll template successfully!',
            'info' => "You've successfully added employees to the payroll template"
        ];
    }
}
