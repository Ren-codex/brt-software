<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationClear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BankReconciliationController extends Controller
{
    public function index()
    {
        $bankAccounts = BankAccount::active()->orderBy('bank_name')->orderBy('account_name')
            ->get(['id', 'bank_name', 'account_name', 'gl_code']);

        $reconciliations = BankReconciliation::with(['bankAccount', 'createdBy'])
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r) => [
                'id'               => $r->id,
                'bank_name'        => optional($r->bankAccount)->bank_name . ' — ' . optional($r->bankAccount)->account_name,
                'period_end'       => $r->period_end?->toDateString(),
                'statement_balance'=> (float) $r->statement_balance,
                'statement_balance_formatted' => '₱' . number_format($r->statement_balance, 2),
                'status'           => $r->status,
                'notes'            => $r->notes,
                'created_by'       => optional($r->createdBy)->name,
                'created_at'       => $r->created_at?->toDateString(),
            ]);

        return inertia('Modules/Accounting/BankReconciliation', [
            'bankAccounts'    => $bankAccounts,
            'reconciliations' => $reconciliations,
            'stats'           => [],
        ]);
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'bank_account_id'   => 'required|integer|exists:bank_accounts,id',
            'period_end'        => 'required|date',
            'statement_balance' => 'required|numeric',
            'notes'             => 'nullable|string|max:500',
        ]);

        $reconciliation = BankReconciliation::create([
            'bank_account_id'   => $data['bank_account_id'],
            'period_end'        => $data['period_end'],
            'statement_balance' => round((float) $data['statement_balance'], 2),
            'notes'             => $data['notes'] ?? null,
            'status'            => 'open',
            'created_by_id'     => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Reconciliation started.',
            'data'    => ['id' => $reconciliation->id],
        ]);
    }

    public function show(int $id)
    {
        $reconciliation = BankReconciliation::with(['bankAccount', 'clearedItems', 'createdBy'])->findOrFail($id);
        $bankAccount    = $reconciliation->bankAccount;

        if (!Schema::hasTable('accounts') || !Schema::hasTable('journal_entry_lines')) {
            return response()->json(['lines' => [], 'summary' => $this->emptySummary($reconciliation)]);
        }

        $account = Account::where('gl_code', $bankAccount->gl_code)->first();
        if (!$account) {
            return response()->json(['lines' => [], 'summary' => $this->emptySummary($reconciliation)]);
        }

        $clearedLineIds = $reconciliation->clearedItems->pluck('journal_entry_line_id')->toArray();

        $lines = JournalEntryLine::where('account_id', $account->id)
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('entry_date', '<=', $reconciliation->period_end)
                ->whereNotIn('status', ['reversed'])
            )
            ->with(['journalEntry'])
            ->orderBy(function ($q) {
                $q->select('entry_date')->from('journal_entries')->whereColumn('id', 'journal_entry_lines.journal_entry_id');
            })
            ->orderBy('journal_entry_id')
            ->orderBy('id')
            ->get()
            ->map(fn ($line) => [
                'id'             => $line->id,
                'journal_number' => optional($line->journalEntry)->journal_number,
                'entry_date'     => optional($line->journalEntry)->entry_date?->toDateString(),
                'entry_type'     => optional($line->journalEntry)->entry_type,
                'memo'           => optional($line->journalEntry)->memo,
                'line_type'      => $line->line_type,
                'amount'         => (float) $line->amount,
                'amount_formatted' => '₱' . number_format($line->amount, 2),
                'description'    => $line->description,
                'is_cleared'     => in_array($line->id, $clearedLineIds),
            ])->values();

        $cleared       = $lines->where('is_cleared', true);
        $clearedDebits = $cleared->where('line_type', 'debit')->sum('amount');
        $clearedCredits= $cleared->where('line_type', 'credit')->sum('amount');
        $clearedNet    = round($clearedDebits - $clearedCredits, 2);

        $allDebits  = $lines->where('line_type', 'debit')->sum('amount');
        $allCredits = $lines->where('line_type', 'credit')->sum('amount');
        $bookBalance= round($allDebits - $allCredits, 2);

        $difference = round((float) $reconciliation->statement_balance - $clearedNet, 2);

        return response()->json([
            'lines'   => $lines,
            'summary' => [
                'statement_balance'   => (float) $reconciliation->statement_balance,
                'book_balance'        => $bookBalance,
                'cleared_net'         => $clearedNet,
                'cleared_count'       => $cleared->count(),
                'uncleared_count'     => $lines->where('is_cleared', false)->count(),
                'difference'          => $difference,
                'is_reconciled'       => abs($difference) < 0.01,
            ],
        ]);
    }

    public function toggleClear(Request $request, int $reconciliationId)
    {
        $data = $request->validate([
            'journal_entry_line_id' => 'required|integer|exists:journal_entry_lines,id',
        ]);

        $reconciliation = BankReconciliation::findOrFail($reconciliationId);
        if ($reconciliation->status === 'finalized') {
            return response()->json(['message' => 'Reconciliation is finalized.'], 422);
        }

        $existing = BankReconciliationClear::where('reconciliation_id', $reconciliationId)
            ->where('journal_entry_line_id', $data['journal_entry_line_id'])
            ->first();

        if ($existing) {
            $existing->delete();
            $cleared = false;
        } else {
            BankReconciliationClear::create([
                'reconciliation_id'      => $reconciliationId,
                'journal_entry_line_id'  => $data['journal_entry_line_id'],
            ]);
            $cleared = true;
        }

        return response()->json(['cleared' => $cleared]);
    }

    public function finalize(int $id)
    {
        $reconciliation = BankReconciliation::findOrFail($id);
        $reconciliation->update(['status' => 'finalized']);

        return response()->json(['message' => 'Reconciliation finalized.']);
    }

    public function destroy(int $id)
    {
        BankReconciliation::findOrFail($id)->delete();

        return response()->json(['message' => 'Reconciliation deleted.']);
    }

    private function emptySummary(BankReconciliation $r): array
    {
        return [
            'statement_balance' => (float) $r->statement_balance,
            'book_balance'      => 0,
            'cleared_net'       => 0,
            'cleared_count'     => 0,
            'uncleared_count'   => 0,
            'difference'        => (float) $r->statement_balance,
            'is_reconciled'     => false,
        ];
    }
}
