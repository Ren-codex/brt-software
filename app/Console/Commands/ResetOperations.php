<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all operational (transactional) data for a fresh start, while keeping
 * every master/setup record — users, employees, customers, suppliers, the
 * product catalog, the chart of accounts, bank accounts and config lists.
 *
 * Balances are NOT stored; they are derived from journal_entry_lines, so
 * clearing the transaction tables takes every account, bank and stock level to
 * zero on its own. The one stored balance (petty_cash_funds.balance) and the
 * document-number counters (series) are reset explicitly.
 *
 * Safety: --dry-run shows what would happen and deletes nothing; a real run
 * asks for a typed confirmation and does every delete inside one transaction,
 * so a mid-way failure rolls the whole thing back. Take a database backup
 * first regardless.
 */
class ResetOperations extends Command
{
    protected $signature = 'brt:reset-operations {--dry-run : Show what would be cleared without deleting anything} {--force : Skip the typed confirmation (for non-interactive use)}';

    protected $description = 'Clear all sales, inventory, accounting and payroll transactions for a fresh start (keeps master/setup data).';

    /** Transaction tables to empty, grouped only for readable output. */
    private array $groups = [
        'Sales' => [
            'sales_order_incentives', 'sales_order_delivery_refusals', 'sales_order_items',
            'sales_return_replacements', 'sales_return_history', 'sales_return_items', 'sales_returns',
            'void_receipts', 'receipts', 'remittances', 'ar_invoices',
            'price_change_requests', 'form_drafts', 'sales_orders',
        ],
        'Inventory' => [
            'stock_return_logs', 'stock_return_items', 'stock_returns',
            'inventory_weight_losses', 'inventory_adjustments', 'inventory_stocks',
            'received_stock_payments', 'received_items', 'received_stocks',
            'purchase_order_logs', 'purchase_order_items', 'purchase_orders',
            'replenishment_requests',
        ],
        'Accounting / Cash' => [
            'journal_entry_lines', 'journal_entries',
            'checks', 'bank_reconciliation_clears', 'bank_reconciliations',
            'bank_deposits', 'bank_withdrawals', 'fund_transfers',
            'petty_cash_transactions', 'expenses', 'expense_budgets',
            'supervisor_authorizations',
        ],
        'Payroll' => [
            'payroll_items', 'payroll_logs', 'payrolls',
            'loan_payments', 'loan_logs', 'loans',
        ],
        'Notifications' => [
            'notifications',
        ],
    ];

    public function handle(): int
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();
        $this->line('  Database: <info>'.$connection->getDatabaseName().'</info>  (driver: '.$driver.')');
        $this->newLine();

        // Tally current rows for every table in scope.
        $rows = [];
        $grandTotal = 0;
        foreach ($this->groups as $group => $tables) {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    $rows[] = [$group, $table, 'missing'];
                    continue;
                }
                $count = DB::table($table)->count();
                $grandTotal += $count;
                $rows[] = [$group, $table, number_format($count)];
            }
        }

        $this->table(['Group', 'Table', 'Rows'], $rows);
        $this->line('  Rows to delete: <comment>'.number_format($grandTotal).'</comment>');
        $this->newLine();
        $this->line('  Resets: series counters -> 1, petty_cash_funds.balance -> 0');
        $this->line('  Kept untouched: users, employees, customers, suppliers, products,');
        $this->line('                  chart of accounts, bank accounts, config lists, audit logs.');
        $this->newLine();

        if ($dryRun) {
            $this->info('  DRY RUN — nothing was deleted.');
            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn('  This permanently deletes all operational data on the database above.');
            $this->warn('  Make sure a fresh backup exists first.');
            $answer = $this->ask('  Type RESET to proceed');
            if ($answer !== 'RESET') {
                $this->line('  Aborted — nothing was changed.');
                return self::SUCCESS;
            }
        }

        $isMysql = $driver === 'mysql';
        $deleted = [];

        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            DB::transaction(function () use (&$deleted) {
                foreach ($this->groups as $tables) {
                    foreach ($tables as $table) {
                        if (! Schema::hasTable($table)) {
                            continue;
                        }
                        $deleted[$table] = DB::table($table)->delete();
                    }
                }

                // Reset document-number counters to 1 (rows kept — they define formats).
                if (Schema::hasTable('series')) {
                    DB::table('series')->update(['starting_value' => 1]);
                }

                // The only stored balance; everything else is derived from the
                // journal and is already zero once the tables above are empty.
                if (Schema::hasTable('petty_cash_funds')) {
                    DB::table('petty_cash_funds')->update(['balance' => 0]);
                }
            });
        } finally {
            if ($isMysql) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $this->newLine();
        $this->info('  Done. Deleted:');
        foreach ($deleted as $table => $n) {
            if ($n > 0) {
                $this->line('    '.$table.': '.number_format($n));
            }
        }
        $this->line('  Series counters reset to 1; petty cash balance reset to 0.');
        $this->newLine();

        return self::SUCCESS;
    }
}
