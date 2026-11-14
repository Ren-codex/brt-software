<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Accounting\OpeningReceivablesImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Import opening customer receivables, one sheet row per open invoice.
 *
 * Dry run by default: the whole import runs inside a transaction, the result is
 * printed, and everything is rolled back. Only --apply commits, and it refuses
 * rows that look wrong unless told otherwise.
 */
class ImportOpeningReceivables extends Command
{
    protected $signature = 'accounting:import-opening-receivables
        {path : CSV export of the receivables sheet (customer, invoice_no, amount, invoice_date, due_date)}
        {--apply : Commit the import. Without it nothing is saved.}
        {--date= : Fallback date for rows with no invoice date (default: today)}
        {--user= : Username recorded as creating it (default: first active Super Admin)}
        {--allow-anomalies : Apply even though some rows look wrong}';

    protected $description = 'Import opening customer receivables from a CSV (dry run unless --apply)';

    public function handle(OpeningReceivablesImporter $importer): int
    {
        $rows = OpeningReceivablesImporter::rowsFromCsv($this->argument('path'));
        $date = $this->option('date') ?: now()->toDateString();
        $user = $this->resolveUser();

        if (! $user) {
            $this->error('No user to record the import against. Pass --user=<username>.');

            return self::FAILURE;
        }

        Auth::setUser($user);

        $apply = (bool) $this->option('apply');

        DB::beginTransaction();

        try {
            $report = $importer->import($rows, $user, $date);

            if ($apply && $report['anomalies'] && ! $this->option('allow-anomalies')) {
                DB::rollBack();
                $this->printReport($report, $apply);
                $this->error('Not applied: fix the rows above or pass --allow-anomalies.');

                return self::FAILURE;
            }

            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->printReport($report, $apply);

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        if ($username = $this->option('user')) {
            return User::where('username', $username)->first();
        }

        return User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin')->where('user_roles.is_active', 1))
            ->orderBy('id')
            ->first();
    }

    private function printReport(array $report, bool $applied): void
    {
        $this->newLine();
        $this->line($applied
            ? '<info>APPLIED</info> — opening receivables booked'
            : '<comment>DRY RUN</comment> — nothing was saved.');

        $section = function (string $title, array $lines) {
            $this->newLine();
            $this->line("<options=bold>{$title}</> (" . count($lines) . ')');
            foreach ($lines as $value) {
                $this->line('  ' . $value);
            }
        };

        $section('Rows that look wrong', $report['anomalies']);
        $section('Customers matched (sheet -> system)', $report['customers']['matched']);
        $section('Customers created', $report['customers']['created']);

        foreach ($report['invoices'] as $cust) {
            $this->newLine();
            $this->line(sprintf('<options=bold>%s</>  —  %s owed', $cust['customer'], number_format($cust['total'], 2)));
            foreach ($cust['lines'] as $line) {
                $this->line('  ' . $line);
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '<options=bold>TOTAL</>  %d invoices, %s  →  DR Accounts Receivable / CR Opening Balance Equity',
            $report['totals']['invoices'],
            number_format($report['totals']['amount'], 2),
        ));
    }
}
