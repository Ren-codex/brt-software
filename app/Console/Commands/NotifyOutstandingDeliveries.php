<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Notifications\OutstandingDeliveriesNotification;
use Illuminate\Console\Command;

/**
 * Every delivery list in this system waits to be looked at. This is the one
 * thing that speaks first: once a day, what has been sitting too long.
 *
 * Two questions, both about time rather than status: goods delivered with
 * nothing collected, and money collected that nobody has turned in. A delivery
 * made this morning is not late, so only what has aged past the threshold
 * counts.
 */
class NotifyOutstandingDeliveries extends Command
{
    protected $signature = 'deliveries:notify-outstanding';

    protected $description = 'Tell the office, and each rep, what has been delivered but uncollected and what cash is still in the field';

    public function handle(): void
    {
        $days = (int) AppSetting::get('field_cash_alert_days', 2);
        $cutoff = now()->startOfDay()->subDays($days);

        $uncollected = SalesOrder::with('arInvoices')
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', $cutoff)
            ->whereDoesntHave('status', fn ($q) => $q->whereIn('slug', ['cancelled', 'sales-returned']))
            ->whereHas('arInvoices', fn ($q) => $q->where('balance_due', '>', 0))
            ->get();

        $held = Receipt::with('arInvoice.sales_order')
            ->whereNull('remittance_id')
            ->whereHas('status', fn ($q) => $q->where('slug', 'pending'))
            ->whereDate('receipt_date', '<=', $cutoff)
            ->get();

        if ($uncollected->isEmpty() && $held->isEmpty()) {
            $this->info('Nothing outstanding beyond '.$days.' days.');

            return;
        }

        $this->notifyOffice($uncollected, $held, $days);
        $this->notifyEachRep($uncollected, $held, $days);

        $this->info('Told the office about '.$uncollected->count().' uncollected and '.$held->count().' held.');
    }

    private function notifyOffice($uncollected, $held, int $days): void
    {
        $office = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Administrator', 'Top Management']))->get();

        foreach ($office as $user) {
            $user->notify(new OutstandingDeliveriesNotification(
                $uncollected->count(),
                (float) $uncollected->sum(fn ($order) => $order->arInvoices->sum('balance_due')),
                $held->count(),
                (float) $held->sum('amount_paid'),
                $days,
                'all',
            ));
        }
    }

    /**
     * A rep hears only about their own orders — the office's total is nobody
     * else's to chase.
     */
    private function notifyEachRep($uncollected, $held, int $days): void
    {
        $repIds = $uncollected->pluck('sales_rep_id')
            ->merge($held->map(fn ($receipt) => optional(optional($receipt->arInvoice)->sales_order)->sales_rep_id))
            ->filter()
            ->unique();

        foreach ($repIds as $repId) {
            $mine = $uncollected->where('sales_rep_id', $repId);
            $myHeld = $held->filter(fn ($receipt) => optional(optional($receipt->arInvoice)->sales_order)->sales_rep_id === $repId);

            $user = optional(\App\Models\Employee::find($repId))->user;
            if (! $user) {
                continue;
            }

            $user->notify(new OutstandingDeliveriesNotification(
                $mine->count(),
                (float) $mine->sum(fn ($order) => $order->arInvoices->sum('balance_due')),
                $myHeld->count(),
                (float) $myHeld->sum('amount_paid'),
                $days,
                'mine',
            ));
        }
    }
}
