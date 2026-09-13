<?php

namespace App\Services\Modules;

use App\Models\BankDeposit;
use App\Models\Check;
use App\Models\Receipt;
use App\Models\ReceivedStockPayment;
use App\Models\User;
use App\Notifications\BouncedCheckNotification;
use App\Services\Accounting\CashManagementService;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates register rows. Deliberately does no posting: a pending check moves no
 * money, and clearing is handled by the services that own each ledger.
 */
class CheckRegisterClass
{
    public function registerReceived(Receipt $receipt, array $attributes = []): Check
    {
        $receipt->loadMissing('arInvoice.sales_order');

        $checkNumber = $attributes['check_number'] ?? $receipt->reference_number;

        if (trim((string) $checkNumber) === '') {
            throw ValidationException::withMessages([
                'check_number' => 'A check needs its number before it can be entered in the register.',
            ]);
        }

        return Check::create(array_merge([
            'direction' => Check::DIRECTION_RECEIVED,
            'check_number' => $checkNumber,
            'check_date' => $receipt->check_date ?: $receipt->receipt_date,
            'amount' => $receipt->amount_paid,
            'bank_name' => $receipt->bank_name,
            'source_type' => Receipt::class,
            'source_id' => $receipt->id,
            'customer_id' => $receipt->customer_id,
            'received_by_id' => $receipt->arInvoice?->sales_order?->sales_rep_id,
            'status' => Check::STATUS_PENDING,
        ], $attributes));
    }

    /**
     * Push a corrected check date onto the register row for a receipt.
     *
     * The register is authoritative for maturity, so a date fixed anywhere else
     * has to reach it — otherwise the row keeps the original date and the
     * forecast reads a maturity that is no longer true. Silent when there is no
     * register row: older check receipts pre-date the register.
     */
    public function syncCheckDate(Receipt $receipt, $checkDate): void
    {
        if (blank($checkDate)) {
            return;
        }

        Check::where('source_type', Receipt::class)
            ->where('source_id', $receipt->id)
            ->update(['check_date' => $checkDate]);
    }

    public function registerIssued(ReceivedStockPayment $payment, array $attributes = []): Check
    {
        $payment->loadMissing('receivedStock');

        $checkNumber = $attributes['check_number'] ?? $payment->reference_number;

        if (trim((string) $checkNumber) === '') {
            throw ValidationException::withMessages([
                'check_number' => 'A check needs its number before it can be entered in the register.',
            ]);
        }

        // A given bank account cannot issue the same check number twice. A
        // bounced or cleared check still blocks reuse of its number: a
        // bounced check is normally replaced with a different physical check
        // carrying its own number, while the bounced number itself was still
        // spent (written, then dishonored) and re-registering it as a new
        // check would let two physical instruments share one number. Only
        // this direction is checked — bank_account_id is null for received
        // checks, so a duplicate check number across incoming customer
        // checks is expected and not an error.
        $duplicate = Check::issued()
            ->where('check_number', $checkNumber)
            ->where('bank_account_id', $payment->bank_account_id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'check_number' => "Check {$checkNumber} has already been issued from this account.",
            ]);
        }

        return Check::create(array_merge([
            'direction' => Check::DIRECTION_ISSUED,
            'check_number' => $checkNumber,
            'check_date' => $payment->payment_date,
            'amount' => $payment->amount_paid,
            'bank_name' => $payment->bank_name,
            'bank_account_id' => $payment->bank_account_id,
            'source_type' => ReceivedStockPayment::class,
            'source_id' => $payment->id,
            'supplier_id' => $payment->receivedStock?->supplier_id,
            'status' => Check::STATUS_PENDING,
        ], $attributes));
    }

    public function markCleared(Check $check): Check
    {
        $this->assertPending($check);

        DB::transaction(function () use ($check) {
            if ($check->direction === Check::DIRECTION_ISSUED) {
                $payment = ReceivedStockPayment::with('receivedStock')->findOrFail($check->source_id);
                app(JournalEntryService::class)->postClearedSupplierCheck($payment->receivedStock, $payment, $check->check_date);
            } else {
                app(ArInvoiceClass::class)->confirmCheck($check->source_id, $check->bank_name, $check->check_date?->toDateString());
            }

            // Spec: "a check-type bank deposit stays pending until its underlying
            // check is confirmed cleared, and confirming the check is what posts
            // the deposit." Without this the two pending states drift apart.
            //
            // Matched on check_number AND amount, not check_number alone: a
            // BankDeposit carries no FK back to the Check (or to the customer),
            // and check numbers are only unique per issuing bank — two
            // different customers' checks could coincidentally share a number.
            // Requiring the amount to match too makes a false match on an
            // unrelated deposit implausible without eliminating the risk
            // entirely, which would need a real link between the two tables.
            //
            // Still not airtight, so this fails safe rather than guessing: if
            // more than one pending deposit matches, posting any one of them
            // could move the wrong customer's money, so none are posted and
            // the whole confirmation is refused (rolling back the receipt
            // confirmation above too) until a person resolves the ambiguity.
            if ($check->direction === Check::DIRECTION_RECEIVED) {
                $matches = BankDeposit::where('deposit_type', BankDeposit::TYPE_CHECK)
                    ->where('status', BankDeposit::STATUS_PENDING)
                    ->where('check_number', $check->check_number)
                    ->where('amount', $check->amount)
                    ->get();

                if ($matches->count() > 1) {
                    throw ValidationException::withMessages([
                        'check_number' => "Multiple pending bank deposits match check {$check->check_number} for this amount. Resolve the ambiguity before confirming — posting could move the wrong customer's money.",
                    ]);
                }

                $matches->first() && app(CashManagementService::class)->postBankDeposit($matches->first());
            }

            $check->update([
                'status' => Check::STATUS_CLEARED,
                'cleared_at' => now(),
                'cleared_by_id' => Auth::id(),
            ]);
        });

        return $check;
    }

    public function markBounced(Check $check, string $reason): Check
    {
        $this->assertPending($check);

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'bounce_reason' => 'Say why the check bounced — the rep needs it to chase the customer.',
            ]);
        }

        $check->update([
            'status' => Check::STATUS_BOUNCED,
            'bounced_at' => now(),
            'bounced_by_id' => Auth::id(),
            'bounce_reason' => trim($reason),
        ]);

        // Nothing posts and no balance changes: a bounced check never was money.
        $this->notifyReceivingRep($check);

        return $check;
    }

    private function assertPending(Check $check): void
    {
        if (!$check->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending check can be cleared or bounced. This one is ' . $check->status . '.',
            ]);
        }
    }

    private function notifyReceivingRep(Check $check): void
    {
        // `checks.received_by_id` is an employees.id (see the FK in
        // create_checks_table). Users don't carry an employee_id column —
        // it's the other way round: User::employee() is hasOne(Employee,
        // 'user_id'), i.e. the FK lives on employees.user_id. So look up
        // the User by walking that relation from the Employee side.
        $user = $check->received_by_id
            ? User::whereHas('employee', fn ($q) => $q->where('id', $check->received_by_id))->first()
            : null;

        $user?->notify(new BouncedCheckNotification($check));
    }
}
