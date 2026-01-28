<?php

namespace App\Services\Modules;

use App\Models\PayrollTemplate;
use App\Models\PayrollTemplateEmployee;
use Illuminate\Support\Facades\Auth;

class PayrollTemplateClass
{
    public function getAll()
    {
        return PayrollTemplate::where('is_active', 1)->get();
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

        // Sync employees: delete existing and add new
        PayrollTemplateEmployee::where('payroll_template_id', $id)->delete();

        foreach ($request->employee_ids as $employeeId) {
            PayrollTemplateEmployee::create([
                'payroll_template_id' => $id,
                'employee_id' => $employeeId,
            ]);
        }

        return [
            'data' => $payrollTemplate,
            'message' => 'Payroll template updated successfully!',
            'info' => "You've successfully updated the payroll template"
        ];
    }
}
