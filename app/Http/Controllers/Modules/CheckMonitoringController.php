<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Services\Modules\CheckRegisterClass;
use App\Services\System\Permission\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The rep's window onto the register: are the checks I took good yet?
 *
 * Read-only by construction — there is no action here at all. Confirming a check
 * is an Accounting act, deliberately out of reach, because a rep discharging
 * their own check would make "cleared" meaningless.
 *
 * Scoped the same way remittances are: a rep sees their own, anyone holding
 * sales admin sees everyone's.
 */
class CheckMonitoringController extends Controller
{
    public function __construct(private CheckRegisterClass $register) {}

    public function index(Request $request)
    {
        $filters = [
            'status' => $request->input('status'),
            'keyword' => $request->input('keyword'),
            'count' => $request->input('count'),
        ];

        return response()->json(
            $this->register->lists($filters, $this->ownEmployeeId())
        );
    }

    /**
     * Null means "show everything" — only for someone trusted with the whole
     * Sales module. A rep without an employee record sees nothing rather than
     * everything, which is the safer way to fail.
     */
    private function ownEmployeeId(): ?int
    {
        $user = Auth::user();

        if (!$user || app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin')) {
            return null;
        }

        return $user->employee?->id ?? -1;
    }
}
