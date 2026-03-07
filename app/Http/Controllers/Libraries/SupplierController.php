<?php

namespace App\Http\Controllers\Libraries;
use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\SupplierClass;
use App\Http\Requests\Libraries\SupplierRequest;
use App\Models\ListSupplier;
use App\Http\Resources\Libraries\ListSupplierResource;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    use HandlesTransaction;

    public $supplier,$dropdown;

    public function __construct(SupplierClass $supplier, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->supplier = $supplier;
    }

    public function index(Request $request){   
        switch($request->option){
            case 'lists':
                return $this->supplier->lists($request);
            break;
            default:
                return inertia('Modules/Libraries/Suppliers/Index',[
                    'dropdowns' => [
                        'statuses' => $this->dropdown->statuses()
                    ]
                ]); 
            break;
        }   
    }

    public function show($id){
        $supplier = ListSupplier::findOrFail($id);
        return new ListSupplierResource($supplier);
    }

    public function toggleActive(Request $request, $id){
        $supplier = ListSupplier::findOrFail($id);
        $supplier->update(['is_active' => $request->is_active]);
        
        return response()->json([
            'message' => 'Supplier status updated successfully',
            'data' => new ListSupplierResource($supplier)
        ]);
    }

    public function toggleBlacklist(Request $request, $id){
        $supplier = ListSupplier::findOrFail($id);
        $supplier->update(['is_blacklisted' => $request->is_blacklisted]);
        
        return response()->json([
            'message' => 'Supplier blacklist status updated successfully',
            'data' => new ListSupplierResource($supplier)
        ]);
    }

    public function stockReturnSummary($id)
    {
        ListSupplier::findOrFail($id);

        $summary = DB::table('stock_return_items as sri')
            ->join('stock_returns as sr', 'sr.id', '=', 'sri.stock_return_id')
            ->join('purchase_orders as po', 'po.id', '=', 'sr.po_id')
            ->where('po.supplier_id', $id)
            ->selectRaw('
                COALESCE(SUM(sri.quantity), 0) as total_returned,
                COALESCE(SUM(sri.replaced_quantity), 0) as total_replaced,
                COALESCE(SUM(sri.loss_quantity), 0) as total_loss,
                COUNT(DISTINCT sr.id) as total_stock_returns
            ')
            ->first();

        return response()->json([
            'data' => [
                'total_returned' => (int) ($summary->total_returned ?? 0),
                'total_replaced' => (int) ($summary->total_replaced ?? 0),
                'total_loss' => (int) ($summary->total_loss ?? 0),
                'total_stock_returns' => (int) ($summary->total_stock_returns ?? 0),
            ],
        ]);
    }

    public function stockReturns(Request $request, $id)
    {
        ListSupplier::findOrFail($id);
        $count = (int) $request->input('count', 10);
        $count = max(1, min($count, 50));

        $stockReturns = DB::table('stock_returns as sr')
            ->join('purchase_orders as po', 'po.id', '=', 'sr.po_id')
            ->leftJoin('stock_return_items as sri', 'sri.stock_return_id', '=', 'sr.id')
            ->leftJoin('list_statuses as ls', 'ls.id', '=', 'sr.status_id')
            ->where('po.supplier_id', $id)
            ->groupBy('sr.id', 'sr.stock_return_no', 'po.po_number', 'ls.name', 'sr.created_at')
            ->orderByDesc('sr.id')
            ->selectRaw('
                sr.id,
                sr.stock_return_no,
                po.po_number,
                COALESCE(ls.name, "-") as status_name,
                sr.created_at,
                COALESCE(SUM(sri.quantity), 0) as total_returned,
                COALESCE(SUM(sri.replaced_quantity), 0) as total_replaced,
                COALESCE(SUM(sri.loss_quantity), 0) as total_loss
            ')
            ->paginate($count)
            ->appends(['count' => $count])
            ->through(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'stock_return_no' => $row->stock_return_no,
                    'po_number' => $row->po_number,
                    'status_name' => $row->status_name,
                    'created_at' => $row->created_at,
                    'total_returned' => (int) $row->total_returned,
                    'total_replaced' => (int) $row->total_replaced,
                    'total_loss' => (int) $row->total_loss,
                ];
            });

        return response()->json($stockReturns);
    }

  
    public function update(SupplierRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->supplier->update($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }

    public function store(SupplierRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->supplier->save($request);
        });

        return back()->with([
            'data' => $result['data'],
            'message' => $result['message'],
            'info' => $result['info'],
            'status' => $result['status'],
        ]);
    }



 
}
