<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\Controller;
use App\Http\Requests\Libraries\RolePermissionRequest;
use App\Services\Libraries\RolePermissionClass;
use App\Traits\HandlesTransaction;

class RolePermissionController extends Controller
{
    use HandlesTransaction;

    public function __construct(protected RolePermissionClass $rolePermission)
    {
    }

    public function show(int $id)
    {
        return response()->json($this->rolePermission->catalogForRole($id));
    }

    public function update(RolePermissionRequest $request, int $id)
    {
        $data = $request->validated();

        $result = $this->handleTransaction(function () use ($data, $id) {
            // Pass null, not [], when the payload never mentioned authorizations
            // -- the service reads null as "leave them as they are".
            return $this->rolePermission->save(
                $id,
                $data['grants'] ?? [],
                array_key_exists('authorizations', $data) ? $data['authorizations'] : null
            );
        });

        return response()->json($result);
    }
}
