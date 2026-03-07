<?php

namespace App\Http\Controllers\Modules;

use App\Exports\SalesReportExport;
use App\Http\Controllers\Controller;
use App\Services\Modules\ReportClass;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportsController extends Controller
{
    public function __construct(private ReportClass $reports)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);
        $reportData = $this->reports->summary($filters);

        if ($request->option === 'summary') {
            return response()->json($reportData);
        }
        if ($request->option === 'excel') {
            $filename = 'sales-report-' . now()->format('Ymd_His') . '.xlsx';

            return Excel::download(new SalesReportExport($filters, $reportData), $filename);
        }
        if ($request->option === 'pdf') {
            $filename = 'sales-report-' . now()->format('Ymd_His') . '.pdf';
            $pdf = \PDF::loadView('prints.sales-report', [
                'filters' => $filters,
                'reportData' => $reportData,
            ])->setPaper('A4', 'portrait');

            return $pdf->download($filename);
        }

        return inertia('Modules/Reports/Index', [
            'filters' => $filters,
            'reportData' => $reportData,
        ]);
    }

    private function resolveFilters(Request $request): array
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $day = $request->input('day', now()->toDateString());
        $limit = (int) $request->input('limit', 10);
        $paymentMode = strtolower((string) $request->input('payment_mode', 'all'));

        return [
            'from' => $from,
            'to' => $to,
            'day' => $day,
            'limit' => max(1, min($limit, 50)),
            'payment_mode' => in_array($paymentMode, ['all', 'cash', 'credit'], true) ? $paymentMode : 'all',
        ];
    }
}
