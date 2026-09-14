<?php

namespace App\Http\Requests\Libraries;

use App\Services\System\Permission\SupervisorActions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

            // Undeclared keys are stripped by validated(), so without these two
            // lines the authorization checkboxes would post and silently vanish
            // before the service ever saw them.
            'authorizations' => 'sometimes|array',
            'authorizations.*' => ['string', Rule::in(SupervisorActions::keys())],
        ];
    }
}
