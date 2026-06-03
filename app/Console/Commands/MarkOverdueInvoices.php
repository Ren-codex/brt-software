<?php

namespace App\Console\Commands;

use App\Models\ArInvoice;
use App\Models\ListStatus;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark AR invoices past their due date with an outstanding balance as Overdue';

    public function __construct(private NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $overdueStatusId = ListStatus::getBySlug('overdue')->id;
        $cancelledStatusId = ListStatus::getBySlug('cancelled')->id;
        $paidStatusId = ListStatus::getBySlug('paid')->id;

        $affectedIds = ArInvoice::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->where('balance_due', '>', 0)
            ->whereNotIn('status_id', [$cancelledStatusId, $paidStatusId, $overdueStatusId])
            ->pluck('id');

        if ($affectedIds->isEmpty()) {
            $this->info('No invoices to mark as overdue.');

            return;
        }

        ArInvoice::whereIn('id', $affectedIds)->update(['status_id' => $overdueStatusId]);

        $invoices = ArInvoice::with('sales_order.customer')
            ->whereIn('id', $affectedIds)
            ->get();

        $this->notificationService->notifyOverdueInvoices($invoices);

        $this->info("Marked {$invoices->count()} invoice(s) as overdue.");
    }
}
