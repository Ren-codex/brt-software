<?php

namespace App\Services\Modules;


use App\Models\Remittance;
use App\Models\Receipt;
use App\Models\ArInvoice;
use App\Models\Employee;
use App\Models\ListStatus;
use App\Http\Resources\Libraries\RemittanceResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Services\SeriesService;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Validation\ValidationException;
use App\Services\System\Permission\PermissionService;

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
        $user = Auth::user();
        $employeeId = ($user && !app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin'))
            ? $user->employee?->id
            : null;

        $query = Remittance::with(['receipts.arInvoice.sales_order', 'receipts.customer', 'receipts.status', 'status', 'createdBy.employee', 'approvedBy.employee', 'bankDeposit.bankAccount'])
            ->when($employeeId, function ($query) use ($employeeId) {
                $query->whereHas('receipts.arInvoice.sales_order', function ($soQuery) use ($employeeId) {
                    $soQuery->where(function ($salesOrderQuery) use ($employeeId) {
                        $salesOrderQuery
                            ->where('added_by_id', $employeeId)
                            ->orWhere('sales_rep_id', $employeeId);
                    });
                });
            })
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
        $user = Auth::user();
        $employeeId = ($user && !app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin'))
            ? $user->employee?->id
            : null;

        $query = Remittance::whereHas('status', fn ($q) => $q->where('slug', 'liquidated'))
            ->whereNull('bank_deposit_id')
            ->when($employeeId, function ($query) use ($employeeId) {
                $query->whereHas('receipts.arInvoice.sales_order', function ($soQuery) use ($employeeId) {
                    $soQuery->where(function ($salesOrderQuery) use ($employeeId) {
                        $salesOrderQuery
                            ->where('added_by_id', $employeeId)
                            ->orWhere('sales_rep_id', $employeeId);
                    });
                });
            })
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

        // A Sales Rep has no checkbox in the UI and must remit every pending
        // receipt of theirs — but that's only enforced client-side unless we
        // re-check it here, since this endpoint otherwise trusts whatever
        // receipt ids are posted.
        $user = Auth::user();
        $employeeId = ($user && !app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin'))
            ? $user->employee?->id
            : null;

        if ($employeeId) {
            $ownPendingReceiptIds = Receipt::whereNull('remittance_id')
                ->whereHas('status', fn ($q) => $q->where('slug', 'pending'))
                ->where(function ($query) {
                    $query->whereNull('receipt_type')
                        ->orWhere('receipt_type', '!=', 'refund');
                })
                ->whereHas('arInvoice.sales_order', function ($q) use ($employeeId) {
                    $q->where('sales_rep_id', $employeeId);
                })
                ->pluck('id');

            if ($receiptIds->diff($ownPendingReceiptIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'receipts' => 'You may only remit your own pending receipts.',
                ]);
            }

            if ($ownPendingReceiptIds->diff($receiptIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'receipts' => 'All of your pending receipts must be included in this remittance.',
                ]);
            }
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
            $modes = array_filter(array_map('trim', explode(',', (string) $request->received_via)));
            $hasCheck = collect($modes)->contains(fn ($mode) => strtolower($mode) === 'check');
            $data->reference_no = $hasCheck ? $request->reference_no : null;
            $data->received_breakdown = collect($request->received_breakdown ?? [])
                ->filter(fn ($amount) => $amount !== null && $amount !== '')
                ->map(fn ($amount) => round((float) $amount, 2))
                ->toArray();
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
            'message' => $isApprove ? 'Remittance verification was successful!' : 'Remittance was disapproved.',
            'info'    => $isApprove
                ? "You've successfully verified the remittance"
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
            ->whereHas('status', function ($q) {
                $q->where('slug', 'pending');
            })
            ->where(function ($query) {
                $query->whereNull('receipt_type')
                    ->orWhere('receipt_type', '!=', 'refund');
            })
            ->whereHas('arInvoice.sales_order', function ($q) use ($employee) {
                $q->where(function ($salesOrderQuery) use ($employee) {
                    $salesOrderQuery
                        ->where('added_by_id', $employee->id)
                        ->orWhere('sales_rep_id', $employee->id);
                });
            })
            ->selectRaw('COUNT(*) as receipt_count, SUM(amount_paid) as total_amount')
            ->first();

        return response()->json([
            'total_amount'  => (float) ($receipts->total_amount ?? 0),
            'receipt_count' => (int)   ($receipts->receipt_count ?? 0),
        ]);
    }

    /**
     * Employee summary for the Remittance tab. Grouped by sales rep rather
     * than by date - shows the actual pending receipts (not yet remitted)
     * and pending AR invoices (balance_due > 0) each employee is holding,
     * instead of a status-count tally.
     */
    public function summary($request)
    {
        $user = Auth::user();
        $employeeId = ($user && !app(PermissionService::class)->userHasAccess($user, 'sales', null, 'admin'))
            ? $user->employee?->id
            : null;

        $from = $request->from
            ? Carbon::parse($request->from)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $to = $request->to
            ? Carbon::parse($request->to)->endOfDay()
            : Carbon::now()->endOfMonth()->endOfDay();

        $cancelledId = ListStatus::getBySlug('cancelled')?->id ?? 0;

        $pendingReceipts = Receipt::whereNull('remittance_id')
            ->whereHas('status', fn ($q) => $q->where('slug', 'pending'))
            ->where(function ($query) {
                $query->whereNull('receipt_type')
                    ->orWhere('receipt_type', '!=', 'refund');
            })
            ->whereBetween('receipt_date', [$from, $to])
            ->with(['arInvoice.sales_order.customer'])
            ->when($employeeId, function ($query) use ($employeeId) {
                $query->whereHas('arInvoice.sales_order', fn ($q) => $q->where('sales_rep_id', $employeeId));
            })
            ->get()
            ->groupBy(fn ($r) => optional($r->arInvoice?->sales_order)->sales_rep_id ?? 0);

        $pendingArInvoices = ArInvoice::where('balance_due', '>', 0)
            ->where('status_id', '!=', $cancelledId)
            ->whereBetween('invoice_date', [$from, $to])
            ->with(['sales_order.customer'])
            ->when($employeeId, function ($query) use ($employeeId) {
                $query->whereHas('sales_order', fn ($q) => $q->where('sales_rep_id', $employeeId));
            })
            ->get()
            ->groupBy(fn ($ai) => optional($ai->sales_order)->sales_rep_id ?? 0);

        $repIds = $pendingReceipts->keys()->merge($pendingArInvoices->keys())->unique()->filter();

        $employees = Employee::whereIn('id', $repIds)->get()->keyBy('id');

        $rows = $repIds->map(function ($repId) use ($pendingReceipts, $pendingArInvoices, $employees) {
            $receipts = $pendingReceipts->get($repId, collect());
            $invoices = $pendingArInvoices->get($repId, collect());
            $employee = $employees->get($repId);

            return [
                'rep_id'                => $repId,
                'rep_name'              => $employee?->fullname ?? 'Unassigned',
                'pending_receipt_count' => $receipts->count(),
                'pending_receipt_total' => (float) $receipts->sum('amount_paid'),
                'pending_receipts'      => $receipts->map(fn ($r) => [
                    'id'             => $r->id,
                    'receipt_number' => $r->receipt_number,
                    'receipt_date'   => $r->receipt_date,
                    'so_number'      => optional($r->arInvoice?->sales_order)->so_number,
                    'customer_name'  => optional($r->arInvoice?->sales_order?->customer)->name ?? 'Walk-in Customer',
                    'amount_paid'    => (float) $r->amount_paid,
                ])->values(),
                'pending_ar_count'      => $invoices->count(),
                'pending_ar_total'      => (float) $invoices->sum('balance_due'),
                'pending_ar_invoices'   => $invoices->map(fn ($ai) => [
                    'id'              => $ai->id,
                    'invoice_number'  => $ai->invoice_number,
                    'invoice_date'    => $ai->invoice_date,
                    'due_date'        => $ai->due_date,
                    'so_number'       => optional($ai->sales_order)->so_number,
                    'customer_name'   => optional($ai->sales_order?->customer)->name ?? 'Walk-in Customer',
                    'balance_due'     => (float) $ai->balance_due,
                ])->values(),
            ];
        })
        ->sortByDesc(fn ($row) => $row['pending_receipt_total'] + $row['pending_ar_total'])
        ->values();

        return response()->json([
            'data' => $rows,
            'from' => $from->toDateString(),
            'to'   => $to->toDateString(),
        ]);
    }

}
