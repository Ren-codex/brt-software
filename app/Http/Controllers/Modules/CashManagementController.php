<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankDeposit;
use App\Models\BankWithdrawal;
use App\Models\FundTransfer;
use App\Models\JournalEntryLine;
use App\Models\PettyCashFund;
use App\Models\Remittance;
use App\Services\Accounting\CashManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CashManagementController extends Controller
{
    public function __construct(private CashManagementService $service) {}

    public function cashOnHand()
    {
        $balance = $this->service->getCashOnHandBalance();

        return response()->json([
            'balance'           => $balance,
            'balance_formatted' => '₱' . number_format($balance, 2),
        ]);
    }

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

        $funds = PettyCashFund::with(['transactions' => fn($q) => $q->orderByDesc('transaction_date')->orderByDesc('id')])
            ->orderBy('name')
            ->get()
            ->map(function ($f) {
                return [
                    'id'               => $f->id,
                    'name'             => $f->name,
                    'gl_code'          => $f->gl_code,
                    'balance'          => (float) $f->balance,
                    'balance_formatted'=> '₱' . number_format($f->balance, 2),
                    'is_active'        => $f->is_active,
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
                // Exclude "Cash in Bank" control accounts — those represent money
                // already deposited, not a valid source for a NEW bank deposit.
                ->where('name', 'not like', '%Cash in Bank%')
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'subtype'])
                ->map(function ($a) {
                    $a->balance = $this->service->getAccountBalance($a->id);
                    $a->balance_formatted = '₱' . number_format($a->balance, 2);
                    return $a;
                })
            : collect();

        $deposits = BankDeposit::with(['cashAccount', 'bankAccount', 'createdBy', 'remittances.createdBy.employee'])
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
                'remittances'      => $d->remittances->map(fn($r) => [
                    'id'             => $r->id,
                    'remittance_no'  => $r->remittance_no,
                    'rep_name'       => optional(optional($r->createdBy)->employee)->fullname,
                    'amount'         => (float) ($r->received_amount ?? $r->total_amount),
                ])->values(),
            ]);

        $withdrawals = BankWithdrawal::with(['cashAccount', 'bankAccount', 'createdBy'])
            ->orderByDesc('withdrawal_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($w) => [
                'id'                => $w->id,
                'withdrawal_no'     => $w->withdrawal_no,
                'withdrawal_date'   => $w->withdrawal_date,
                'cash_account'      => optional($w->cashAccount)->name,
                'bank_name'         => optional($w->bankAccount)->bank_name . ' — ' . optional($w->bankAccount)->account_name,
                'amount'            => (float) $w->amount,
                'amount_formatted'  => '₱' . number_format($w->amount, 2),
                'reference'         => $w->reference,
                'notes'             => $w->notes,
                'created_by'        => optional($w->createdBy)->name,
                'created_at'        => $w->created_at?->toDateTimeString(),
            ]);

        $undepositedRemittances = Remittance::with(['createdBy.employee'])
            ->whereHas('status', fn ($q) => $q->where('slug', 'liquidated'))
            ->whereNull('bank_deposit_id')
            ->orderBy('remittance_date')
            ->get()
            ->map(fn($r) => [
                'id'                => $r->id,
                'remittance_no'     => $r->remittance_no,
                'remittance_date'   => $r->remittance_date,
                'rep_name'          => optional(optional($r->createdBy)->employee)->fullname ?? 'Unknown',
                'amount'            => (float) ($r->received_amount ?? $r->total_amount),
                'amount_formatted'  => '₱' . number_format($r->received_amount ?? $r->total_amount, 2),
            ])
            ->values();

        $totalTransferred  = FundTransfer::sum('amount');
        $transferCount     = FundTransfer::count();
        $fundCount         = PettyCashFund::count();
        $totalPettyCash    = PettyCashFund::sum('balance');
        $depositCount      = BankDeposit::count();
        $totalDeposited    = BankDeposit::sum('amount');
        $withdrawalCount   = BankWithdrawal::count();
        $totalWithdrawn    = BankWithdrawal::sum('amount');

        return inertia('Modules/Accounting/CashManagement', [
            'transfers'    => $transfers,
            'funds'        => $funds,
            'bankAccounts' => $bankAccounts,
            'cashAccounts' => $cashAccounts,
            'deposits'     => $deposits,
            'withdrawals'  => $withdrawals,
            'undepositedRemittances' => $undepositedRemittances,
            'cashPosition' => $cashPosition,
            'stats'        => $this->buildStats(),
            'summaryCards' => [
                ['title' => 'Fund Transfers',   'value' => '₱' . number_format($totalTransferred, 2), 'description' => $transferCount . ' transfer' . ($transferCount === 1 ? '' : 's') . ' recorded.',   'icon' => 'ri-exchange-dollar-line'],
                ['title' => 'Bank Deposits',    'value' => '₱' . number_format($totalDeposited, 2),   'description' => $depositCount . ' deposit' . ($depositCount === 1 ? '' : 's') . ' recorded.',     'icon' => 'ri-bank-card-2-line'],
                ['title' => 'Bank Withdrawals', 'value' => '₱' . number_format($totalWithdrawn, 2),   'description' => $withdrawalCount . ' withdrawal' . ($withdrawalCount === 1 ? '' : 's') . ' recorded.', 'icon' => 'ri-bank-card-line'],
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

        $amount = round((float) $data['amount'], 2);
        $fromBalance = $this->service->getBankAccountBalance((int) $data['from_bank_account_id']);
        if ($amount > $fromBalance) {
            return response()->json([
                'message' => 'Amount exceeds the source bank account\'s available balance (₱' . number_format($fromBalance, 2) . ').',
                'errors'  => ['amount' => ['Amount exceeds the source bank account\'s available balance (₱' . number_format($fromBalance, 2) . ').']],
            ], 422);
        }

        $transfer = $this->service->createFundTransfer($data);

        return response()->json(['message' => 'Transfer recorded.', 'data' => $transfer]);
    }

    public function destroyFundTransfer(int $id)
    {
        $this->service->deleteFundTransfer($id);

        return response()->json(['message' => 'Transfer deleted and journal entry reversed.']);
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
            'cash_account_id'    => 'required|integer|exists:accounts,id',
            'bank_account_id'    => 'required|integer|exists:bank_accounts,id',
            'amount'             => 'required|numeric|min:0.01',
            'deposit_date'       => 'required|date',
            'reference'          => 'nullable|string|max:100',
            'notes'              => 'nullable|string|max:500',
            'remittance_ids'     => 'nullable|array',
            'remittance_ids.*'   => 'integer|exists:remittances,id',
        ]);

        $amount = round((float) $data['amount'], 2);
        $sourceBalance = $this->service->getAccountBalance((int) $data['cash_account_id']);
        if ($amount > $sourceBalance) {
            return response()->json([
                'message' => 'Amount exceeds this cash account\'s available balance (₱' . number_format($sourceBalance, 2) . ').',
                'errors'  => ['amount' => ['Amount exceeds this cash account\'s available balance (₱' . number_format($sourceBalance, 2) . ').']],
            ], 422);
        }

        $deposit = $this->service->createBankDeposit($data);

        return response()->json(['message' => 'Bank deposit recorded.', 'data' => $deposit]);
    }

    public function destroyDeposit(int $id)
    {
        $this->service->deleteBankDeposit($id);

        return response()->json(['message' => 'Deposit deleted and journal entry reversed.']);
    }

    public function storeWithdrawal(Request $request)
    {
        $data = $request->validate([
            'bank_account_id'    => 'required|integer|exists:bank_accounts,id',
            'cash_account_id'    => 'required|integer|exists:accounts,id',
            'amount'             => 'required|numeric|min:0.01',
            'withdrawal_date'    => 'required|date',
            'reference'          => 'nullable|string|max:100',
            'notes'              => 'nullable|string|max:500',
        ]);

        $amount = round((float) $data['amount'], 2);
        $bankBalance = $this->service->getBankAccountBalance((int) $data['bank_account_id']);
        if ($amount > $bankBalance) {
            return response()->json([
                'message' => 'Amount exceeds this bank account\'s available balance (₱' . number_format($bankBalance, 2) . ').',
                'errors'  => ['amount' => ['Amount exceeds this bank account\'s available balance (₱' . number_format($bankBalance, 2) . ').']],
            ], 422);
        }

        $withdrawal = $this->service->createBankWithdrawal($data);

        return response()->json(['message' => 'Bank withdrawal recorded.', 'data' => $withdrawal]);
    }

    public function destroyWithdrawal(int $id)
    {
        $this->service->deleteBankWithdrawal($id);

        return response()->json(['message' => 'Withdrawal deleted and journal entry reversed.']);
    }

    private function buildCashPosition($bankAccounts): array
    {
        if (!Schema::hasTable('accounts') || !Schema::hasTable('journal_entry_lines')) {
            return [
                'bank_balances'     => [],
                'petty_cash'        => [],
                'cash_on_hand'      => 0,
                'total_bank'        => 0,
                'total_petty_cash'  => 0,
                'total_cash'        => 0,
                'data_ready'        => false,
            ];
        }

        $bankBalances = $bankAccounts->map(function ($ba) {
            $account = Account::where('code', $ba->gl_code)->first();
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

        $cashOnHand = $this->service->getCashOnHandBalance();

        $totalBank      = round(array_sum(array_column($bankBalances, 'balance')), 2);
        $totalPettyCash = round(array_sum(array_column($pettyCash, 'balance')), 2);

        return [
            'bank_balances'    => $bankBalances,
            'petty_cash'       => $pettyCash,
            'cash_on_hand'     => $cashOnHand,
            'total_bank'       => $totalBank,
            'total_petty_cash' => $totalPettyCash,
            'total_cash'       => round($totalBank + $totalPettyCash + $cashOnHand, 2),
            'data_ready'       => true,
        ];
    }

    private function buildStats(): array
    {
        return [];
    }
}
