<?php

namespace App\Services\Accounting;

use App\Models\BankDeposit;
use App\Models\FundTransfer;
use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Services\NotificationService;
use App\Services\SeriesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            $deposit = BankDeposit::create([
                'deposit_no' => $this->series->get('bank_deposit_no'),
                'cash_account_id' => $data['cash_account_id'],
                'bank_account_id' => $data['bank_account_id'],
                'amount' => round((float) $data['amount'], 2),
                'deposit_date' => $data['deposit_date'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_id' => Auth::id(),
            ]);

            $deposit->load(['cashAccount', 'bankAccount', 'createdBy']);
            $this->journal->recordBankDepositEntry($deposit);

            return $deposit;
        });
    }

    public function deleteBankDeposit(int $id): void
    {
        DB::transaction(function () use ($id) {
            $deposit = BankDeposit::with(['cashAccount', 'bankAccount'])->findOrFail($id);
            $this->journal->reverseEntriesForSource($deposit, 'Bank deposit deleted.', now()->toDateString());
            $deposit->delete();
        });
    }

    // ── Petty Cash ───────────────────────────────────────────────────

    public function createFund(array $data): PettyCashFund
    {
        return DB::transaction(function () use ($data) {
            $initialBalance = round((float) ($data['initial_balance'] ?? 0), 2);

            $fund = PettyCashFund::create([
                'name'          => $data['name'],
                'gl_code'       => $data['gl_code'],
                'balance'       => $initialBalance,
                'fixed_amount'  => $initialBalance,
                'custodian_id'  => $data['custodian_id'] ?? null,
                'weekly_budget' => $data['weekly_budget'] ?? 0,
                'is_active'     => true,
                'created_by_id' => Auth::id(),
            ]);

            if ($initialBalance > 0) {
                $this->journal->recordFundCapitalization($fund, $initialBalance);
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
}
