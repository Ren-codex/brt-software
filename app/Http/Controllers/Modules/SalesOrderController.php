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
                        'locations' => $this->dropdown->locations(),
                        'sales_statuses' => $this->dropdown->sales_statuses(),
                    ],
                    'isExternal' => false,
                    'return_grace_period' => (int) AppSetting::get('return_grace_period', 7),
                ]);
            break;
        }
    }

    /**
     * Is this credit sale actually deferring payment?
     *
     * Compared against today rather than the order date: the due date says when
     * the money becomes collectible, and a backdated order picked as "due today"
     * is still collectible today. A missing or unreadable date counts as
     * deferred, so a payload that simply omits it cannot slip past the approval.
     */
    private function creditIsDeferred(mixed $dueDate): bool
    {
        if (blank($dueDate)) {
            return true;
        }

        try {
            return \Illuminate\Support\Carbon::parse($dueDate)->startOfDay()->isAfter(now()->startOfDay());
        } catch (\Throwable) {
            return true;
        }
    }

    public function store(SalesOrderRequest $request){

        // A credit sale commits the business to collecting later, so somebody
        // authorised for it approves with their own credentials. This replaces
        // the "type CREDIT" box, which only ever existed in the browser.
        //
        // Due today is the exception: nothing is being deferred, so there is no
        // commitment to approve.
        if (\App\Models\SalesOrder::isTermCredit($request->payment_mode)
            && $this->creditIsDeferred($request->input('due_date'))) {
            $this->requireSupervisor($request, 'sales.credit_sale');
        }

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
