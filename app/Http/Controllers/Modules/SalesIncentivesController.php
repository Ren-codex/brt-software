<?php

namespace App\Http\Controllers\Modules;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SalesOrderIncentive;
use App\Services\DropdownClass;

class SalesIncentivesController extends Controller
{
    public $dropdown;

    public function __construct(DropdownClass $dropdown){
        $this->dropdown = $dropdown;
    }

    public function index(Request $request)
    {
        $createdDateFrom = $request->created_date_from
            ? Carbon::parse($request->created_date_from)->startOfDay()
            : null;

        $createdDateTo = $request->created_date_to
            ? Carbon::parse($request->created_date_to)->endOfDay()
            : null;

        $detailQuery = SalesOrderIncentive::query()
            ->with(['salesOrder:id,so_number,order_date'])
            ->when($request->keyword, function ($query) use ($request) {
                $kw = $request->keyword;
                $query->whereHas('employee', function ($q) use ($kw) {
                    $q->where('firstname', 'like', '%' . $kw . '%')
                      ->orWhere('lastname', 'like', '%' . $kw . '%')
                      ->orWhereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ['%' . $kw . '%']);
                });
            })
            ->when($createdDateFrom, function ($query) use ($createdDateFrom) {
                $query->where('created_at', '>=', $createdDateFrom);
            })
            ->when($createdDateTo, function ($query) use ($createdDateTo) {
                $query->where('created_at', '<=', $createdDateTo);
            });

        // Group incentives by employee and compute totals with filters
        $query = SalesOrderIncentive::selectRaw('employee_id,
            SUM(amount) as total_amount,
            SUM(sold_quantity) as total_sold_quantity,
            SUM(product_total_kg) as total_product_total_kg')
            ->when($request->keyword, function ($query) use ($request) {
                $kw = $request->keyword;
                $query->whereHas('employee', function ($q) use ($kw) {
                    $q->where('firstname', 'like', '%' . $kw . '%')
                      ->orWhere('lastname', 'like', '%' . $kw . '%')
                      ->orWhereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ['%' . $kw . '%']);
                });
            })
            ->when($createdDateFrom, function ($query) use ($createdDateFrom) {
                $query->where('created_at', '>=', $createdDateFrom);
            })
            ->when($createdDateTo, function ($query) use ($createdDateTo) {
                $query->where('created_at', '<=', $createdDateTo);
            })
            ->groupBy('employee_id')
            ->orderByRaw('SUM(amount) DESC')
            ->with('employee');

        $grouped = $query->paginate($request->per_page ?? 15);

        $detailsByEmployee = $detailQuery
            ->whereIn('employee_id', $grouped->getCollection()->pluck('employee_id'))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('employee_id');

        $grouped->setCollection(
            $grouped->getCollection()->map(function ($item) use ($detailsByEmployee) {
                $details = $detailsByEmployee->get($item->employee_id, collect())
                    ->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'sales_order_id' => $detail->sales_order_id,
                            'sold_quantity' => (int) $detail->sold_quantity,
                            'product_total_kg' => (float) $detail->product_total_kg,
                            'amount' => (float) $detail->amount,
                            'status' => $detail->status,
                            'created_at' => optional($detail->created_at)->toDateTimeString(),
                            'sales_order' => $detail->salesOrder ? [
                                'id' => $detail->salesOrder->id,
                                'so_number' => $detail->salesOrder->so_number,
                                'order_date' => optional($detail->salesOrder->order_date)->toDateString(),
                            ] : null,
                        ];
                    })
                    ->values();

                return [
                    'employee_id' => $item->employee_id,
                    'total_amount' => (float) $item->total_amount,
                    'total_sold_quantity' => (int) $item->total_sold_quantity,
                    'total_product_total_kg' => (float) $item->total_product_total_kg,
                    'employee' => $item->employee,
                    'details' => $details,
                ];
            })
        );

        return response()->json($grouped);
    }
}
