<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\BankDeposit;
use App\Models\BankWithdrawal;
use App\Models\FundTransfer;
use App\Models\JournalEntryLine;
use App\Models\ListStatus;
use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\Remittance;
use App\Services\NotificationService;
use App\Services\SeriesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashManagementService
{
    public function __construct(
        private SeriesService $series,
        private JournalEntryService $journal,
        private NotificationService $notificationService,
    ) {}

    // ── Fund Transfers ────────────────────────────────────────────────

    public function createFundTransfer(array $data): FundTransfer
    {
        return DB::transaction(function () use ($data) {
            $transfer = FundTransfer::create([
                'transfer_no' => $this->series->get('fund_transfer_no'),
                'transfer_date' => $data['transfer_date'],
                'from_bank_account_id' => $data['from_bank_account_id'],
                'to_bank_account_id' => $data['to_bank_account_id'],
                'amount' => round((float) $data['amount'], 2),
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_id' => Auth::id(),
            ]);

            $transfer->load(['fromBankAccount', 'toBankAccount', 'createdBy']);
            $this->journal->recordFundTransferEntry($transfer);

            return $transfer;
        });
    }

    public function deleteFundTransfer(int $id): void
    {
        DB::transaction(function () use ($id) {
            $transfer = FundTransfer::with(['fromBankAccount', 'toBankAccount'])->findOrFail($id);
            $this->journal->reverseEntriesForSource($transfer, 'Fund transfer deleted.', now()->toDateString());
            $transfer->delete();
        });
    }

    // ── Bank Deposits ────────────────────────────────────────────────

    public function createBankDeposit(array $data): BankDeposit
    {
        return DB::transaction(function () use ($data) {
            $isCheck = ($data['deposit_type'] ?? BankDeposit::TYPE_CASH) === BankDeposit::TYPE_CHECK;

            $deposit = BankDeposit::create([
                'deposit_no' => $this->series->get('bank_deposit_no'),
                'cash_account_id' => $data['cash_account_id'],
                'bank_account_id' => $data['bank_account_id'],
                'deposit_type' => $isCheck ? BankDeposit::TYPE_CHECK : BankDeposit::TYPE_CASH,
                'amount' => round((float) $data['amount'], 2),
                'deposit_date' => $data['deposit_date'],
                'check_date' => $isCheck ? ($data['check_date'] ?? null) : null,
                'check_number' => $isCheck ? ($data['check_number'] ?? null) : null,
                'status' => BankDeposit::STATUS_PENDING,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_id' => Auth::id(),
            ]);

            $deposit->load(['cashAccount', 'bankAccount', 'createdBy']);

            // Cash is good the moment it's deposited, so it posts right away.
            // A check is never posted on creation, not even one dated today
            // or earlier — a check can bounce regardless of the date written
            // on it, so only a person confirming it actually cleared may post
            // it. See CheckRegisterClass::markCleared(), which is what calls
            // postBankDeposit() for a confirmed check-type deposit;
            // deposits:post-due-checks never posts anything either.
            // A deposit is what says which of our accounts a customer's check
            // will reach, so the register learns it here — without it the
            // forecast cannot count the money as arriving anywhere.
            if ($isCheck) {
                app(\App\Services\Modules\CheckRegisterClass::class)->attributeToBank(
                    (string) ($data['check_number'] ?? ''),
                    round((float) $data['amount'], 2),
                    (int) $data['bank_account_id']
                );
            }

            if (!$isCheck) {
                $this->postBankDeposit($deposit);
            }

            if (!empty($data['remittance_ids'])) {
                $liquidatedStatusId = ListStatus::getBySlug('liquidated')?->id;
                Remittance::whereIn('id', $data['remittance_ids'])
                    ->where('status_id', $liquidatedStatusId)
                    ->whereNull('bank_deposit_id')
                    ->update(['bank_deposit_id' => $deposit->id]);
            }

            return $deposit;
        });
    }

    /**
     * Writes the DR Bank / CR Cash entry and marks the deposit posted. Safe to
     * call twice — a posted deposit is left alone, so calling this from both
     * deposit creation (when already due) and check confirmation cannot
     * double-post.
     */
    public function postBankDeposit(BankDeposit $deposit): BankDeposit
    {
        if ($deposit->status === BankDeposit::STATUS_POSTED) {
            return $deposit;
        }

        $deposit->loadMissing(['cashAccount', 'bankAccount']);
        $this->journal->recordBankDepositEntry($deposit);

        $deposit->forceFill([
            'status' => BankDeposit::STATUS_POSTED,
            'posted_at' => now(),
        ])->save();

        return $deposit;
    }

    public function deleteBankDeposit(int $id): void
    {
        DB::transaction(function () use ($id) {
            $deposit = BankDeposit::with(['cashAccount', 'bankAccount'])->findOrFail($id);
            // A pending check never posted an entry, so there is nothing to reverse.
            if ($deposit->status === BankDeposit::STATUS_POSTED) {
                $this->journal->reverseEntriesForSource($deposit, 'Bank deposit deleted.', now()->toDateString());
            }
            Remittance::where('bank_deposit_id', $id)->update(['bank_deposit_id' => null]);
            $deposit->delete();
        });
    }

    // ── Bank Withdrawals ─────────────────────────────────────────────

    public function createBankWithdrawal(array $data): BankWithdrawal
    {
        return DB::transaction(function () use ($data) {
            $isCheck = ($data['withdrawal_method'] ?? BankWithdrawal::METHOD_SLIP) === BankWithdrawal::METHOD_CHECK;

            if ($isCheck && blank($data['check_date'] ?? null)) {
                throw ValidationException::withMessages([
                    'check_date' => 'Enter the date written on the check — it is the day the money leaves the account.',
                ]);
            }

            $withdrawal = BankWithdrawal::create([
                'withdrawal_no' => $this->series->get('bank_withdrawal_no'),
                'bank_account_id' => $data['bank_account_id'],
                'cash_account_id' => $data['cash_account_id'],
                'withdrawal_method' => $isCheck ? BankWithdrawal::METHOD_CHECK : BankWithdrawal::METHOD_SLIP,
                'amount' => round((float) $data['amount'], 2),
                'withdrawal_date' => $data['withdrawal_date'],
                'check_number' => $isCheck ? ($data['check_number'] ?? null) : null,
                'check_date' => $isCheck ? ($data['check_date'] ?? null) : null,
                'status' => BankWithdrawal::STATUS_PENDING,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_id' => Auth::id(),
            ]);

            $withdrawal->load(['bankAccount', 'cashAccount', 'createdBy']);

            // A check written to withdraw cash empties the account when it is
            // presented, not when it is written. Hold it in the register until
            // someone confirms it cleared; a slip is cash in hand right away.
            if ($isCheck) {
                app(\App\Services\Modules\CheckRegisterClass::class)->registerWithdrawal($withdrawal);
            } else {
                $this->postBankWithdrawal($withdrawal);
            }

            return $withdrawal;
        });
    }

    /**
     * Writes DR Cash / CR Bank and marks the withdrawal posted. Safe to call
     * twice, so clearing a check cannot double-post.
     */
    public function postBankWithdrawal(BankWithdrawal $withdrawal): BankWithdrawal
    {
        if ($withdrawal->status === BankWithdrawal::STATUS_POSTED) {
            return $withdrawal;
        }

        $withdrawal->loadMissing(['bankAccount', 'cashAccount']);
        $this->journal->recordBankWithdrawalEntry($withdrawal);

        $withdrawal->forceFill([
            'status' => BankWithdrawal::STATUS_POSTED,
            'posted_at' => now(),
        ])->save();

        return $withdrawal;
    }

    public function deleteBankWithdrawal(int $id): void
    {
        DB::transaction(function () use ($id) {
            $withdrawal = BankWithdrawal::with(['bankAccount', 'cashAccount'])->findOrFail($id);
            $this->journal->reverseEntriesForSource($withdrawal, 'Bank withdrawal deleted.', now()->toDateString());
            $withdrawal->delete();
        });
    }

    // ── Petty Cash ───────────────────────────────────────────────────

    public function createFund(array $data): PettyCashFund
    {
        return DB::transaction(function () use ($data) {
            $initialBalance = round((float) ($data['initial_balance'] ?? 0), 2);

            $fund = PettyCashFund::create([
                'name'          => $data['name'],
                'gl_code'       => $data['gl_code'] ?? $this->generateFundGlCode(),
                'balance'       => $initialBalance,
                'fixed_amount'  => $initialBalance,
                'custodian_id'  => $data['custodian_id'] ?? null,
                'low_balance_threshold' => $data['low_balance_threshold'] ?? null,
                'is_active'     => true,
                'created_by_id' => Auth::id(),
            ]);

            if ($initialBalance > 0) {
                $this->journal->recordFundCapitalization($fund, $initialBalance, $data['bank_account_id'] ?? null);
            }

            return $fund;
        });
    }

    public function addTransaction(PettyCashFund $fund, array $data): PettyCashTransaction
    {
        return DB::transaction(function () use ($fund, $data) {
            $amount = round((float) $data['amount'], 2);
            $type = $data['type'];

            $txn = PettyCashTransaction::create([
                'transaction_no' => $this->series->get('petty_cash_txn_no'),
                'fund_id' => $fund->id,
                'type' => $type,
                'amount' => $amount,
                'category' => $data['category'] ?? null,
                'description' => $data['description'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'reference_number' => $data['reference_number'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'created_by_id' => Auth::id(),
            ]);

            if ($type === 'replenishment') {
                $fund->increment('balance', $amount);
                $txn->load(['fund', 'bankAccount']);
                $this->journal->recordPettyCashReplenishment($txn);
            } else {
                $previousBalance = (float) $fund->balance;
                $fund->decrement('balance', $amount);
                $newBalance = $previousBalance - $amount;
                $txn->load(['fund']);
                $this->journal->recordPettyCashDisbursement($txn);
                $this->notificationService->checkAndNotifyLowBalance($fund, $previousBalance, $newBalance);
            }

            return $txn->fresh(['fund', 'bankAccount', 'createdBy']);
        });
    }

    public function adjustFundBalance(PettyCashFund $fund, float $newBalance, string $reason): ?PettyCashTransaction
    {
        return DB::transaction(function () use ($fund, $newBalance, $reason) {
            $previousBalance = (float) $fund->balance;
            $delta = round($newBalance - $previousBalance, 2);

            if ($delta === 0.0) {
                return null;
            }

            $txn = PettyCashTransaction::create([
                'transaction_no' => $this->series->get('petty_cash_txn_no'),
                'fund_id' => $fund->id,
                'type' => $delta > 0 ? 'replenishment' : 'disbursement',
                'amount' => abs($delta),
                'category' => 'cash_count_adjustment',
                'description' => $reason,
                'transaction_date' => now()->toDateString(),
                'created_by_id' => Auth::id(),
            ]);

            $fund->update(['balance' => $newBalance]);
            $txn->load('fund');
            $this->journal->recordPettyCashAdjustment($txn, $delta);
            $this->notificationService->checkAndNotifyLowBalance($fund, $previousBalance, $newBalance);

            return $txn->fresh(['fund', 'createdBy']);
        });
    }

    public function deleteTransaction(int $id): void
    {
        DB::transaction(function () use ($id) {
            $txn = PettyCashTransaction::with('fund')->findOrFail($id);

            $this->journal->reverseEntriesForSource($txn, 'Petty cash transaction deleted.', now()->toDateString());

            if ($txn->type === 'replenishment') {
                $previousBalance = (float) $txn->fund->balance;
                $txn->fund->decrement('balance', $txn->amount);
                $newBalance = $previousBalance - (float) $txn->amount;
                $this->notificationService->checkAndNotifyLowBalance($txn->fund, $previousBalance, $newBalance);
            } else {
                $txn->fund->increment('balance', $txn->amount);
            }

            $txn->delete();
        });
    }

    public function getCashOnHandBalance(): float
    {
        $cashAccount = Account::where('slug', 'cash')->first() ?? Account::where('code', '1000')->first();
        if (!$cashAccount) {
            return 0.0;
        }

        $debit = (float) JournalEntryLine::where('account_id', $cashAccount->id)->where('line_type', 'debit')->sum('amount');
        $credit = (float) JournalEntryLine::where('account_id', $cashAccount->id)->where('line_type', 'credit')->sum('amount');

        return round($debit - $credit, 2);
    }

    public function getAccountBalance(int $accountId): float
    {
        $debit = (float) JournalEntryLine::where('account_id', $accountId)->where('line_type', 'debit')->sum('amount');
        $credit = (float) JournalEntryLine::where('account_id', $accountId)->where('line_type', 'credit')->sum('amount');

        return round($debit - $credit, 2);
    }

    /**
     * Cash still on hand once pending check deposits are accounted for. Those
     * checks have left the drawer but have not credited the ledger yet, so
     * spending against the raw balance would let several of them each pass the
     * guard while together overdrawing the account.
     */
    public function getAvailableCashBalance(int $accountId): float
    {
        $pending = (float) BankDeposit::where('cash_account_id', $accountId)
            ->where('status', BankDeposit::STATUS_PENDING)
            ->sum('amount');

        return round($this->getAccountBalance($accountId) - $pending, 2);
    }

    /**
     * What an account can actually cover, once checks drawn on it are counted.
     *
     * A written check has not left the ledger yet, so the raw balance still
     * shows that money. Spending against it is how an account ends up honouring
     * one check and bouncing another.
     */
    public function getAvailableBankBalance(int $bankAccountId): float
    {
        $committed = (float) \App\Models\Check::query()
            ->where('status', \App\Models\Check::STATUS_PENDING)
            ->where('direction', \App\Models\Check::DIRECTION_ISSUED)
            ->where('bank_account_id', $bankAccountId)
            ->sum('amount');

        return round($this->getBankAccountBalance($bankAccountId) - $committed, 2);
    }

    public function getCashInBankBalance(): float
    {
        $account = Account::where('slug', 'cash_in_bank')->first() ?? Account::where('code', '1011')->first();
        if (!$account) {
            return 0.0;
        }

        $debit = (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'debit')->sum('amount');
        $credit = (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'credit')->sum('amount');

        return round($debit - $credit, 2);
    }

    public function getBankAccountBalance(int $bankAccountId): float
    {
        $bankAccount = \App\Models\BankAccount::find($bankAccountId);
        if (!$bankAccount || !$bankAccount->gl_code) {
            return 0.0;
        }

        $account = Account::where('code', $bankAccount->gl_code)->first();
        if (!$account) {
            return 0.0;
        }

        $debit = (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'debit')->sum('amount');
        $credit = (float) JournalEntryLine::where('account_id', $account->id)->where('line_type', 'credit')->sum('amount');

        return round($debit - $credit, 2);
    }

    private function generateFundGlCode(): string
    {
        $lastNumber = PettyCashFund::where('gl_code', 'like', 'PCF-%')
            ->get()
            ->map(fn ($f) => (int) substr($f->gl_code, 4))
            ->max() ?? 0;

        return 'PCF-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }
}
