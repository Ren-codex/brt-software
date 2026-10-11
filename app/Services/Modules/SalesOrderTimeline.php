<?php

namespace App\Services\Modules;

use App\Models\SalesOrder;

/**
 * Where a sales order is on its way from placed to closed.
 *
 * Nothing records each status change as it happens, so this is not a history
 * read out of a log. It is rebuilt from the facts the order already carries —
 * when it was created, when it was stamped delivered, which receipts exist and
 * who is holding them — which are the same facts the status itself is derived
 * from. That keeps the timeline and the status badge from ever disagreeing.
 *
 * The consequence is that a step shows the date the thing happened (the
 * delivery, the latest payment), not the moment the status label changed.
 */
class SalesOrderTimeline
{
    public function build(SalesOrder $order): array
    {
        $order->loadMissing([
            'status', 'customer', 'created_by.employee', 'deliveredBy.employee',
            'driver', 'checker', 'salesRep',
            'arInvoices.receipts.status', 'arInvoices.receipts.heldBy', 'arInvoices.receipts.remittance',
        ]);

        $slug = optional($order->status)->slug;
        $stopped = in_array($slug, ['cancelled', 'sales-returned'], true);

        $payment = $this->paymentStep($order);
        $delivery = $this->deliveryStep($order);

        // Goods go out first on COD and credit; a cash sale is paid first.
        $middle = SalesOrder::isOnAccount($order->payment_mode)
            ? [$delivery, $payment]
            : [$payment, $delivery];

        $steps = array_values(array_filter([
            $this->placedStep($order),
            ...$middle,
            $this->turnoverStep($order, $payment['done']),
            $this->closedStep($order, $slug),
        ]));

        // The first unfinished step is where the order is now. Once an order is
        // cancelled or returned nothing further is coming, so the rest is
        // marked skipped instead of waiting.
        $currentFound = false;
        foreach ($steps as &$step) {
            if ($step['done']) {
                $step['state'] = 'done';
            } elseif ($stopped) {
                $step['state'] = 'skipped';
            } elseif (! $currentFound) {
                $step['state'] = 'current';
                $currentFound = true;
            } else {
                $step['state'] = 'upcoming';
            }
            unset($step['done']);
        }
        unset($step);

        if ($stopped) {
            $steps[] = [
                'key' => $slug,
                'label' => $slug === 'cancelled' ? 'Cancelled' : 'Sales returned',
                'icon' => $slug === 'cancelled' ? 'ri-close-circle-line' : 'ri-arrow-go-back-line',
                'state' => 'stopped',
                'at' => optional($order->updated_at)->toIso8601String(),
                'by' => null,
                'detail' => $slug === 'cancelled'
                    ? ($order->cancellation_remarks ? 'Reason: '.$order->cancellation_remarks : 'This order was cancelled.')
                    : 'The goods on this order were returned.',
            ];
        }

        return [
            'so_number' => $order->so_number,
            'customer' => optional($order->customer)->name ?? 'Walk-in customer',
            'payment_mode' => $order->payment_mode,
            'total_amount' => (float) $order->total_amount,
            'status' => [
                'name' => optional($order->status)->name,
                'slug' => $slug,
                'text_color' => optional($order->status)->text_color,
                'bg_color' => optional($order->status)->bg_color,
            ],
            'summary' => $this->summary($slug, $steps),
            'steps' => $steps,
        ];
    }

    private function placedStep(SalesOrder $order): array
    {
        return [
            'key' => 'placed',
            'label' => 'Order placed',
            'icon' => 'ri-file-add-line',
            'done' => true,
            'at' => optional($order->created_at)->toIso8601String(),
            'by' => $this->userName($order->created_by),
            'detail' => trim(($order->payment_mode ?: 'No payment mode').' · ₱'.number_format((float) $order->total_amount, 2)
                .($order->salesRep ? ' · Sales rep '.$order->salesRep->fullname : '')),
        ];
    }

    private function paymentStep(SalesOrder $order): array
    {
        $invoices = $order->arInvoices;
        $receipts = $invoices->flatMap->receipts
            ->filter(fn ($r) => $r->receipt_type === 'payment' && ! in_array(optional($r->status)->slug, ['voided', 'cancelled'], true));

        $due = (float) $invoices->sum('amount_due') ?: (float) $order->total_amount;
        $balance = $invoices->isEmpty() ? $due : (float) $invoices->sum('balance_due');
        $collected = max($due - $balance, 0);
        $paid = $invoices->isNotEmpty() && $balance <= 0;
        $last = $receipts->sortByDesc('receipt_date')->first();

        if ($paid) {
            $detail = 'Paid in full — ₱'.number_format($due, 2);
        } elseif ($collected > 0) {
            $detail = '₱'.number_format($collected, 2).' collected, ₱'.number_format($balance, 2).' still owed';
        } elseif (SalesOrder::isCod($order->payment_mode)) {
            $detail = 'To be collected at the door on delivery — ₱'.number_format($balance, 2);
        } else {
            $detail = 'Nothing collected yet — ₱'.number_format($balance, 2).' owed';
        }

        return [
            'key' => 'payment',
            'label' => $paid ? 'Paid' : 'Payment',
            'icon' => 'ri-money-dollar-circle-line',
            'done' => $paid,
            'at' => $last && $last->receipt_date ? \Carbon\Carbon::parse($last->receipt_date)->toIso8601String() : null,
            'by' => null,
            'detail' => $detail,
        ];
    }

    private function deliveryStep(SalesOrder $order): array
    {
        $delivered = (bool) $order->delivered_at;
        $crew = collect([
            $order->driver ? 'Driver '.$order->driver->fullname : null,
            $order->checker ? 'Checker '.$order->checker->fullname : null,
        ])->filter()->implode(' · ');

        if ($delivered) {
            $detail = $order->isDelivery() ? ($crew ?: 'Delivered') : 'Handed over at the counter';
        } elseif ($order->isDelivery()) {
            $detail = collect([
                $order->delivery_date ? 'Planned for '.$order->delivery_date->format('M d, Y') : 'No delivery date set',
                $crew ?: null,
            ])->filter()->implode(' · ');
        } else {
            $detail = 'Released over the counter once it is paid';
        }

        return [
            'key' => 'delivery',
            'label' => $delivered ? 'Delivered' : ($order->isDelivery() ? 'Delivery' : 'Release'),
            'icon' => 'ri-truck-line',
            'done' => $delivered,
            'at' => optional($order->delivered_at)->toIso8601String(),
            'by' => $this->userName($order->deliveredBy),
            'detail' => $detail,
        ];
    }

    /**
     * Only for orders that went out on a truck: that is the one case where the
     * money can be recorded as collected and still not be in the office.
     */
    private function turnoverStep(SalesOrder $order, bool $paid): ?array
    {
        if (! $order->fieldCollectorId()) {
            return null;
        }

        $receipts = $order->arInvoices->flatMap->receipts->where('receipt_type', 'payment');
        $out = $receipts->filter(fn ($r) => ! $r->remittance_id
            && in_array($r->held_by_employee_id, array_filter([$order->driver_id, $order->checker_id])));
        $remitted = $receipts->filter(fn ($r) => $r->remittance)->sortByDesc(fn ($r) => $r->remittance->remittance_date)->first();
        $in = $paid && $order->moneyIsIn();

        if ($in) {
            $detail = $remitted
                ? 'Remitted on '.$remitted->remittance->remittance_no
                : 'Handed in to the office';
        } elseif ($out->isNotEmpty()) {
            $holders = $out->map(fn ($r) => optional($r->heldBy)->fullname)->filter()->unique()->implode(', ');
            $detail = '₱'.number_format((float) $out->sum('amount_paid'), 2).' still with '.($holders ?: 'the delivery crew');
        } else {
            $detail = 'Waiting for the collection to be made and handed in';
        }

        return [
            'key' => 'turnover',
            'label' => $in ? 'Money turned in' : 'Money turnover',
            'icon' => 'ri-hand-coin-line',
            'done' => $in,
            'at' => $in && $remitted && $remitted->remittance->remittance_date
                ? \Carbon\Carbon::parse($remitted->remittance->remittance_date)->toIso8601String()
                : null,
            'by' => null,
            'detail' => $detail,
        ];
    }

    private function closedStep(SalesOrder $order, ?string $slug): array
    {
        $closed = $slug === 'closed';

        return [
            'key' => 'closed',
            'label' => 'Closed',
            'icon' => 'ri-checkbox-circle-line',
            'done' => $closed,
            'at' => null,
            'by' => null,
            'detail' => $closed
                ? 'Paid, delivered, and the money is in. Nothing left to do.'
                : 'Closes on its own once it is paid, delivered, and the money is in.',
        ];
    }

    /** One sentence saying what the order is waiting on. */
    private function summary(?string $slug, array $steps): string
    {
        if ($slug === 'cancelled') {
            return 'This order was cancelled and will go no further.';
        }
        if ($slug === 'sales-returned') {
            return 'This order was returned by the customer.';
        }

        $current = collect($steps)->firstWhere('state', 'current');

        return $current
            ? 'Waiting on: '.$current['label'].'. '.$current['detail'].'.'
            : 'This order is complete.';
    }

    private function userName($user): ?string
    {
        if (! $user) {
            return null;
        }

        return optional($user->employee)->fullname ?: $user->username;
    }
}
