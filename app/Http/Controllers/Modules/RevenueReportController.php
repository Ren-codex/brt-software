<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Services\Modules\RevenueReportService;
use Illuminate\Http\Request;

class RevenueReportController extends Controller
{
    /**
     * @var RevenueReportService
     */
    protected $revenueReportService;

    public function __construct(RevenueReportService $revenueReportService)
    {
        $this->revenueReportService = $revenueReportService;
    }

    public function index(Request $request)
    {
        // Optional: gather filters from request for future usage
        $filters = $request->only(['from', 'to', 'product_id']);

        $reports = $this->revenueReportService->getReports($filters);

        return response()->json($reports);
    }
}
