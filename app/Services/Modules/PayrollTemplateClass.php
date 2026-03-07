<?php

namespace App\Services\Modules;

use App\Models\PayrollTemplate;
use App\Models\PayrollTemplateEmployee;
use App\Models\Payroll;
use Illuminate\Support\Facades\Auth;
use App\Models\ListStatus;

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
            'message' => 'Payroll group saved successfully!',
            'info' => "You've successfully saved the payroll group"
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
            'message' => 'Payroll group updated successfully!',
            'info' => "You've successfully updated the payroll group"
        ];
    }

    public function delete($id)
    {
        $payrollTemplate = PayrollTemplate::findOrFail($id);
        
        $hasOngoingPayroll = Payroll::where('payroll_template_id', $payrollTemplate->id)
        ->where('status_id', '!=', ListStatus::getBySlug('completed')->id)
        ->exists();

        if ($hasOngoingPayroll) {
            return [
                'data' => $payrollTemplate,
                'message' => 'Payroll group cannot be deleted because the group has ongoing/unpaid payrolls.',
                'info' => "Please finalize or remove the payrolls covering the current period before deleting the group",
                'status' => false,
            ];
        }

        $payrollTemplate->delete();

        return [
            'data' => $payrollTemplate,
            'message' => 'Payroll group deleted successfully!',
            'info' => "You've successfully deleted the payroll group",
            'status' => true,
        ];
    }

    public function removeEmployee($templateId, $employeeId)
    {
        $payrollTemplate = PayrollTemplate::findOrFail($templateId);

        // Prevent removal if the employee is part of an ongoing payroll
        $hasOngoingPayroll = Payroll::whereHas('items', function($q) use ($employeeId) {
            $q->where('employee_id', $employeeId);
        })
        ->where('status_id', '!=', ListStatus::getBySlug('completed')->id)
        ->exists();

        if ($hasOngoingPayroll) {
            return [
                'data' => $payrollTemplate,
                'message' => 'Employee has an ongoing payroll and cannot be removed from the group.',
                'info' => "Please finalize or remove the payroll covering the current period before removing the employee from the group",
                'status' => false,
            ];
        }

        $payrollTemplateEmployee = PayrollTemplateEmployee::where('payroll_template_id', $templateId)
            ->where('employee_id', $employeeId)
            ->firstOrFail();

        $payrollTemplateEmployee->delete();

        return [
            'data' => $payrollTemplate,
            'message' => 'Employee removed from payroll group successfully!',
            'info' => "You've successfully removed the employee from the payroll group"
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
            'message' => 'Employees added to payroll group successfully!',
            'info' => "You've successfully added employees to the payroll group"
        ];
    }
}
