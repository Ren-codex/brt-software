<?php

namespace App\Http\Controllers;

use App\Services\DropdownClass;
use App\Services\PrintClass;
use App\Traits\HandlesTransaction;
use Illuminate\Http\Request;
use App\Services\Modules\RemittanceClass;
use App\Http\Requests\Modules\RemittanceRequest;

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
            default:
                return inertia('Modules/Sales/Components/Remittances/Index');
            break;
        }
    }

    private function getDashboardMetrics()
    {
        $totalRemittances = \App\Models\Remittance::count();
        $todayRemittances = \App\Models\Remittance::whereDate('created_at', today())->count();

        // Only approved remittances represent cash actually turned over.
        $totalAmountRemitted = \App\Models\Remittance::whereHas('status', function ($q) {
            $q->where('slug', 'approved');
        })->sum('total_amount');

        // "Open" means still awaiting a decision. Matched on slug, since the
        // previous comparison against the name 'liquidated' only worked by
        // accident of MySQL's case-insensitive collation, and nothing ever set
        // that status anyway - so this counted every remittance ever created.
        $openRemittances = \App\Models\Remittance::whereHas('status', function ($q) {
            $q->where('slug', 'pending');
        })->count();

        return response()->json([
            'total_remittances' => $totalRemittances,
            'total_amount_remitted' => $totalAmountRemitted,
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

    public function approve(Request $request, $id){
        // remarks is a string(255) column, so cap it here rather than letting
        // the database truncate or reject a long note.
        $request->validate([
            'status' => 'required|in:Approve,Disapprove',
            'remarks' => 'nullable|string|max:255',
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
