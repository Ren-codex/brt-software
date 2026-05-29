<?php

namespace App\Http\Controllers\Modules;

use Illuminate\Http\Request;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use App\Models\PettyCashFund;
use App\Services\Modules\ReplenishmentService;

class ReplenishmentController extends Controller
{
    use HandlesTransaction;

    public function __construct(protected ReplenishmentService $service) {}

    public function index(Request $request)
    {
        $page = $this->service->lists($request);

        return response()->json([
            'data'  => $page->getCollection()->map(fn($r) => $this->service->formatForFrontend($r))->values(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $r = $this->service->get($id);
        return response()->json($this->service->formatForFrontend($r));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fund_id'      => 'required|integer|exists:petty_cash_funds,id',
            'period_label' => 'nullable|string|max:150',
        ]);

        $result = $this->handleTransaction(fn() => $this->service->createDraft($request));

        if (!$result['status']) {
            return response()->json([
                'message' => $result['message'],
                'info'    => $result['info'],
                'errors'  => $result['errors'],
                'status'  => 'error',
            ], $result['errors'] ? 422 : 500);
        }

        return response()->json([
            'message' => $result['message'],
            'status'  => 'success',
            'data'    => $this->service->formatForFrontend($result['data']),
        ]);
    }

    public function submit($id)
    {
        $result = $this->handleTransaction(fn() => $this->service->submit($id));

        if (!$result['status']) {
            return response()->json([
                'message' => $result['message'],
                'info'    => $result['info'],
                'errors'  => $result['errors'],
                'status'  => 'error',
            ], $result['errors'] ? 422 : 500);
        }

        return response()->json([
            'message' => $result['message'],
            'status'  => 'success',
            'data'    => $this->service->formatForFrontend($result['data']),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $request->validate(['review_notes' => 'nullable|string|max:1000']);

        $result = $this->handleTransaction(fn() => $this->service->approve($id, $request->review_notes));

        if (!$result['status']) {
            return response()->json([
                'message' => $result['message'],
                'info'    => $result['info'],
                'errors'  => $result['errors'],
                'status'  => 'error',
            ], $result['errors'] ? 422 : 500);
        }

        return response()->json([
            'message' => $result['message'],
            'status'  => 'success',
            'data'    => $this->service->formatForFrontend($result['data']),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['review_notes' => 'nullable|string|max:1000']);

        $result = $this->handleTransaction(fn() => $this->service->reject($id, $request->review_notes));

        if (!$result['status']) {
            return response()->json([
                'message' => $result['message'],
                'info'    => $result['info'],
                'errors'  => $result['errors'],
                'status'  => 'error',
            ], $result['errors'] ? 422 : 500);
        }

        return response()->json([
            'message' => $result['message'],
            'status'  => 'success',
            'data'    => $this->service->formatForFrontend($result['data']),
        ]);
    }
}
