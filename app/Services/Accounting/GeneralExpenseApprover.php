<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Approving a general expense, in one place.
 *
 * This was inline in GeneralExpenseController::approve(). It moved here when the
 * unified approvals page gained the ability to approve an expense too: the
 * balance checks below are the difference between posting a journal entry the
 * books can carry and one that drives an account negative, so they must run on
 * both paths rather than being duplicated and left to drift apart.
 *
 * Failures are thrown as ValidationException so the 422 and its message reach
 * the caller unchanged, whichever route it came in on.
 */
class GeneralExpenseApprover
{
    public function __construct(
        private JournalEntryService $journal,
        private CashManagementService $cashManagement,
    ) {}

    /**
     * @throws ValidationException when a balance rule refuses the approval
     */
    public function approve(int $id): Expense
    {
        $expense = Expense::whereNull('fund_id')->findOrFail($id);

        if ($expense->status !== 'draft') {
            $this->refuse('Only draft expenses can be approved.');
        }

        $amount = round((float) $expense->amount, 2);

        if ($expense->payment_method === 'cash') {
            $cashOnHand = $this->cashManagement->getCashOnHandBalance();
            if ($amount > $cashOnHand) {
                $this->refuse('Amount exceeds available Cash on Hand (₱'.number_format($cashOnHand, 2).'). Reduce the amount or use Bank Transfer instead.');
            }
        }

        if (in_array($expense->payment_method, ['check', 'bank_transfer'], true)) {
            if ($expense->bank_account_id) {
                $bankBalance = $this->cashManagement->getBankAccountBalance($expense->bank_account_id);
                if ($amount > $bankBalance) {
                    $this->refuse('Amount exceeds this bank account\'s available balance (₱'.number_format($bankBalance, 2).').');
                }
            } else {
                $cashInBank = $this->cashManagement->getCashInBankBalance();
                if ($amount > $cashInBank) {
                    $this->refuse('Amount exceeds available Cash in Bank (₱'.number_format($cashInBank, 2).'). Reduce the amount or select a specific bank account with sufficient balance.');
                }
            }
        }

        $expense->update([
            'status' => 'approved',
            'approved_by_id' => Auth::id(),
            'approved_at' => now(),
        ]);

        $this->journal->recordGeneralExpenseEntry($expense->fresh(['glAccount', 'bankAccount']));

        return $expense->fresh(['glAccount', 'bankAccount', 'added_by', 'approvedBy']);
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['approve' => [$message]]);
    }
}
