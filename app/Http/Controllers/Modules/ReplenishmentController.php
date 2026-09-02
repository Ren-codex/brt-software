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
        // source_type is required: without it the journal entry falls back to a
        // generic "Cash in Bank" (1011) catch-all that is never deposited into,
        // so it drifts negative and splits bank reporting away from the real
        // per-bank accounts (1020/1021). The approver must say where the money
        // actually came from.
        $request->validate([
            'review_notes'    => 'nullable|string|max:1000',
            'source_type'     => 'required|in:cash,bank',
            'bank_account_id' => 'nullable|required_if:source_type,bank|exists:bank_accounts,id',
        ]);

        $result = $this->handleTransaction(fn() => $this->service->approve($id, $request->review_notes, $request->source_type, $request->bank_account_id));

        if (!$result['status']) {
            return response()->json([
                'message' => $result['info'] ?? $result['message'],
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
