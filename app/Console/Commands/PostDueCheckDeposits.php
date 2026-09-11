<?php

namespace App\Console\Commands;

use App\Models\BankDeposit;
use App\Services\Accounting\CashManagementService;
use Illuminate\Console\Command;

/**
 * A check deposit holds its journal entry until the check date, so the money
 * lands in the bank on the day the check is actually good. This posts the ones
 * whose date has arrived.
 *
 * Idempotent: postBankDeposit() ignores an already-posted deposit, and the
 * query only looks at pending ones.
 */
class PostDueCheckDeposits extends Command
{
    protected $signature = 'deposits:post-due-checks';

    protected $description = 'Post bank deposits whose check date has arrived';

    public function handle(CashManagementService $service): int
    {
        $due = BankDeposit::dueForPosting(now()->toDateString())->get();

        if ($due->isEmpty()) {
            $this->info('No check deposits are due for posting.');

            return self::SUCCESS;
        }

        $posted = 0;
        foreach ($due as $deposit) {
            try {
                $service->postBankDeposit($deposit);
                $posted++;
            } catch (\Throwable $e) {
                // One bad deposit should not stop the rest of the batch.
                $this->error("Deposit {$deposit->deposit_no} failed to post: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Posted {$posted} check deposit(s).");

        return self::SUCCESS;
    }
}
