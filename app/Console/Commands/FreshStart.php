<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empty the books before the business starts using them for real.
 *
 * Everything entered while the system was being tried out goes: every sale,
 * every delivery, every peso of stock, and the ledger behind them. What the
 * business *is* stays — customers, products, suppliers, employees, users,
 * roles, positions, bank accounts, the chart of accounts and every Libraries
 * list. Afterwards the ledger is empty, so every bank account reads zero and
 * opening balances and opening stock have to be entered again.
 *
 * This cannot be undone. It truncates, which in MySQL commits as it goes and
 * cannot be rolled back, so the only way back is the backup taken beforehand —
 * which is why this refuses to start without one from today.
 */
class FreshStart extends Command
{
    protected $signature = 'system:fresh-start
                            {--yes : Skip the confirmation. For a run you have already thought about.}
                            {--skip-backup-check : Start without a backup from today. Say why to yourself first.}';

    protected $description = 'Empty sales, inventory, accounting and payroll for a fresh start. Irreversible.';

    /**
     * Emptied, in no particular order — foreign keys are set aside while this
     * runs, so nothing here depends on the order it is listed in.
     */
    private const CLEARED = [
        'Sales' => [
            'sales_orders', 'sales_order_items', 'sales_order_incentives',
            'sales_order_delivery_refusals', 'ar_invoices', 'receipts', 'void_receipts',
            'remittances', 'sales_returns', 'sales_return_items', 'sales_return_history',
            'sales_return_replacements',
        ],
        'Inventory' => [
            'purchase_orders', 'purchase_order_items', 'purchase_order_logs',
            'received_stocks', 'received_items', 'received_stock_payments',
            'stock_returns', 'stock_return_items', 'stock_return_logs',
            'inventory_stocks', 'inventory_adjustments', 'inventory_weight_losses',
            'product_conversions', 'replenishment_requests',
        ],
        'Accounting' => [
            'journal_entries', 'journal_entry_lines', 'checks',
            'bank_deposits', 'bank_withdrawals', 'fund_transfers',
            'bank_reconciliations', 'bank_reconciliation_clears',
            'petty_cash_transactions', 'expenses',
        ],
        'Payroll' => [
            'payrolls', 'payroll_items', 'payroll_logs',
            'loans', 'loan_payments', 'loan_logs',
        ],
        'Traces' => [
            'notifications', 'activity_log', 'authentication_logs',
            'supervisor_authorizations',
        ],
    ];

    /**
     * Left alone, and worth saying out loud: this is what the business is, as
     * opposed to what it has done.
     */
    private const KEPT = [
        'customers', 'contacts', 'products', 'list_suppliers', 'employees',
        'employee_positions', 'users', 'user_roles', 'list_roles', 'role_permissions',
        'list_positions', 'list_salaries', 'bank_accounts', 'accounts',
        'petty_cash_funds', 'expense_budgets', 'payroll_templates', 'payroll_settings',
        'app_settings', 'series',
    ];

    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();

        if (! $this->option('skip-backup-check') && ! $this->hasBackupFromToday()) {
            $this->error('No backup from today found in storage/app.');
            $this->line('Run: php artisan backup:run --only-db');
            $this->line('Truncating cannot be undone, and the backup is the only way back.');

            return self::FAILURE;
        }

        $counts = $this->counts();
        $total = array_sum(array_map('array_sum', $counts));

        if ($total === 0) {
            $this->info('Nothing to clear — these tables are already empty.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("About to empty <options=bold>{$database}</> of:");
        foreach ($counts as $group => $tables) {
            $groupTotal = array_sum($tables);
            if ($groupTotal === 0) {
                continue;
            }
            $this->newLine();
            $this->line("  <options=bold>{$group}</> — ".number_format($groupTotal).' rows');
            foreach (array_filter($tables) as $table => $rows) {
                $this->line(sprintf('    %-34s %s', $table, number_format($rows)));
            }
        }

        $this->newLine();
        $this->line('Kept: customers, products, suppliers, employees, users, roles, positions,');
        $this->line('bank accounts, chart of accounts, Libraries lists and settings.');
        $this->newLine();
        $this->warn('Every bank account will read zero afterwards. Opening balances and opening');
        $this->warn('stock have to be entered again. This cannot be undone.');
        $this->newLine();

        if (! $this->option('yes')) {
            $typed = $this->ask("Type the database name to confirm ({$database})");

            if ($typed !== $database) {
                $this->info('Nothing was deleted.');

                return self::SUCCESS;
            }
        }

        $this->emptyTables(array_keys($counts));
        $this->resetCounters();

        $this->newLine();
        $this->info('Done. '.number_format($total).' rows cleared.');
        $this->line('Next: enter opening balances, then opening stock.');

        return self::SUCCESS;
    }

    /** Row counts per table, skipping any table this database does not have. */
    private function counts(): array
    {
        $counts = [];

        foreach (self::CLEARED as $group => $tables) {
            foreach ($tables as $table) {
                $counts[$group][$table] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
            }
        }

        return $counts;
    }

    /**
     * Truncate rather than delete: it resets each table's next id, which is
     * the point of starting fresh. Foreign keys are set aside for the run —
     * every table that could point at another is in the list, so nothing is
     * left holding a reference to a row that has gone.
     */
    private function emptyTables(array $groups): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($groups as $group) {
                foreach (self::CLEARED[$group] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->truncate();
                    }
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Document numbers start from one again, and the petty cash funds are
     * emptied without being deleted — the fund is a thing the business has,
     * its balance is something it did.
     */
    private function resetCounters(): void
    {
        if (Schema::hasTable('series')) {
            DB::table('series')->update(['starting_value' => 1, 'updated_at' => now()]);
        }

        if (Schema::hasTable('petty_cash_funds')) {
            DB::table('petty_cash_funds')->update(['balance' => 0, 'updated_at' => now()]);
        }
    }

    /** A backup zip written today, wherever spatie/laravel-backup put it. */
    private function hasBackupFromToday(): bool
    {
        foreach (glob(storage_path('app/*/*.zip')) ?: [] as $file) {
            if (date('Y-m-d', filemtime($file)) === now()->toDateString()) {
                return true;
            }
        }

        return false;
    }
}
