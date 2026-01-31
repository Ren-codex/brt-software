<?php

namespace App\Http\Controllers\Modules;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SalesOrderIncentive;
use App\Services\DropdownClass;
use Illuminate\Http\JsonResponse;

class SalesIncentivesController extends Controller
{
    public $dropdown;

    public function __construct(DropdownClass $dropdown){
        $this->dropdown = $dropdown;
    }

    public function index(Request $request)
    {
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
            ->when($request->created_date_from, function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->created_date_from);
            })
            ->when($request->created_date_to, function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->created_date_to);
            })
            ->groupBy('employee_id')
            ->orderByRaw('SUM(amount) DESC')
            ->with('employee');

        $grouped = $query->paginate($request->per_page ?? 15)
            ->through(function ($item) {
                return [
                    'employee_id' => $item->employee_id,
                    'total_amount' => (float) $item->total_amount,
                    'total_sold_quantity' => (int) $item->total_sold_quantity,
                    'total_product_total_kg' => (float) $item->total_product_total_kg,
                    'employee' => $item->employee,
                ];
            });

        return response()->json($grouped);
    }
}
