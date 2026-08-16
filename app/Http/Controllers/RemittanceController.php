<?php

namespace App\Http\Controllers;

use App\Services\DropdownClass;
use App\Services\PrintClass;
use App\Traits\HandlesTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Modules\RemittanceClass;
use App\Http\Requests\Modules\RemittanceRequest;
use App\Services\System\Permission\PermissionService;

class RemittanceController extends Controller
{
    use HandlesTransaction;

    public $remittance,$dropdown,$print;

    public function __construct(RemittanceClass $remittance, DropdownClass $dropdown, PrintClass $print){
        $this->dropdown = $dropdown;
        $this->remittance = $remittance;
        $this->print = $print;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                return $this->remittance->lists($request);
            break;
            case 'dashboard':
                return $this->getDashboardMetrics();
            break;
            case 'summary':
                return $this->remittance->summary($request);
            break;
            case 'my_holdings':
                return $this->remittance->myHoldings();
            break;
            case 'undeposited_summary':
                return $this->remittance->undepositedSummary($request);
            break;
            default:
                return inertia('Modules/Sales/Components/Remittances/Index');
            break;
        }
    }

    private function getDashboardMetrics()
    {
        // Scoped by admin permission, not merely by having an employee record -
        // an Administrator who also has an employee profile (e.g. superadmin01)
        // must still see the org-wide total, matching RemittanceClass::lists()/
        // summary()/undepositedSummary().
        $user = Auth::user();
        $employeeId = ($user && !app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin'))
            ? $user->employee?->id
            : null;
        $base = \App\Models\Remittance::query()
            ->when($employeeId, function ($query) use ($employeeId) {
                $query->whereHas('receipts.arInvoice.sales_order', function ($soQuery) use ($employeeId) {
                    $soQuery->where(function ($salesOrderQuery) use ($employeeId) {
                        $salesOrderQuery
                            ->where('added_by_id', $employeeId)
                            ->orWhere('sales_rep_id', $employeeId);
                    });
                });
            });

        $totalRemittances = (clone $base)->count();
        $todayRemittances = (clone $base)->whereDate('created_at', today())->count();

        // Only liquidated remittances represent cash actually turned over and
        // confirmed received. A Remittance's status is never 'approved' -
        // RemittanceClass::approve() sets it to 'liquidated' or 'disapproved'
        // ('approved' is a status used by other modules, e.g. stock returns).
        $totalAmountRemitted = (clone $base)->whereHas('status', function ($q) {
            $q->where('slug', 'liquidated');
        })->sum('total_amount');

        // "Open" means still awaiting an approve/disapprove decision, i.e. the
        // same states RemittanceClass::approve() itself accepts a decision
        // from. 'pending' is a Receipt status and never appears on a
        // Remittance, so this previously matched nothing.
        $openRemittances = (clone $base)->whereHas('status', function ($q) {
            $q->whereIn('slug', ['for-verification', 'remitted']);
        })->count();

        // "Cash on hand" is money collected but NOT yet remitted - the
        // org-wide equivalent of RemittanceClass::myHoldings(), which computes
        // this per sales rep. Receipts, not Remittances: a receipt only joins
        // a Remittance (and leaves 'pending') once someone prepares one.
        $totalCashOnHand = \App\Models\Receipt::whereNull('remittance_id')
            ->whereHas('status', function ($q) {
                $q->where('slug', 'pending');
            })
            ->where(function ($query) {
                $query->whereNull('receipt_type')
                    ->orWhere('receipt_type', '!=', 'refund');
            })
            ->when($employeeId, function ($query) use ($employeeId) {
                $query->whereHas('arInvoice.sales_order', function ($soQuery) use ($employeeId) {
                    $soQuery->where(function ($salesOrderQuery) use ($employeeId) {
                        $salesOrderQuery
                            ->where('added_by_id', $employeeId)
                            ->orWhere('sales_rep_id', $employeeId);
                    });
                });
            })
            ->sum('amount_paid');

        return response()->json([
            'total_remittances' => $totalRemittances,
            'total_amount_remitted' => $totalAmountRemitted,
            'total_cash_on_hand' => $totalCashOnHand,
            'today_remittances' => $todayRemittances,
            'open_remittances' => $openRemittances
        ]);
    }

    public function store(RemittanceRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->remittance->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function destroy($id){
        $result = $this->handleTransaction(function () use ($id) {
            return $this->remittance->delete($id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function remit($id){
        $result = $this->handleTransaction(function () use ($id) {
            return $this->remittance->remit($id);
        });

        return response()->json([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'] ?? 'success',
        ]);
    }

    public function approve(Request $request, $id){
        // remarks is a string(255) column, so cap it here rather than letting
        // the database truncate or reject a long note.
        $request->validate([
            'status' => 'required|in:Approve,Disapprove',
            'remarks' => 'nullable|string|max:255',
            // received_via is derived on the frontend from whichever payment
            // modes actually got an amount entered in the verification table -
            // Cash/GCash/Check/Bank Transfer from the receipts, plus any custom
            // mode the verifier added - so it's a free-form comma-separated
            // list, not a fixed enum.
            'received_via' => [
                'required_if:status,Approve',
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $modes = array_filter(array_map('trim', explode(',', (string) $value)));
                    if (empty($modes)) {
                        $fail('Select at least one payment mode.');
                    }
                },
            ],
            'reference_no' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($request) {
                    $modes = array_filter(array_map('trim', explode(',', (string) $request->received_via)));
                    $hasCheck = collect($modes)->contains(fn ($mode) => strtolower($mode) === 'check');
                    if ($request->status === 'Approve' && $hasCheck && empty($value)) {
                        $fail('Reference no. is required when a check payment is included.');
                    }
                },
            ],
            // Per-payment-mode amounts backing received_amount/received_via,
            // e.g. {"Cash": 500, "GCash": 250} — used to reclassify each
            // portion into the right GL account (see recordRemittanceApprovalEntry).
            'received_breakdown' => 'nullable|array',
            'received_breakdown.*' => 'nullable|numeric|min:0',
        ]);

        $result = $this->handleTransaction(function () use ($request, $id) {
            return $this->remittance->approve($request, $id);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function show($id, Request $request)
    {
        return $this->print->print($id, $request);
    }

    /**
     * Target of GET /remittances/{id}/print. The route has always pointed here
     * but the method did not exist, so that URL threw BadMethodCallException.
     */
    public function printRemittance($id)
    {
        return $this->print->printRemittance($id);
    }
}
