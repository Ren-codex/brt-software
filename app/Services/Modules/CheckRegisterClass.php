<?php

namespace App\Services\Modules;

use App\Models\Check;
use App\Models\Receipt;
use App\Models\ReceivedStockPayment;
use App\Models\User;
use App\Notifications\BouncedCheckNotification;
use Illuminate\Support\Facades\Auth;
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

        return Check::create(array_merge([
            'direction' => Check::DIRECTION_RECEIVED,
            'check_number' => $receipt->reference_number,
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

    public function registerIssued(ReceivedStockPayment $payment, array $attributes = []): Check
    {
        $payment->loadMissing('receivedStock');

        return Check::create(array_merge([
            'direction' => Check::DIRECTION_ISSUED,
            'check_number' => $payment->reference_number,
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
