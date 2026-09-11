<?php

namespace App\Console\Commands;

use App\Models\Receipt;
use Illuminate\Console\Command;

/**
 * Punch-list #5: a check released to the bank auto-matures 7 days later —
 * purely a custody/monitoring status, independent of whether it's been
 * bank-confirmed (see ArInvoiceClass::confirmCheck()).
 */
class MarkMaturedChecks extends Command
{
    protected $signature = 'checks:mark-matured';

    protected $description = 'Flip released checks to matured once 7 days have passed since release';

    public function handle(): void
    {
        $affectedIds = Receipt::query()
            ->where('payment_mode', 'Check')
            ->where('check_status', 'released')
            ->whereNotNull('released_at')
            ->where('released_at', '<=', now()->subDays(7))
            ->pluck('id');

        if ($affectedIds->isEmpty()) {
            $this->info('No checks to mark as matured.');

            return;
        }

        Receipt::whereIn('id', $affectedIds)->update(['check_status' => 'matured']);

        $this->info("Marked {$affectedIds->count()} check(s) as matured.");
    }
}
