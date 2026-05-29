<?php

namespace App\Console\Commands;

use App\Models\ArInvoice;
use App\Models\ListStatus;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark AR invoices past their due date with an outstanding balance as Overdue';

    public function handle(): void
    {
        $overdueStatusId   = ListStatus::getBySlug('overdue')->id;
        $cancelledStatusId = ListStatus::getBySlug('cancelled')->id;
        $paidStatusId      = ListStatus::getBySlug('paid')->id;

        $count = ArInvoice::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->where('balance_due', '>', 0)
            ->whereNotIn('status_id', [$cancelledStatusId, $paidStatusId, $overdueStatusId])
            ->update(['status_id' => $overdueStatusId]);

        $this->info("Marked {$count} invoice(s) as overdue.");
    }
}
