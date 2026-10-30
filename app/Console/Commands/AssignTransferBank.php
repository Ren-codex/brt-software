<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Models\Receipt;
use App\Models\Remittance;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs bank-transfer receipts that were recorded without naming a bank.
 *
 * Such a transfer settled into the shared 1011 Cash in Bank pool instead of a
 * real bank's ledger, so it never showed under that bank's balance. This names
 * the bank now, and for a receipt whose remittance was already verified it
 * reverses that remittance's posting and re-posts it so the money moves out of
 * 1011 into the named bank's account. Dry-run unless --apply is given, and it
 * skips any receipt that already names a bank, so it is safe to re-run.
 */
class AssignTransferBank extends Command
{
    protected $signature = 'transfers:assign-bank
        {--bank= : The destination bank account — its id, or part of its name}
        {--receipts= : Comma-separated receipt numbers; default is every transfer missing a bank}
        {--apply : Write the change. Without it, only reports what would happen}';

    protected $description = 'Name the destination bank on transfers recorded without one, and move the money out of the Cash in Bank pool';

    public function handle(JournalEntryService $journal): int
    {
        $bank = $this->resolveBank();
        if (! $bank) {
            return self::FAILURE;
        }

        $receipts = $this->targetReceipts();
        if ($receipts->isEmpty()) {
            $this->info('No bank-transfer receipts need a bank. Nothing to do.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $this->info(($apply ? 'APPLYING' : 'DRY RUN — no changes')
            .": assigning to {$bank->bank_name} — {$bank->account_name} (GL {$bank->gl_code})");
        $this->newLine();

        foreach ($receipts as $receipt) {
            $remittance = $receipt->remittance;
            $remitted = $remittance && optional($remittance->status)->slug === 'liquidated';
            $action = $remitted
                ? 'set bank + re-post remittance '.$remittance->remittance_no
                : 'set bank';

            $this->line(sprintf(
                '  %s | %s | PHP %s | %s',
                $receipt->receipt_number,
                $receipt->payment_mode,
                number_format((float) $receipt->amount_paid, 2),
                $action
            ));

            if (! $apply) {
                continue;
            }

            DB::transaction(function () use ($receipt, $remittance, $remitted, $bank, $journal) {
                $receipt->update(['bank_account_id' => $bank->id]);

                if ($remitted) {
                    // Undo the posting that sent this into 1011, then let the
                    // ordinary verification posting run again — now that the
                    // receipt names a bank, it lands in that bank's account.
                    $journal->reverseEntriesForSource(
                        $remittance,
                        'Re-posting remittance: transfer destination bank corrected.'
                    );
                    $journal->recordRemittanceApprovalEntry(
                        $remittance->fresh('receipts')
                    );
                }
            });
        }

        $this->newLine();
        $this->info($apply
            ? 'Done.'
            : 'Dry run only. Re-run with --apply to write these changes.');

        return self::SUCCESS;
    }

    private function resolveBank(): ?BankAccount
    {
        $key = (string) $this->option('bank');
        if (trim($key) === '') {
            $this->error('Pass --bank with the destination bank account id or name.');

            return null;
        }

        $query = is_numeric($key)
            ? BankAccount::where('id', (int) $key)
            : BankAccount::where('bank_name', 'LIKE', "%{$key}%")
                ->orWhere('account_name', 'LIKE', "%{$key}%");

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->error("No bank account matches \"{$key}\".");

            return null;
        }

        if ($matches->count() > 1) {
            $this->error("\"{$key}\" matches more than one bank account; pass its id instead:");
            foreach ($matches as $m) {
                $this->line("  {$m->id}: {$m->bank_name} — {$m->account_name}");
            }

            return null;
        }

        return $matches->first();
    }

    private function targetReceipts()
    {
        $query = Receipt::with(['remittance.status', 'remittance.receipts'])
            ->where('receipt_type', 'payment')
            ->where('payment_mode', 'Bank Transfer')
            ->whereNull('bank_account_id');

        $numbers = trim((string) $this->option('receipts'));
        if ($numbers !== '') {
            $query->whereIn('receipt_number', array_map('trim', explode(',', $numbers)));
        }

        return $query->orderBy('receipt_number')->get();
    }
}
