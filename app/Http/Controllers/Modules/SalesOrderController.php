<?php

namespace App\Http\Controllers\Modules;

use App\Models\SalesOrder;
use Illuminate\Http\Request;
use App\Services\DropdownClass;
use App\Services\PrintClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\Modules\SalesOrderClass;
use App\Http\Requests\Modules\SalesOrderRequest;


class SalesOrderController extends Controller
{
    use \App\Http\Controllers\Concerns\RequiresSupervisorAuthorization;

    use HandlesTransaction;
    use \App\Traits\AuthorizesPermission;

    public $sales_order,$dropdown , $print;

    public function __construct(SalesOrderClass $sales_order, DropdownClass $dropdown , PrintClass $print){
        $this->dropdown = $dropdown;
        $this->sales_order = $sales_order;
        $this->print = $print;
    }

    public function index(Request $request){
        $this->authorizePermission('sales', 'sales_orders', 'view');

        switch($request->option){
            case 'lists':
                return $this->sales_order->lists($request);
            break;
            case 'dashboard':
                return $this->sales_order->dashboard();
            break;
            case 'delivery-board':
                return $this->sales_order->deliveryBoard($request);
            break;
            case 'stock':
                return $this->sales_order->stockAvailability();
            break;
            case 'return-history':
                return response()->json($this->sales_order->returnHistory($request));
            break;
            case 'products':
                // Fresh product/batch/price snapshot for the Add Item modal — the
                // Inertia-loaded `dropdowns.products` prop is fetched once on page
                // load and never refreshes, so it goes stale as soon as any other
                // stock/price activity happens while this page stays open.
                return response()->json($this->dropdown->products());
            break;
            default:
                return inertia('Modules/Sales/Index', [
                    'dropdowns' => [
                        'customers' => $this->dropdown->customers(),
                        'brands' => $this->dropdown->brands(),
                        'products' => $this->dropdown->products(),
                        'batch_codes' => $this->dropdown->batch_codes(),
                        'sales_reps' => $this->dropdown->sales_reps(),
                        'drivers' => $this->dropdown->drivers(),
                        'checkers' => $this->dropdown->checkers(),
                        'locations' => $this->dropdown->locations(),
                        'sales_statuses' => $this->dropdown->sales_statuses(),
                    ],
                    'isExternal' => false,
                    'return_grace_period' => (int) AppSetting::get('return_grace_period', 7),
                ]);
            break;
        }
    }

    public function store(SalesOrderRequest $request){

        // A credit sale used to need a supervisor's credentials whenever it
        // deferred payment. The customer's credit limit is the control now.

        $result = $this->handleTransaction(function () use ($request) {
            return $this->sales_order->save($request);
        });

        if (!$result['status']) {
            return back()->withErrors($result['errors'] ?? [
                'stock' => $result['info'] ?? 'Unable to save sales order.',
            ]);
        }

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
            'receipt_id' => $result['receipt_id'] ?? null,
        ]);
    }


    /**
     * Approve or send back a delivery that was encoded and is waiting.
     * Approving is what records the delivery and any money entered with it.
     */
    public function deliveryDecision(Request $request, $id)
    {
        $this->authorizePermission('sales', 'sales_orders', 'approver');

        $request->validate([
            'decision' => 'required|in:approved,disapproved',
            'remarks' => 'nullable|string|max:300',
        ]);

        $result = $this->handleTransaction(fn () => $request->decision === 'approved'
            ? $this->sales_order->approveDelivery($id)
            : $this->sales_order->rejectDelivery($id, $request->remarks));

        return response()->json($result, ($result['status'] ?? true) ? 200 : 422);
    }

    /** Approve a sale on credit, or refuse it — which cancels the order. */
    public function creditDecision(Request $request, $id)
    {
        $this->authorizePermission('sales', 'sales_orders', 'approver');

        $request->validate([
            'decision' => 'required|in:approved,disapproved',
            'remarks' => 'nullable|string|max:300',
        ]);

        $result = $this->handleTransaction(fn () => $request->decision === 'approved'
            ? $this->sales_order->approveCredit($id)
            : $this->sales_order->rejectCredit($id, $request->remarks));

        return response()->json($result, ($result['status'] ?? true) ? 200 : 422);
    }

    public function update(SalesOrderRequest $request, $id){

        $result = $this->handleTransaction(function () use ($request, $id) {
                $action = $request->action ?? 'update';
                switch($action){
                    case 'update':
                        $request->merge(['id' => $id]);
                        $request->merge(['is_external' => false]);
                        return $this->sales_order->update($request);
                    break;
                    case 'approve':
                        // Approving a return moves stock and money back.
                        $this->requireSupervisor($request, 'sales.approve_return', \App\Models\SalesOrder::class, (int) $id);
                        $request->merge(['id' => $id]);
                        return $this->sales_order->approve($request->id, $request->item_ids ?? [], $request->replacement_items ?? []);
                    break;
                    case 'mark-delivered':
                        $this->authorizePermission('sales', 'sales_orders', 'encoder');
                        $request->merge(['id' => $id]);

                        return $this->sales_order->markDelivered($request);
                    break;
                    case 'adjustment':
                        $request->merge(['id' => $id]);
                        return $this->sales_order->adjustment($request);
                    break;
                    default:
                        $request->merge(['id' => $id]);
                        $request->merge(['is_external' => false]);
                        return $this->sales_order->update($request);
                    break;
                }
            });


        if (!$result['status']) {
            return back()->withErrors($result['errors'] ?? [
                'stock' => $result['info'] ?? 'Unable to update sales order.',
            ]);
        }


        return back()->with([
            'data'       => $result['data'],
            'message'    => $result['message'],
            'info'       => $result['info'],
            'status'     => $result['status'],
            'receipt_id' => $result['receipt_id'] ?? null,
        ]);

    }


    public function adjustment(SalesOrderRequest $request, $id){
        $request->merge(['id' => $id]);
        $result = $this->handleTransaction(fn() => $this->sales_order->adjustment($request));

        if (!$result['status']) {
            return back()->withErrors($result['errors'] ?? [
                'adjustment' => $result['info'] ?? 'Unable to apply adjustment.',
            ]);
        }

        return back()->with([
            'data'    => $result['data'],
            'message' => $result['message'],
            'info'    => $result['info'],
            'status'  => $result['status'],
        ]);
    }

    /**
     * The status timeline for one order.
     *
     * Held to the same visibility as the list: a rep sees their own orders, a
     * sales administrator sees all of them. Without this check anyone with
     * view access could read another rep's order by guessing its id.
     */
    public function timeline($id)
    {
        $this->authorizePermission('sales', 'sales_orders', 'view');

        $order = \App\Models\SalesOrder::findOrFail($id);

        $scope = app(\App\Services\System\Permission\PermissionService::class)->salesScopeEmployeeId(auth()->user());
        $mine = (int) $order->sales_rep_id === (int) $scope || (int) $order->added_by_id === (int) auth()->id();
        abort_if($scope !== null && ! $mine, 403, 'This order belongs to another sales rep.');

        return response()->json(app(\App\Services\Modules\SalesOrderTimeline::class)->build($order));
    }

    public function show($id , Request $request){
        $this->authorizePermission('sales', 'sales_orders', 'view');

        return $this->print->print($id, $request);
    }

    public function destroy(Request $request, $id){
        // Cancelling is the void-holder's job — a sales rep who raised the order
        // can pull it back — but an approver may do it too, so neither loses out.
        $this->authorizeAnyPermission('sales', 'sales_orders', ['void', 'approver']);

        $result = $this->handleTransaction(function () use ($request, $id) {
            return $this->sales_order->cancel($id, $request->remarks);
        });

        if (!$result['status']) {
            return back()->withErrors($result['errors'] ?? [
                'cancel' => $result['info'] ?? 'Unable to cancel sales order.',
            ]);
        }

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }
}
