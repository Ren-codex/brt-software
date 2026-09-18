<?php

namespace App\Console\Commands;

use App\Models\SalesOrder;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyUnpaidSameDaySalesOrders extends Command
{
    protected $signature = 'sales-orders:notify-unpaid-same-day';

    protected $description = 'Notify the sales rep and admins about today\'s COD/credit sales orders that are still unpaid';

    public function __construct(private NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        // A cash sale auto-collects and closes on save, so one still unpaid this
        // afternoon is held by a batch approval (see requires_batch_approval).
        // Credit sales legitimately carry a balance until the customer pays.
        //
        // COD is unpaid by design until the driver returns, so it is only worth
        // chasing once its delivery day has arrived — an order delivering on
        // Friday is not late on Monday.
        $salesOrders = SalesOrder::with(['customer', 'salesRep.user', 'arInvoices'])
            ->whereDate('order_date', today())
            ->whereIn(DB::raw('LOWER(payment_mode)'), ['cash', 'cod', 'credit', 'credit sales'])
            ->where(function ($query) {
                $query->whereNotIn(DB::raw('LOWER(payment_mode)'), ['cod'])
                    ->orWhereNull('delivery_date')
                    ->orWhereDate('delivery_date', '<=', today());
            })
            ->whereHas('status', fn ($q) => $q->whereNotIn('slug', ['cancelled']))
            ->whereHas('arInvoices', fn ($q) => $q->where('balance_due', '>', 0))
            ->get();

        if ($salesOrders->isEmpty()) {
            $this->info('No unpaid same-day COD/credit sales orders to notify.');

            return;
        }

        $this->notificationService->notifyUnpaidSameDaySalesOrders($salesOrders);

        $this->info("Notified about {$salesOrders->count()} unpaid same-day sales order(s).");
    }
}
