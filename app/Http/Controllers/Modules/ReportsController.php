<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Services\Modules\ReportClass;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct(private ReportClass $reports)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);

        if ($request->option === 'summary') {
            return response()->json($this->reports->summary($filters));
        }

        return inertia('Modules/Reports/Index', [
            'filters' => $filters,
            'reportData' => $this->reports->summary($filters),
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
