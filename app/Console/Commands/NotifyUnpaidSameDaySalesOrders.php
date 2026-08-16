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
        // "COD" orders in this system are Cash sales — normally auto-collected and
        // closed the instant they're saved, so the only way one is still sitting
        // unpaid this afternoon is a batch-approval hold (see requires_batch_approval)
        // that's still awaiting sign-off. Credit sales are the more common case:
        // they legitimately carry a balance until the customer pays.
        $salesOrders = SalesOrder::with(['customer', 'salesRep.user', 'arInvoices'])
            ->whereDate('order_date', today())
            ->whereIn(DB::raw('LOWER(payment_mode)'), ['cash', 'cod', 'credit', 'credit sales'])
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
