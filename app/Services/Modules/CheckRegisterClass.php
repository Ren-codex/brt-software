<?php

namespace App\Services\Modules;

use App\Models\Check;
use App\Models\Receipt;
use App\Models\ReceivedStockPayment;

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
            'check_date' => $receipt->check_date,
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
}
