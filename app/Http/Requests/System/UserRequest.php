<?php

namespace App\Http\Requests\System;

use Hashids\Hashids;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->option === 'credential') {
            return [
                'code' => 'required|string',
                'email' => 'required|email',
                'mobile' => 'required|string',
                'kradworkz' => 'nullable|string',
            ];
        }
        return [
            //
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            if ($this->option === 'credential') {
                if (\App\Models\User::where('email', $this->email)->exists()) {
                    $validator->errors()->add('email', 'The email has already been taken.');
                }

                $mobileExists = \App\Models\Employee::all()->filter(function ($employee) use ($user) {
                    try {
                        return $employee->mobile 
                            && decrypt($employee->mobile) === $this->mobile
                            && $employee->id !== ($user->employee->id ?? null);
                    } catch (\Exception $e) {
                        return false;
                    }
                })->count();

                if ($mobileExists) {
                    $validator->errors()->add('mobile', 'The mobile number has already been taken.');
                }
            }
        });
    }
}
