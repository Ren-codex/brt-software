<?php

namespace App\Console\Commands;

use App\Models\ListStatus;
use App\Models\SalesOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSalesOrderStatuses extends Command
{
    protected $signature = 'sales-orders:sync-statuses
                            {--dry-run : Show what would change without writing to the database}';

    protected $description = 'Sync Sales Order statuses to match their AR invoice payment state';

    public function handle(): void
    {
        $dryRun = $this->option('dry-run');

        $closedId        = ListStatus::getBySlug('closed')->id;
        $partiallyPaidId = ListStatus::getBySlug('partially-paid')->id;
        $forPaymentId    = ListStatus::getBySlug('for-payment')->id;

        $fixable = ['for-payment', 'partially-paid', 'unpaid'];
        $fixableIds = ListStatus::whereIn('slug', $fixable)->pluck('id')->toArray();

        $orders = SalesOrder::with(['arInvoices', 'status'])
            ->whereIn('status_id', $fixableIds)
            ->get();

        $fixed = 0;
        $rows  = [];

        foreach ($orders as $order) {
            $invoice = $order->arInvoices->first();
            if (!$invoice) {
                continue;
            }

            if ($invoice->balance_due <= 0) {
                $newStatusId = $closedId;
                $newSlug     = 'closed';
            } elseif ($invoice->amount_paid > 0) {
                $newStatusId = $partiallyPaidId;
                $newSlug     = 'partially-paid';
            } else {
                continue;
            }

            if ($order->status_id === $newStatusId) {
                continue;
            }

            $rows[] = [
                $order->so_number,
                optional($order->status)->slug ?? '?',
                '→',
                $newSlug,
            ];

            if (!$dryRun) {
                $order->update(['status_id' => $newStatusId]);
            }

            $fixed++;
        }

        if (empty($rows)) {
            $this->info('All Sales Order statuses are already in sync.');
            return;
        }

        $this->table(['SO Number', 'Old Status', '', 'New Status'], $rows);
        $label = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$label} {$fixed} Sales Order(s).");
    }
}
