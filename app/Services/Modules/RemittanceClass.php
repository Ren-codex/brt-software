<?php

namespace App\Services\Modules;


use App\Models\Remittance;
use App\Models\Receipt;
use App\Models\ListStatus;
use App\Http\Resources\Libraries\RemittanceResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Services\SeriesService;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Validation\ValidationException;

class RemittanceClass
{
    protected $series_service;
    protected $journalEntryService;

    public function __construct(
        SeriesService $series_service,
        JournalEntryService $journalEntryService,
    ) {
        $this->series_service = $series_service;
        $this->journalEntryService = $journalEntryService;
    }

    /**
     * Statuses are resolved by slug rather than hardcoded ids, matching
     * ArInvoiceClass. The previous hardcoded ids pointed at the wrong rows.
     */
    private function statusId(string $slug): int
    {
        $status = ListStatus::getBySlug($slug);

        if (! $status) {
            throw ValidationException::withMessages([
                'status' => "The '{$slug}' status is missing from list_statuses.",
            ]);
        }

        return $status->id;
    }

    public function lists($request)
    {
        $query = Remittance::with(['receipts.arInvoice.sales_order', 'receipts.customer', 'receipts.status', 'status', 'createdBy.employee', 'approvedBy.employee', 'bankDeposit.bankAccount'])
            ->when($request->location_id, function ($query, $locationId) {
                $query->whereHas('receipts', function ($q) use ($locationId) {
                    $q->where(function ($inner) use ($locationId) {
                        $inner->whereHas('arInvoice.sales_order', function ($soQ) use ($locationId) {
                            $soQ->where('location_id', $locationId)
                                ->orWhereNull('location_id');
                        })->orWhereDoesntHave('arInvoice');
                    });
                });
            })
            ->when($request->keyword, function ($query, $keyword) {
                $query->where('remittance_no', 'LIKE', "%{$keyword}%");
            })
            ->when($request->status === 'undeposited', function ($query) {
                $query->whereHas('status', fn ($q) => $q->where('slug', 'liquidated'))
                    ->whereNull('bank_deposit_id');
            })
            ->when($request->status && $request->status !== 'undeposited', function ($query) use ($request) {
                $query->whereHas('status', function ($q) use ($request) {
                    $q->where('slug', $request->status);
                });
            });

        return RemittanceResource::collection(
            $query->orderBy('created_at', 'DESC')
                  ->paginate($request->count ?: 10)
        );
    }

    public function undepositedSummary($request)
    {
        $query = Remittance::whereHas('status', fn ($q) => $q->where('slug', 'liquidated'))
            ->whereNull('bank_deposit_id')
            ->when($request->location_id, function ($query, $location_id) {
                $query->whereHas('receipts', function ($q) use ($location_id) {
                    $q->where(function ($inner) use ($location_id) {
                        $inner->whereHas('arInvoice.sales_order', function ($soQ) use ($location_id) {
                            $soQ->where('location_id', $location_id)
                                ->orWhereNull('location_id');
                        })->orWhereDoesntHave('arInvoice');
                    });
                });
            });

        return response()->json([
            'total_amount' => (float) $query->sum('total_amount'),
            'count'        => $query->count(),
        ]);
    }

    /**
     * Exceptions are allowed to propagate so the DB::transaction wrapper in
     * HandlesTransaction rolls back. Catching them here previously committed
     * partial writes while reporting failure.
     */
    public function save($request)
    {
        $receiptIds = collect($request->receipts ?? [])
            ->filter()
            ->unique()
            ->values();

        if ($receiptIds->isEmpty()) {
            throw ValidationException::withMessages([
                'receipts' => 'Select at least one receipt to remit.',
            ]);
        }

        // Locked for the duration of the transaction so two remittances cannot
        // claim the same receipt concurrently.
        $receipts = Receipt::whereIn('id', $receiptIds)->lockForUpdate()->get();

        if ($receipts->count() !== $receiptIds->count()) {
            throw ValidationException::withMessages([
                'receipts' => 'One or more of the selected receipts no longer exist.',
            ]);
        }

        $pendingStatusId = $this->statusId('pending');

        $hasUnavailableReceipt = $receipts->contains(function ($receipt) use ($pendingStatusId) {
            return (int) $receipt->status_id !== $pendingStatusId || ! is_null($receipt->remittance_id);
        });

        if ($hasUnavailableReceipt) {
            throw ValidationException::withMessages([
                'receipts' => 'One or more selected receipts are no longer pending.',
            ]);
        }

        // Authoritative total, computed from the receipts rather than trusted
        // from the client payload.
        $totalAmount = $receipts->sum('amount_paid');

        $forVerificationStatusId = $this->statusId('for-verification');

        $data = Remittance::create([
            'remittance_no'   => $this->series_service->get('remittance'),
            'remittance_date' => Carbon::now(),
            'summary'         => $request->summary,
            'total_amount'    => $totalAmount,
            'status_id'       => $forVerificationStatusId,
            'created_by_id'   => Auth::id(),
        ]);

        Receipt::whereIn('id', $receiptIds)->update([
            'status_id'     => $forVerificationStatusId,
            'remittance_id' => $data->id,
        ]);

        return [
            'data'    => new RemittanceResource($data->fresh('receipts')),
            'status'  => true,
            'message' => 'Remittance saved was successful!',
            'info'    => "You've successfully saved the remittance",
        ];
    }

    public function delete($id)
    {
        $data = Remittance::with('status')->findOrFail($id);

        if ($data->status?->slug !== 'for-verification') {
            throw ValidationException::withMessages([
                'remittance' => 'Only open remittances can be deleted.',
            ]);
        }

        Receipt::where('remittance_id', $id)->update([
            'status_id'     => $this->statusId('pending'),
            'remittance_id' => null,
        ]);

        $data->delete();

        return [
            'data'    => $data,
            'status'  => true,
            'message' => 'Remittance deleted was successful!',
            'info'    => "You've successfully deleted the remittance",
        ];
    }

    public function remit($id)
    {
        $data = Remittance::with('status')->findOrFail($id);

        if ($data->status?->slug !== 'for-verification') {
            throw ValidationException::withMessages([
                'remittance' => 'Only open remittances can be marked as remitted.',
            ]);
        }

        $remittedStatusId = $this->statusId('remitted');
        $data->status_id = $remittedStatusId;
        $data->save();

        Receipt::where('remittance_id', $data->id)->update(['status_id' => $remittedStatusId]);

        return [
            'data'    => new RemittanceResource($data->fresh('receipts')),
            'status'  => true,
            'message' => 'Remittance marked as remitted!',
            'info'    => "The remittance has been submitted for approval.",
        ];
    }

    public function approve($request, $id)
    {
        $data = Remittance::with('status')->findOrFail($id);

        // A remittance may only be decided once, from the open or remitted state.
        if (! in_array($data->status?->slug, ['for-verification', 'remitted'])) {
            throw ValidationException::withMessages([
                'status' => 'Only open or remitted remittances can be approved or disapproved.',
            ]);
        }

        $isApprove = $request->status === 'Approve';

        $data->status_id      = $isApprove ? $this->statusId('liquidated') : $this->statusId('disapproved');
        $data->approved_by_id = Auth::id();
        $data->approved_at    = Carbon::now();
        $data->remarks        = $request->remarks;

        if ($isApprove && $request->received_amount !== null) {
            $data->received_amount = $request->received_amount;
            $data->variance        = round((float) $request->received_amount - (float) $data->total_amount, 2);
        }

        if ($isApprove) {
            $data->received_via = $request->received_via;
            $data->reference_no = $request->received_via === 'check' ? $request->reference_no : null;
        }

        $data->save();

        if ($isApprove) {
            $this->journalEntryService->recordRemittanceApprovalEntry($data);
        }

        $receiptStatusId = $isApprove ? $this->statusId('liquidated') : $this->statusId('pending');

        $receipts = Receipt::where('remittance_id', $data->id)->get();
        foreach ($receipts as $receipt) {
            $receipt->status_id = $receiptStatusId;
            if (! $isApprove) {
                $receipt->remittance_id = null;
            }
            $receipt->save();
        }

        return [
            'data'    => new RemittanceResource($data->fresh('receipts')),
            'status'  => true,
            'message' => $isApprove ? 'Remittance approval was successful!' : 'Remittance was disapproved.',
            'info'    => $isApprove
                ? "You've successfully approved the remittance"
                : "You've disapproved the remittance",
        ];
    }

    public function myHoldings()
    {
        $employee = Auth::user()->employee;

        if (!$employee) {
            return response()->json(['total_amount' => 0, 'receipt_count' => 0]);
        }

        $receipts = Receipt::whereNull('remittance_id')
            ->whereHas('arInvoice.sales_order', function ($q) use ($employee) {
                $q->where('sales_rep_id', $employee->id);
            })
            ->selectRaw('COUNT(*) as receipt_count, SUM(amount_paid) as total_amount')
            ->first();

        return response()->json([
            'total_amount'  => (float) ($receipts->total_amount ?? 0),
            'receipt_count' => (int)   ($receipts->receipt_count ?? 0),
        ]);
    }

    public function summary($request)
    {
        $from = $request->from
            ? Carbon::parse($request->from)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $to = $request->to
            ? Carbon::parse($request->to)->endOfDay()
            : Carbon::now()->endOfMonth()->endOfDay();

        $remittances = Remittance::with(['createdBy.employee', 'status'])
            ->whereBetween('remittance_date', [$from, $to])
            ->orderBy('remittance_date', 'DESC')
            ->get();

        $grouped = $remittances
            ->groupBy(fn($r) => optional($r->createdBy)->id ?? 0)
            ->map(function ($repRemittances) {
                $rep = optional($repRemittances->first()->createdBy)->employee;
                $byDate = $repRemittances
                    ->groupBy(fn($r) => Carbon::parse($r->remittance_date)->toDateString())
                    ->map(fn($dayRows) => [
                        'date'            => Carbon::parse($dayRows->first()->remittance_date)->toDateString(),
                        'count'           => $dayRows->count(),
                        'total_amount'    => $dayRows->sum('total_amount'),
                        'received_amount' => $dayRows->whereNotNull('received_amount')->sum('received_amount'),
                        'statuses'        => $dayRows->groupBy(fn($r) => $r->status?->slug ?? 'unknown')
                                                     ->map->count(),
                    ])
                    ->values();

                return [
                    'rep_id'           => optional($repRemittances->first()->createdBy)->id,
                    'rep_name'         => $rep?->fullname ?? 'Unknown',
                    'total_amount'     => $repRemittances->sum('total_amount'),
                    'remittance_count' => $repRemittances->count(),
                    'dates'            => $byDate,
                ];
            })
            ->values();

        return response()->json([
            'data' => $grouped,
            'from' => $from->toDateString(),
            'to'   => $to->toDateString(),
        ]);
    }

}
