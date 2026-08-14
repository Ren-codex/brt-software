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
        $result = $this->handleTransaction(function () use ($request, $id) {
            return $this->rolePermission->save($id, $request->validated()['grants'] ?? []);
        });

        return response()->json($result);
    }
}
