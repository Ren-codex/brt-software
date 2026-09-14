<?php

namespace App\Http\Controllers\Concerns;

use App\Services\System\Permission\SupervisorAuthorization;
use Illuminate\Http\Request;

/**
 * Spend a supervisor's authorisation on the action it was granted for.
 *
 * Called from the controller rather than route middleware because two of the
 * three guarded actions share an endpoint with unguarded ones: a sales order
 * PUT is a return approval only when `action=approve`, and refusing every PUT
 * without a token would block ordinary edits.
 */
trait RequiresSupervisorAuthorization
{
    protected function requireSupervisor(Request $request, string $action, ?string $subjectType = null, ?int $subjectId = null): int
    {
        return app(SupervisorAuthorization::class)->consume(
            (string) $request->input('supervisor_token'),
            $action,
            $subjectType,
            $subjectId
        );
    }
}
