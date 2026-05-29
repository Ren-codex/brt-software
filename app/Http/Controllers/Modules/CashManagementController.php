<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankDeposit;
use App\Models\Expense;
use App\Models\FundTransfer;
use App\Models\JournalEntryLine;
use App\Models\PettyCashFund;
use App\Services\Accounting\CashManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CashManagementController extends Controller
{
    public function __construct(private CashManagementService $service) {}

    public function index()
    {
        $transfers = FundTransfer::with(['fromBankAccount', 'toBankAccount', 'createdBy'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($t) => [
                'id'               => $t->id,
                'transfer_no'      => $t->transfer_no,
                'transfer_date'    => $t->transfer_date,
                'from_bank'        => optional($t->fromBankAccount)->bank_name . ' — ' . optional($t->fromBankAccount)->account_name,
                'to_bank'          => optional($t->toBankAccount)->bank_name . ' — ' . optional($t->toBankAccount)->account_name,
                'amount'           => (float) $t->amount,
                'amount_formatted' => '₱' . number_format($t->amount, 2),
                'reference_number' => $t->reference_number,
                'notes'            => $t->notes,
                'created_by'       => optional($t->createdBy)->name,
                'created_at'       => $t->created_at?->toDateTimeString(),
            ]);

        $weekStart = Carbon::now()->startOfWeek()->toDateString();
        $weekEnd   = Carbon::now()->endOfWeek()->toDateString();

        $funds = PettyCashFund::with(['transactions' => fn($q) => $q->orderByDesc('transaction_date')->orderByDesc('id')])
            ->orderBy('name')
            ->get()
            ->map(function ($f) use ($weekStart, $weekEnd) {
                $weeklySpent = (float) Expense::where('fund_id', $f->id)
                    ->whereIn('status', ['recorded', 'submitted', 'reimbursed'])
                    ->whereBetween('expense_date', [$weekStart, $weekEnd])
                    ->sum('amount');

                $weeklyBudget    = (float) $f->weekly_budget;
                $weeklyRemaining = $weeklyBudget > 0 ? round($weeklyBudget - $weeklySpent, 2) : null;

                return [
                    'id'               => $f->id,
                    'name'             => $f->name,
                    'gl_code'          => $f->gl_code,
                    'balance'          => (float) $f->balance,
                    'balance_formatted'=> '₱' . number_format($f->balance, 2),
                    'is_active'        => $f->is_active,
                    'weekly_budget'    => $weeklyBudget,
                    'weekly_spent'     => round($weeklySpent, 2),
                    'weekly_remaining' => $weeklyRemaining,
                    'transactions'     => $f->transactions->map(fn($t) => [
                        'id'               => $t->id,
                        'transaction_no'   => $t->transaction_no,
                        'type'             => $t->type,
                        'amount'           => (float) $t->amount,
                        'amount_formatted' => '₱' . number_format($t->amount, 2),
                        'category'         => $t->category,
                        'description'      => $t->description,
                        'transaction_date' => $t->transaction_date,
                        'reference_number' => $t->reference_number,
                        'receipt_path'     => $t->receipt_path,
                        'source_type'      => $t->source_type,
                        'bank_account_id'  => $t->bank_account_id,
                        'bank_account_name'=> optional($t->bankAccount)->bank_name . (optional($t->bankAccount)->account_name ? ' — ' . $t->bankAccount->account_name : ''),
                        'created_by'       => optional($t->createdBy)->name,
                    ])->values(),
                ];
            });

        $bankAccounts = BankAccount::active()->orderBy('bank_name')->orderBy('account_name')->get(['id', 'bank_name', 'account_name', 'gl_code']);
        $cashPosition = $this->buildCashPosition($bankAccounts);

        $cashAccounts = Schema::hasTable('accounts')
            ? Account::where('type', 'asset')
                ->where(function ($q) {
                    $q->whereIn('subtype', ['cash', 'petty_cash', 'current_asset'])
                      ->orWhere('name', 'like', '%Cash%');
                })
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'subtype'])
            : collect();

        $deposits = BankDeposit::with(['cashAccount', 'bankAccount', 'createdBy'])
            ->orderByDesc('deposit_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($d) => [
                'id'               => $d->id,
                'deposit_no'       => $d->deposit_no,
                'deposit_date'     => $d->deposit_date,
                'cash_account'     => optional($d->cashAccount)->name,
                'bank_name'        => optional($d->bankAccount)->bank_name . ' — ' . optional($d->bankAccount)->account_name,
                'amount'           => (float) $d->amount,
                'amount_formatted' => '₱' . number_format($d->amount, 2),
                'reference'        => $d->reference,
                'notes'            => $d->notes,
                'created_by'       => optional($d->createdBy)->name,
                'created_at'       => $d->created_at?->toDateTimeString(),
            ]);

        $totalTransferred  = FundTransfer::sum('amount');
        $transferCount     = FundTransfer::count();
        $fundCount         = PettyCashFund::count();
        $totalPettyCash    = PettyCashFund::sum('balance');
        $depositCount      = BankDeposit::count();
        $totalDeposited    = BankDeposit::sum('amount');

        return inertia('Modules/Accounting/CashManagement', [
            'transfers'    => $transfers,
            'funds'        => $funds,
            'bankAccounts' => $bankAccounts,
            'cashAccounts' => $cashAccounts,
            'deposits'     => $deposits,
            'cashPosition' => $cashPosition,
            'stats'        => $this->buildStats(),
            'summaryCards' => [
                ['title' => 'Fund Transfers',    'value' => $transferCount,    'description' => 'Total transfers recorded.',        'icon' => 'ri-exchange-dollar-line'],
                ['title' => 'Total Transferred', 'value' => '₱' . number_format($totalTransferred, 2), 'description' => 'Cumulative amount moved.', 'icon' => 'ri-bank-line'],
                ['title' => 'Bank Deposits',     'value' => $depositCount,     'description' => 'Cash deposited to bank.',          'icon' => 'ri-bank-card-2-line'],
                ['title' => 'Total Deposited',   'value' => '₱' . number_format($totalDeposited, 2),  'description' => 'Cumulative cash deposited.',  'icon' => 'ri-money-dollar-circle-line'],
            ],
        ]);
    }

    public function storeFundTransfer(Request $request)
    {
        $data = $request->validate([
            'transfer_date'        => 'required|date',
            'from_bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'to_bank_account_id'   => 'required|integer|exists:bank_accounts,id|different:from_bank_account_id',
            'amount'               => 'required|numeric|min:0.01',
            'reference_number'     => 'nullable|string|max:100',
            'notes'                => 'nullable|string|max:500',
        ]);

        $transfer = $this->service->createFundTransfer($data);

        return response()->json(['message' => 'Transfer recorded.', 'data' => $transfer]);
    }

    public function destroyFundTransfer(int $id)
    {
        $this->service->deleteFundTransfer($id);

        return response()->json(['message' => 'Transfer deleted and journal entry reversed.']);
    }

    public function storeFund(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'gl_code'         => 'required|string|max:20|unique:petty_cash_funds,gl_code',
            'weekly_budget'   => 'nullable|numeric|min:0',
            'initial_balance' => 'nullable|numeric|min:0.01',
        ]);

        $fund = $this->service->createFund($data);

        return response()->json(['message' => 'Petty cash fund created.', 'data' => $fund]);
    }

    public function updateFund(Request $request, int $id)
    {
        $fund = \App\Models\PettyCashFund::findOrFail($id);

        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'weekly_budget' => 'nullable|numeric|min:0',
        ]);

        $fund->update([
            'name'          => $data['name'],
            'weekly_budget' => $data['weekly_budget'] ?? 0,
        ]);

        return response()->json(['message' => 'Fund updated.', 'data' => $fund->fresh()]);
    }

    public function storePettyCashTransaction(Request $request)
    {
        $data = $request->validate([
            'fund_id'                  => 'required|integer|exists:petty_cash_funds,id',
            'type'                     => 'required|in:replenishment',
            'amount'                   => 'required|numeric|min:0.01',
            'transaction_date'         => 'required|date',
            'category'                 => 'nullable|string|max:80',
            'description'              => 'nullable|string|max:300',
            'reference_number'         => 'nullable|string|max:100',
            'source_type'              => 'nullable|in:cash,bank',
            'bank_account_id'          => 'nullable|integer|exists:bank_accounts,id',
            'receipt'                  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'replenishment_request_id' => 'nullable|integer|exists:replenishment_requests,id',
        ]);

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('receipts/petty-cash', 'public');
        }

        $fund = PettyCashFund::findOrFail($data['fund_id']);
        $txn  = $this->service->addTransaction($fund, $data);

        if (!empty($data['replenishment_request_id'])) {
            \App\Models\ReplenishmentRequest::where('id', $data['replenishment_request_id'])
                ->where('status', 'approved')
                ->update(['status' => 'released']);
        }

        return response()->json(['message' => ucfirst($data['type']) . ' recorded.', 'data' => $txn]);
    }

    public function destroyPettyCashTransaction(int $id)
    {
        $this->service->deleteTransaction($id);

        return response()->json(['message' => 'Transaction deleted and journal entry reversed.']);
    }

    public function storeDeposit(Request $request)
    {
        $data = $request->validate([
            'cash_account_id' => 'required|integer|exists:accounts,id',
            'bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'deposit_date'    => 'required|date',
            'reference'       => 'nullable|string|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        $deposit = $this->service->createBankDeposit($data);

        return response()->json(['message' => 'Bank deposit recorded.', 'data' => $deposit]);
    }

    public function destroyDeposit(int $id)
    {
        $this->service->deleteBankDeposit($id);

        return response()->json(['message' => 'Deposit deleted and journal entry reversed.']);
    }

    private function buildCashPosition($bankAccounts): array
    {
        if (!Schema::hasTable('accounts') || !Schema::hasTable('journal_entry_lines')) {
            return [
                'bank_balances'     => [],
                'petty_cash'        => [],
                'total_bank'        => 0,
                'total_petty_cash'  => 0,
                'total_cash'        => 0,
                'data_ready'        => false,
            ];
        }

        $bankBalances = $bankAccounts->map(function ($ba) {
            $account = Account::where('gl_code', $ba->gl_code)->first();
            $balance = 0;
            if ($account) {
                $debit  = (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'debit')->sum('amount');
                $credit = (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'credit')->sum('amount');
                $balance = round($debit - $credit, 2);
            }
            return [
                'id'               => $ba->id,
                'bank_name'        => $ba->bank_name,
                'account_name'     => $ba->account_name,
                'gl_code'          => $ba->gl_code,
                'balance'          => $balance,
                'balance_formatted'=> '₱' . number_format($balance, 2),
                'has_account'      => $account !== null,
            ];
        })->values()->all();

        $pettyCash = PettyCashFund::active()->get()->map(fn ($f) => [
            'id'               => $f->id,
            'name'             => $f->name,
            'gl_code'          => $f->gl_code,
            'balance'          => (float) $f->balance,
            'balance_formatted'=> '₱' . number_format($f->balance, 2),
        ])->values()->all();

        $totalBank      = round(array_sum(array_column($bankBalances, 'balance')), 2);
        $totalPettyCash = round(array_sum(array_column($pettyCash, 'balance')), 2);

        return [
            'bank_balances'    => $bankBalances,
            'petty_cash'       => $pettyCash,
            'total_bank'       => $totalBank,
            'total_petty_cash' => $totalPettyCash,
            'total_cash'       => round($totalBank + $totalPettyCash, 2),
            'data_ready'       => true,
        ];
    }

    private function buildStats(): array
    {
        return [];
    }
}
