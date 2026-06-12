<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\PettyCashFund;
use App\Models\ReplenishmentRequest;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use App\Services\SeriesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PettyCashController extends Controller
{
    public function __construct(
        protected SeriesService $series,
        protected JournalEntryService $journal,
    ) {}

    public function index()
    {
        if (!Schema::hasTable('petty_cash_funds')) {
            return inertia('Modules/Accounting/PettyCash', [
                'dataReady' => false,
                'funds'     => [],
                'users'     => [],
            ]);
        }

        $funds = PettyCashFund::with('custodian')
            ->orderBy('name')
            ->get()
            ->map(fn($f) => $this->formatFund($f));

        $users = User::orderBy('username')->get(['id', 'username as name']);

        // All vouchers (expenses) across all funds, newest first
        $vouchers = Expense::with(['fund', 'added_by'])
            ->whereNotNull('fund_id')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($e) => $this->formatVoucher($e));

        // All replenishment requests
        $replenishments = ReplenishmentRequest::with(['fund', 'createdBy', 'reviewedBy', 'expenses'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => $this->formatReplenishment($r));

        $bankAccounts = BankAccount::where('is_active', true)
            ->orderBy('bank_name')
            ->get(['id', 'bank_name', 'account_name', 'account_number'])
            ->map(fn($b) => [
                'id'    => $b->id,
                'label' => $b->bank_name . ' — ' . $b->account_name . ' (' . $b->account_number . ')',
            ]);

        return inertia('Modules/Accounting/PettyCash', [
            'dataReady'      => true,
            'funds'          => $funds,
            'vouchers'       => $vouchers,
            'replenishments' => $replenishments,
            'users'          => $users,
            'bankAccounts'   => $bankAccounts,
            'expenseTypes'   => [
                ['value' => 'operational',    'label' => 'Operational'],
                ['value' => 'utilities',      'label' => 'Utilities'],
                ['value' => 'supplies',       'label' => 'Office Supplies'],
                ['value' => 'transportation', 'label' => 'Transportation'],
                ['value' => 'maintenance',    'label' => 'Maintenance & Repairs'],
                ['value' => 'others',         'label' => 'Others'],
            ],
        ]);
    }

    public function storeVoucher(Request $request)
    {
        $data = $request->validate([
            'fund_id'      => 'required|integer|exists:petty_cash_funds,id',
            'expense_date' => 'required|date',
            'payee'        => 'required|string|max:120',
            'expense_type' => 'required|string|max:80',
            'amount'       => 'required|numeric|min:0.01',
            'description'  => 'nullable|string|max:300',
            'receipt'      => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $fund = PettyCashFund::findOrFail($data['fund_id']);

        if ($data['amount'] > $fund->balance) {
            return response()->json([
                'message' => 'Amount exceeds available fund balance of ₱' . number_format($fund->balance, 2) . '.',
            ], 422);
        }

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts/petty-cash-vouchers', 'public');
        }

        $voucher = Expense::create([
            'voucher_no'   => $this->series->get('pcv_no'),
            'fund_id'      => $data['fund_id'],
            'expense_date' => $data['expense_date'],
            'payee'        => $data['payee'],
            'expense_type' => $data['expense_type'],
            'amount'       => $data['amount'],
            'description'  => $data['description'] ?? null,
            'receipt_path' => $receiptPath,
            'status'       => 'recorded',
            'added_by_id'  => auth()->id(),
        ]);

        // Deduct from fund balance
        $fund->decrement('balance', $data['amount']);

        return response()->json([
            'message' => 'Voucher ' . $voucher->voucher_no . ' recorded.',
            'data'    => $this->formatVoucher($voucher->fresh(['fund', 'added_by'])),
            'fund'    => $this->formatFund($fund->fresh()),
        ]);
    }

    public function topUpFund(Request $request, int $id)
    {
        $data = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'top_up_date'    => 'required|date',
            'bank_account_id'=> 'nullable|integer|exists:bank_accounts,id',
            'notes'          => 'nullable|string|max:300',
        ]);

        $fund = PettyCashFund::findOrFail($id);
        $amount = round((float) $data['amount'], 2);

        DB::transaction(function () use ($fund, $amount, $data) {
            $fund->increment('balance', $amount);
            $fund->increment('fixed_amount', $amount);

            $this->journal->recordFundTopUp($fund->fresh(), $amount, $data['top_up_date'], $data['bank_account_id'] ?? null, $data['notes'] ?? null);
        });

        return response()->json([
            'message' => '₱' . number_format($amount, 2) . ' added to ' . $fund->name . '.',
            'fund'    => $this->formatFund($fund->fresh(['custodian'])),
        ]);
    }

    public function voidVoucher(int $id)
    {
        $voucher = Expense::findOrFail($id);

        if ($voucher->status !== 'recorded') {
            return response()->json(['message' => 'Only unsubmitted vouchers can be voided.'], 422);
        }

        // Restore fund balance
        if ($voucher->fund_id) {
            PettyCashFund::where('id', $voucher->fund_id)->increment('balance', $voucher->amount);
        }

        if ($voucher->receipt_path) {
            Storage::disk('public')->delete($voucher->receipt_path);
        }

        $voucher->delete();

        return response()->json(['message' => 'Voucher voided and fund balance restored.']);
    }

    private function formatFund(PettyCashFund $f): array
    {
        $unsubmitted = Expense::where('fund_id', $f->id)->where('status', 'recorded')->sum('amount');
        $balance     = (float) $f->balance;
        $fixed       = (float) $f->fixed_amount;

        return [
            'id'                   => $f->id,
            'name'                 => $f->name,
            'gl_code'              => $f->gl_code,
            'balance'              => $balance,
            'balance_formatted'    => '₱' . number_format($balance, 2),
            'fixed_amount'         => $fixed,
            'fixed_formatted'      => '₱' . number_format($fixed, 2),
            'unsubmitted_vouchers' => round((float) $unsubmitted, 2),
            'cash_on_hand'         => round($balance, 2),
            'is_active'            => $f->is_active,
            'low_balance'          => $f->low_balance_threshold > 0 && $balance <= $f->low_balance_threshold,
            'low_balance_threshold'=> (float) $f->low_balance_threshold,
            'custodian_id'         => $f->custodian_id,
            'custodian_name'       => optional($f->custodian)->name,
        ];
    }

    private function formatVoucher(Expense $e): array
    {
        return [
            'id'           => $e->id,
            'voucher_no'   => $e->voucher_no,
            'fund_id'      => $e->fund_id,
            'fund_name'    => optional($e->fund)->name,
            'expense_date' => $e->expense_date,
            'payee'        => $e->payee,
            'expense_type' => $e->expense_type,
            'amount'       => (float) $e->amount,
            'amount_fmt'   => '₱' . number_format($e->amount, 2),
            'description'  => $e->description,
            'receipt_path' => $e->receipt_path ? Storage::url($e->receipt_path) : null,
            'status'       => $e->status,
            'added_by'     => optional($e->added_by)->name,
        ];
    }

    private function formatReplenishment(ReplenishmentRequest $r): array
    {
        return [
            'id'             => $r->id,
            'reference_no'   => $r->reference_no,
            'fund_id'        => $r->fund_id,
            'fund_name'      => optional($r->fund)->name,
            'period_label'   => $r->period_label,
            'total_amount'   => (float) $r->total_amount,
            'total_formatted'=> '₱' . number_format($r->total_amount, 2),
            'expense_count'  => $r->expense_count,
            'status'         => $r->status,
            'submitted_at'   => $r->submitted_at?->toDateString(),
            'reviewed_at'    => $r->reviewed_at?->toDateString(),
            'review_notes'   => $r->review_notes,
            'created_by'     => optional($r->createdBy)->name,
            'reviewed_by'    => optional($r->reviewedBy)->name,
            'created_at'     => $r->created_at?->toDateString(),
            'vouchers'       => $r->relationLoaded('expenses')
                ? $r->expenses->map(fn($e) => [
                    'voucher_no'   => $e->voucher_no,
                    'payee'        => $e->payee,
                    'expense_type' => $e->expense_type,
                    'amount_fmt'   => '₱' . number_format($e->amount, 2),
                    'expense_date' => $e->expense_date,
                    'description'  => $e->description,
                ])->values()
                : [],
        ];
    }
}
