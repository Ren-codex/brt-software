<?php

namespace App\Console\Commands;

use App\Models\BankDeposit;
use Illuminate\Console\Command;

/**
 * A check whose date has arrived is not necessarily good funds — it can still
 * bounce. So this command posts nothing: it only reports how many check
 * deposits are due for a person to confirm. Posting happens only when
 * CheckRegisterClass::markCleared() confirms the underlying check actually
 * cleared, which is what calls CashManagementService::postBankDeposit().
 */
class PostDueCheckDeposits extends Command
{
    protected $signature = 'deposits:post-due-checks';

    protected $description = 'Report bank deposits whose check date has arrived and are awaiting confirmation';

    public function handle(): int
    {
        $due = BankDeposit::dueForPosting(now()->toDateString())->get();

        if ($due->isEmpty()) {
            $this->info('No check deposits are awaiting confirmation.');

            return self::SUCCESS;
        }

        // Deliberately posts nothing. A check whose date has arrived has not
        // necessarily cleared — it can still bounce — so a person confirms the
        // money arrived and that confirmation is what posts.
        $this->info("{$due->count()} check deposit(s) are due for confirmation.");

        return self::SUCCESS;
    }
}
