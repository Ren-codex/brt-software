<?php

namespace App\Services\Modules;

use App\Models\BankDeposit;
use App\Models\BankWithdrawal;
use App\Models\Check;
use App\Models\Receipt;
use App\Models\Remittance;
use App\Models\ReceivedStockPayment;
use App\Models\User;
use App\Notifications\BouncedCheckNotification;
use App\Services\Accounting\CashManagementService;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates register rows. Deliberately does no posting: a pending check moves no
 * money, and clearing is handled by the services that own each ledger.
 */
class CheckRegisterClass
{
    /**
     * The register, newest maturity first.
     *
     * `$ownEmployeeId` scopes the list to one rep's own received checks. Passing
     * it is how the Sales-side view stays read-only and personal: a rep sees the
     * checks they took and nothing else, and never the issued side at all.
     */
    public function lists(array $filters = [], ?int $ownEmployeeId = null)
    {
        return $this->visibleChecks($ownEmployeeId)
            ->with(['customer', 'supplier', 'receivedBy', 'bankAccount'])
            ->when($filters['direction'] ?? null, fn ($q, $d) => $q->where('direction', $d))
            ->when($filters['status'] ?? null, fn ($q, $st) => $q->where('status', $st))
            ->when($filters['keyword'] ?? null, fn ($q, $kw) => $q->where(function ($inner) use ($kw) {
                // The customer or supplier by name too: that is how people
                // remember a cheque, more often than by its number.
                $inner->where('check_number', 'LIKE', "%{$kw}%")
                    ->orWhere('bank_name', 'LIKE', "%{$kw}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'LIKE', "%{$kw}%"))
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'LIKE', "%{$kw}%"));
            }))
            ->orderBy('check_date')
            ->orderByDesc('id')
            // Replaces the order above when the screen asks for a sort.
            ->sortFromRequest()
            ->paginate($filters['count'] ?? 15);
    }

    /**
     * How many cheques are at each status, and what they add up to — for the
     * totals at the top of the Sales check monitoring screen. Scoped exactly as
     * lists() is, so the cards and the table always describe the same cheques.
     */
    public function summary(?int $ownEmployeeId = null): array
    {
        $rows = $this->visibleChecks($ownEmployeeId)
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $summary = [];
        foreach ([Check::STATUS_PENDING, Check::STATUS_CLEARED, Check::STATUS_BOUNCED] as $status) {
            $summary[$status] = [
                'count' => (int) ($rows[$status]->total ?? 0),
                'amount' => (float) ($rows[$status]->amount ?? 0),
            ];
        }

        return $summary;
    }

    /** The cheques a caller may see: everything, or one rep's own received ones. */
    private function visibleChecks(?int $ownEmployeeId)
    {
        return Check::query()
            ->when($ownEmployeeId, fn ($q) => $q
                ->where('direction', Check::DIRECTION_RECEIVED)
                ->where('received_by_id', $ownEmployeeId));
    }

    /**
     * Bank transfers a driver reported that nobody has matched in the bank yet.
     *
     * These are not register rows and deliberately never become any: a check
     * has a maturity date and the forecast sums pending checks by it, while a
     * transfer either arrived or did not. Writing one into `checks` would show
     * it falling due on the day it was collected and inflate the forecast by
     * its amount.
     *
     * They still have to be confirmable somewhere, though, because until
     * someone says the money landed the invoice holds the balance open and the
     * ledger posts nothing — so they are listed alongside the register and
     * confirmed through the same screen.
     */
    public function fieldTransfersAwaitingConfirmation(array $filters = [])
    {
        $keyword = $filters['keyword'] ?? null;

        return Receipt::query()
            ->with(['customer', 'arInvoice.sales_order.salesRep', 'heldBy', 'registerCheck'])
            ->whereNull('confirmed_at')
            ->where('receipt_type', 'payment')
            ->whereRaw('LOWER(TRIM(payment_mode)) = ?', ['bank transfer'])
            ->when($keyword, fn ($q, $kw) => $q->where(function ($inner) use ($kw) {
                $inner->where('reference_number', 'LIKE', "%{$kw}%")
                    ->orWhere('receipt_number', 'LIKE', "%{$kw}%");
            }))
            ->orderBy('receipt_date')
            ->get()
            // One definition of "awaiting the bank" for the whole system, on
            // the Receipt itself — a transfer waits only on a COD order.
            ->filter(fn (Receipt $receipt) => $receipt->isUnclearedFor(
                optional(optional($receipt->arInvoice)->sales_order)->payment_mode
            ))
            ->map(function (Receipt $receipt) {
                // receipt_date is not cast on the model, so it arrives as a
                // string and has to be parsed rather than treated as a date.
                $collectedAt = $receipt->receipt_date ? Carbon::parse($receipt->receipt_date) : null;

                return [
                    'receipt_id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'reference_number' => $receipt->reference_number,
                    'customer' => optional($receipt->customer)->name,
                    'so_number' => optional(optional($receipt->arInvoice)->sales_order)->so_number,
                    'amount' => (float) $receipt->amount_paid,
                    'collected_at' => $collectedAt?->toDateString(),
                    'days_waiting' => $collectedAt
                        ? (int) $collectedAt->startOfDay()->diffInDays(now()->startOfDay())
                        : null,
                    'collected_by' => optional($receipt->heldBy)->fullname
                        ?? optional(optional(optional($receipt->arInvoice)->sales_order)->salesRep)->fullname,
                ];
            })
            ->values();
    }

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
    /**
     * Record which bank a received check will land in.
     *
     * A customer's check carries the drawee bank, not ours, so until it is
     * deposited nothing says which of our accounts it will reach — and the
     * forecast cannot credit an account it is only guessing at. Depositing it is
     * the moment that becomes known.
     */
    public function attributeToBank(string $checkNumber, float $amount, ?int $bankAccountId): void
    {
        if (!$bankAccountId || trim($checkNumber) === '') {
            return;
        }

        Check::where('direction', Check::DIRECTION_RECEIVED)
            ->where('status', Check::STATUS_PENDING)
            ->where('check_number', $checkNumber)
            ->where('amount', $amount)
            ->whereNull('bank_account_id')
            ->update(['bank_account_id' => $bankAccountId]);
    }

    public function syncCheckDate(Receipt $receipt, $checkDate): void
    {
        if (blank($checkDate)) {
            return;
        }

        Check::where('source_type', Receipt::class)
            ->where('source_id', $receipt->id)
            ->update(['check_date' => $checkDate]);
    }

    /**
     * A check written to withdraw cash. It draws on one of our accounts and
     * empties it when presented, so it is an issued check like any other — the
     * counterparty is simply ourselves.
     */
    public function registerWithdrawal(BankWithdrawal $withdrawal, array $attributes = []): Check
    {
        $number = $attributes['check_number'] ?? $withdrawal->check_number;

        if (trim((string) $number) === '') {
            throw ValidationException::withMessages([
                'check_number' => 'A check needs its number before it can be entered in the register.',
            ]);
        }

        return Check::create(array_merge([
            'direction' => Check::DIRECTION_ISSUED,
            'check_number' => $number,
            'check_date' => $withdrawal->check_date ?: $withdrawal->withdrawal_date,
            'amount' => $withdrawal->amount,
            'bank_account_id' => $withdrawal->bank_account_id,
            'source_type' => BankWithdrawal::class,
            'source_id' => $withdrawal->id,
            'status' => Check::STATUS_PENDING,
            'notes' => 'Cash withdrawal ' . $withdrawal->withdrawal_no . '.',
        ], $attributes));
    }

    public function registerIssued(ReceivedStockPayment $payment, array $attributes = []): Check
    {
        $payment->loadMissing('receivedStock');

        // A payment made any other way has already posted its own ledger entry,
        // so entering it here would let markCleared() post it a second time.
        // Only a check is held back from posting in the first place.
        if (strcasecmp(trim((string) $payment->payment_mode), 'Check') !== 0) {
            throw ValidationException::withMessages([
                'payment_mode' => 'Only a check payment belongs in the check register.',
            ]);
        }

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

    /**
     * $bankName fills in a bank the cheque never had.
     *
     * Clearing cannot proceed without one — the amount has to be posted
     * somewhere — and a cheque recorded at the door often arrives with none,
     * because nobody at the door knows which bank it will be deposited to.
     * Until now that cheque could not be cleared from any screen: the only
     * confirm took no input, so it failed on the missing bank every time and
     * there was nowhere to supply it. A bank already on the cheque wins; this
     * only fills a blank.
     */
    public function markCleared(Check $check, ?string $bankName = null): Check
    {
        $this->assertPending($check);

        if (! $check->bank_name && filled($bankName)) {
            $check->update(['bank_name' => trim($bankName)]);
            $check->refresh();
        }

        DB::transaction(function () use ($check) {
            if ($check->source_type === BankWithdrawal::class) {
                app(CashManagementService::class)->postBankWithdrawal(
                    BankWithdrawal::findOrFail($check->source_id)
                );
            } elseif ($check->direction === Check::DIRECTION_ISSUED) {
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
                // A deposit made by picking this cheque off the list says so
                // outright, and there is nothing to match or disambiguate.
                $linked = BankDeposit::where('check_id', $check->id)
                    ->where('status', BankDeposit::STATUS_PENDING)
                    ->get();

                $matches = $linked->isNotEmpty()
                    ? $linked
                    // Written before deposits carried that link: all that is
                    // left to go on is the number and the amount.
                    : BankDeposit::where('deposit_type', BankDeposit::TYPE_CHECK)
                        ->where('status', BankDeposit::STATUS_PENDING)
                        ->whereNull('check_id')
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

        DB::transaction(function () use ($check, $reason) {
            $check->update([
                'status' => Check::STATUS_BOUNCED,
                'bounced_at' => now(),
                'bounced_by_id' => Auth::id(),
                'bounce_reason' => trim($reason),
            ]);

            $this->retireDepositForCheck($check);
        });

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

    /**
     * Remove the deposit row that was waiting on a cheque that will never clear.
     *
     * A pending deposit never posted a journal entry, so there is nothing to
     * reverse — the row is simply deleted. When the match is ambiguous (legacy
     * rows without a check_id FK, more than one candidate) the deposit is left
     * alone rather than guessing wrong.
     */
    private function retireDepositForCheck(Check $check): void
    {
        if ($check->direction !== Check::DIRECTION_RECEIVED) {
            return;
        }

        $deposit = BankDeposit::where('check_id', $check->id)
            ->where('status', BankDeposit::STATUS_PENDING)
            ->first();

        if (! $deposit) {
            $matches = BankDeposit::where('deposit_type', BankDeposit::TYPE_CHECK)
                ->where('status', BankDeposit::STATUS_PENDING)
                ->whereNull('check_id')
                ->where('check_number', $check->check_number)
                ->where('amount', $check->amount)
                ->get();

            $deposit = $matches->count() === 1 ? $matches->first() : null;
        }

        if (! $deposit) {
            return;
        }

        Remittance::where('bank_deposit_id', $deposit->id)
            ->update(['bank_deposit_id' => null]);

        $deposit->delete();
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

        if (!$user) {
            return;
        }

        // Best-effort on purpose. If a caller wraps markBounced() in a
        // transaction, letting a notification failure escape would roll the
        // bounce back — losing a real event because an email did not send.
        try {
            $user->notify(new BouncedCheckNotification($check));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
