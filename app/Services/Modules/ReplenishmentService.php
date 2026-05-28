<?php

namespace App\Services\Modules;

use App\Models\Expense;
use App\Models\PettyCashFund;
use App\Models\ReplenishmentRequest;
use App\Services\SeriesService;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ReplenishmentService
{
    public function __construct(
        protected SeriesService $series,
        protected JournalEntryService $journalEntryService
    ) {}

    public function lists($request)
    {
        return ReplenishmentRequest::with(['fund', 'createdBy', 'reviewedBy'])
            ->when($request->fund_id, fn($q, $id) => $q->where('fund_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate($request->count ?? 15);
    }

    public function get($id)
    {
        return ReplenishmentRequest::with([
            'fund',
            'createdBy',
            'reviewedBy',
            'expenses.added_by',
        ])->findOrFail($id);
    }

    public function createDraft($request)
    {
        $fundId = $request->fund_id;

        // Collect all unreimbursed recorded expenses for this fund
        $expenses = Expense::where('fund_id', $fundId)
            ->whereIn('status', ['recorded'])
            ->whereNull('replenishment_request_id')
            ->get();

        if ($expenses->isEmpty()) {
            throw ValidationException::withMessages([
                'expenses' => 'No recorded expenses found for this fund. Record expenses before creating a replenishment request.',
            ]);
        }

        $total = round($expenses->sum('amount'), 2);

        $replenishment = ReplenishmentRequest::create([
            'reference_no'   => $this->series->get('replenishment_no'),
            'fund_id'        => $fundId,
            'period_label'   => $request->period_label ?? null,
            'total_amount'   => $total,
            'expense_count'  => $expenses->count(),
            'status'         => 'draft',
            'created_by_id'  => auth()->id(),
        ]);

        // Link all unreimbursed expenses to this request
        Expense::whereIn('id', $expenses->pluck('id'))
            ->update([
                'replenishment_request_id' => $replenishment->id,
                'status'                   => 'submitted',
            ]);

        return [
            'data'    => $replenishment->fresh(['fund', 'createdBy']),
            'message' => 'Replenishment request created: ' . $replenishment->reference_no,
        ];
    }

    public function submit($id)
    {
        $replenishment = ReplenishmentRequest::findOrFail($id);

        if ($replenishment->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft requests can be submitted.',
            ]);
        }

        $replenishment->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        return [
            'data'    => $replenishment->fresh(['fund', 'createdBy']),
            'message' => $replenishment->reference_no . ' submitted for review.',
        ];
    }

    public function approve($id, $notes = null)
    {
        $replenishment = ReplenishmentRequest::with(['expenses', 'fund'])->findOrFail($id);

        if ($replenishment->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Only submitted requests can be approved.',
            ]);
        }

        DB::transaction(function () use ($replenishment, $notes) {
            $replenishment->update([
                'status'         => 'approved',
                'reviewed_by_id' => auth()->id(),
                'reviewed_at'    => now(),
                'review_notes'   => $notes,
            ]);

            // Promote submitted expenses to reimbursed
            $replenishment->expenses()
                ->where('status', 'submitted')
                ->update(['status' => 'reimbursed']);

            // Bulk-promote any recorded expenses still linked to this request
            $replenishment->expenses()
                ->where('status', 'recorded')
                ->update(['status' => 'reimbursed']);

            // Replenish the fund balance
            $replenishment->fund->increment('balance', $replenishment->total_amount);

            // Fire the journal entry
            $this->journalEntryService->recordReplenishmentEntry($replenishment->fresh(['expenses', 'fund']));
        });

        return [
            'data'    => $replenishment->fresh(['fund', 'createdBy', 'reviewedBy']),
            'message' => $replenishment->reference_no . ' approved. Fund replenished by ₱' . number_format($replenishment->total_amount, 2) . '.',
        ];
    }

    public function reject($id, $notes = null)
    {
        $replenishment = ReplenishmentRequest::findOrFail($id);

        if ($replenishment->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Only submitted requests can be rejected.',
            ]);
        }

        $replenishment->update([
            'status'         => 'rejected',
            'reviewed_by_id' => auth()->id(),
            'reviewed_at'    => now(),
            'review_notes'   => $notes,
        ]);

        // Unlink expenses so PIC can correct and resubmit
        Expense::where('replenishment_request_id', $replenishment->id)
            ->update([
                'status'                   => 'recorded',
                'replenishment_request_id' => null,
            ]);

        return [
            'data'    => $replenishment->fresh(['fund', 'createdBy', 'reviewedBy']),
            'message' => $replenishment->reference_no . ' rejected. Expenses returned to PIC for correction.',
        ];
    }

    public function formatForFrontend(ReplenishmentRequest $r): array
    {
        $weeklyBudget   = (float) optional($r->fund)->weekly_budget;
        $totalAmount    = (float) $r->total_amount;

        // Compare actual total weekly spend (all fund expenses this week) to the budget,
        // not just this replenishment's total — so prior batches are counted.
        $weekStart    = now()->startOfWeek()->toDateString();
        $weekEnd      = now()->endOfWeek()->toDateString();
        $weeklySpent  = $weeklyBudget > 0
            ? (float) Expense::where('fund_id', $r->fund_id)
                ->whereBetween('expense_date', [$weekStart, $weekEnd])
                ->sum('amount')
            : 0;
        $overBudget   = $weeklyBudget > 0 && $weeklySpent > $weeklyBudget;

        return [
            'id'                 => $r->id,
            'reference_no'       => $r->reference_no,
            'fund_id'            => $r->fund_id,
            'fund_name'          => optional($r->fund)->name,
            'period_label'       => $r->period_label,
            'total_amount'       => $totalAmount,
            'total_formatted'    => '₱' . number_format($totalAmount, 2),
            'expense_count'      => $r->expense_count,
            'status'             => $r->status,
            'submitted_at'       => $r->submitted_at?->toDateString(),
            'reviewed_at'        => $r->reviewed_at?->toDateString(),
            'review_notes'       => $r->review_notes,
            'created_by'         => optional($r->createdBy)->name,
            'reviewed_by'        => optional($r->reviewedBy)->name,
            'created_at'         => $r->created_at?->toDateTimeString(),
            'weekly_budget'      => $weeklyBudget,
            'over_weekly_budget' => $overBudget,
            'over_by'            => $overBudget ? round($weeklySpent - $weeklyBudget, 2) : null,
            'expenses'           => $r->relationLoaded('expenses')
                ? $r->expenses->map(fn($e) => [
                    'id'           => $e->id,
                    'expense_type' => $e->expense_type,
                    'amount'       => (float) $e->amount,
                    'amount_fmt'   => '₱' . number_format($e->amount, 2),
                    'expense_date' => $e->expense_date,
                    'description'  => $e->description,
                    'receipt_path' => $e->receipt_path,
                    'recorded_by'  => optional($e->added_by)->name,
                ])->values()
                : [],
        ];
    }
}
