<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class RolePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grants' => 'array',
            'grants.*.module_id' => 'required|integer|exists:modules,id',
            'grants.*.submodule_id' => 'nullable|integer|exists:submodules,id',
            'grants.*.access_level' => 'required|string|in:encoder,approver,releaser,void,view,admin',
        ];
    }
}
