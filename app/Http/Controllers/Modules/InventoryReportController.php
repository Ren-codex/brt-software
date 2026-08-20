<?php

namespace App\Http\Controllers\Modules;

use App\Exports\InventoryReportExport;
use App\Http\Controllers\Controller;
use App\Services\Modules\InventoryReportClass;
use App\Traits\AuthorizesPermission;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InventoryReportController extends Controller
{
    use AuthorizesPermission;

    public function __construct(private InventoryReportClass $reports)
    {
    }

    public function index(Request $request)
    {
        $this->authorizePermission('inventory', 'inventory_stocks', 'view');

        $filters = $this->resolveFilters($request);
        $report = $this->reports->summary($filters);

        if ($request->option === 'summary') {
            return response()->json($report);
        }

        if ($request->option === 'excel') {
            return Excel::download(
                new InventoryReportExport($report),
                'inventory-position-' . now()->format('Ymd_His') . '.xlsx'
            );
        }

        if ($request->option === 'pdf') {
            return \PDF::loadView('prints.inventory-report', ['report' => $report])
                ->setPaper('A4', 'landscape')
                ->download('inventory-position-' . now()->format('Ymd_His') . '.pdf');
        }

        // The report is a tab inside Inventory Management rather than a page of
        // its own, so a bare visit belongs back there. The JSON, Excel and PDF
        // options above are what the tab actually calls.
        return redirect('/inventory');
    }

    /** A snapshot has no date range — only what to include and what to look for. */
    private function resolveFilters(Request $request): array
    {
        $period = (string) $request->input('period', 'current');

        return [
            'keyword'        => trim((string) $request->input('keyword', '')) ?: null,
            'brand'          => trim((string) $request->input('brand', '')) ?: null,
            'low_stock_only' => filter_var($request->input('low_stock_only', false), FILTER_VALIDATE_BOOLEAN),
            'include_empty'  => filter_var($request->input('include_empty', false), FILTER_VALIDATE_BOOLEAN),
            // Anything unrecognised falls back to the full position rather than
            // silently showing a narrower slice than the user asked for.
            'period'         => in_array($period, InventoryReportClass::PERIODS, true) ? $period : 'current',
            // Which month/quarter/week/year — e.g. '2026-08', '2026-Q3', '2026'.
            'period_value'   => trim((string) $request->input('period_value', '')) ?: null,
        ];
    }
}
