<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Inventory\OpeningStockImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Import stock that was on the shelf before the system, one sheet row per batch.
 *
 * Dry run by default: the whole import runs inside a transaction, the result is
 * printed, and everything is rolled back -- series numbers included. Only
 * --apply commits, and it refuses rows that look wrong unless told otherwise.
 */
class ImportOpeningStock extends Command
{
    protected $signature = 'inventory:import-opening-stock
        {path : CSV export of the opening stock sheet}
        {--apply : Commit the import. Without it nothing is saved.}
        {--date= : Date the stock is booked on (default: today)}
        {--user= : Username recorded as creating it (default: first active Super Admin)}
        {--allow-anomalies : Apply even though some rows look wrong}';

    protected $description = 'Import opening stock from a CSV, one row per batch (dry run unless --apply)';

    public function handle(OpeningStockImporter $importer): int
    {
        $rows = OpeningStockImporter::rowsFromCsv($this->argument('path'));
        $date = $this->option('date') ?: now()->toDateString();
        $user = $this->resolveUser();

        if (!$user) {
            $this->error('No user to record the import against. Pass --user=<username>.');

            return self::FAILURE;
        }

        // Journal entries and logs record who did it.
        Auth::setUser($user);

        $apply = (bool) $this->option('apply');

        DB::beginTransaction();

        try {
            $report = $importer->import($rows, $user, $date);

            if ($apply && $report['anomalies'] && !$this->option('allow-anomalies')) {
                DB::rollBack();
                $this->printReport($report, $date, false);
                $this->error('Not applied: fix the rows above or pass --allow-anomalies.');

                return self::FAILURE;
            }

            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->printReport($report, $date, $apply);

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

    private function printReport(array $report, string $date, bool $applied): void
    {
        $this->newLine();
        $this->line($applied
            ? "<info>APPLIED</info> — opening stock booked on {$date}"
            : "<comment>DRY RUN</comment> — nothing was saved. Booking date would be {$date}");

        $section = function (string $title, array $lines) {
            $this->newLine();
            $this->line("<options=bold>{$title}</> (" . count($lines) . ')');
            foreach ($lines as $key => $value) {
                $this->line('  ' . (is_string($key) ? "{$key}  {$value}" : $value));
            }
        };

        $section('Rows that look wrong', $report['anomalies']);
        $section('Brands renamed', $report['brands']['renamed']);
        $section('Brands merged', $report['brands']['merged']);
        $section('Brands created', $report['brands']['created']);
        $section('Suppliers matched (sheet -> system)', $report['suppliers']['matched']);
        $section('Suppliers created', $report['suppliers']['created']);
        $section('Products created', $report['products']['created']);
        $section('Products reused', $report['products']['reused']);

        foreach ($report['receipts'] as $receipt) {
            $this->newLine();
            $this->line(sprintf(
                '<options=bold>%s</>  %s / %s  —  %s units, cost %s',
                $receipt['supplier'],
                $receipt['po_number'],
                $receipt['received_no'],
                number_format($receipt['units']),
                number_format($receipt['cost'], 2),
            ));
            foreach ($receipt['batches'] as $batch) {
                $this->line('  ' . $batch);
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '<options=bold>TOTAL</>  %d batches, %s units, cost %s  →  DR Rice Inventory / CR Opening Balance Equity',
            $report['totals']['batches'],
            number_format($report['totals']['units']),
            number_format($report['totals']['cost'], 2),
        ));
    }
}
