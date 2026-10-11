<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\ApprovalRequestNotification;
use App\Notifications\BouncedCheckNotification;
use App\Notifications\LowBalanceFundNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\OutstandingDeliveriesNotification;
use App\Notifications\OverdueInvoiceNotification;
use App\Notifications\UnpaidSameDaySalesOrderNotification;
use App\Services\Approvals\ApprovalRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills the bell with sample notifications so the panel can be looked at.
 *
 * The rows are written straight into `notifications` rather than raised through
 * the real notification classes: those need an actual low-stock product, an
 * overdue invoice and so on, and nothing here should touch business records.
 * Every row carries `demo: true` in its payload, which is what --clear removes —
 * genuine notifications are never deleted.
 *
 * Because nothing is broadcast, a browser that is already open shows them on its
 * next poll (up to 20 seconds) or on refresh, not instantly.
 */
class SeedDemoNotifications extends Command
{
    protected $signature = 'notifications:demo
        {--user= : Username to seed for (default: every user)}
        {--approvals : Also create real pending loans, so the approvals page has something to act on}
        {--clear : Remove the demo notifications (and demo loans) instead of adding them}';

    protected $description = 'Add (or clear) sample notifications for testing the notification panel';

    public function handle(ApprovalRegistry $registry): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to write demo notifications in production.');

            return self::FAILURE;
        }

        $users = $this->option('user')
            ? User::where('username', $this->option('user'))->get()
            : User::all();

        if ($users->isEmpty()) {
            $this->error('No matching user.');

            return self::FAILURE;
        }

        $removed = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $users->pluck('id'))
            ->where('data', 'like', '%"demo":true%')
            ->delete();

        $removedLoans = $this->clearDemoLoans();

        if ($this->option('clear')) {
            $this->info("Removed {$removed} demo notification(s) and {$removedLoans} demo loan(s).");

            return self::SUCCESS;
        }

        $samples = $this->samples($registry);
        $rows = [];

        if ($this->option('approvals')) {
            // The real thing replaces the two mocked approval rows: saving a
            // pending loan fires ApprovalRequestObserver, which notifies every
            // loan approver exactly as a genuine request would.
            $samples = array_values(array_filter(
                $samples,
                fn ($sample) => $sample['data']['type'] !== 'approval_request'
            ));
            $this->createDemoLoans();
        }

        foreach ($users as $user) {
            foreach ($samples as $sample) {
                $at = now()->subMinutes($sample['minutes_ago']);

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'type' => $sample['class'],
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => json_encode($sample['data'] + ['demo' => true]),
                    'read_at' => $sample['read'] ? $at->copy()->addMinutes(5) : null,
                    'created_at' => $at,
                    'updated_at' => $at,
                ];
            }
        }

        DB::table('notifications')->insert($rows);

        $unread = collect($samples)->where('read', false)->count();
        $this->info(sprintf(
            'Added %d demo notifications (%d unread) for %d user(s). Run with --clear to remove them.',
            count($samples),
            $unread,
            $users->count()
        ));

        return self::SUCCESS;
    }

    /**
     * Two pending loans, numbered DEMO-… so --clear can find them again. These
     * are real rows: approving one on the approvals page really approves it.
     */
    private function createDemoLoans(): void
    {
        $employees = Employee::limit(2)->get();
        $addedBy = User::value('id');

        if ($employees->isEmpty()) {
            $this->warn('No employees exist, so no demo loans were created.');

            return;
        }

        foreach ($employees as $i => $employee) {
            $amount = [15000, 8500][$i];

            Loan::create([
                'loan_no' => 'DEMO-'.now()->format('His').'-'.($i + 1),
                'employee_id' => $employee->id,
                'loan_type' => ['emergency', 'personal'][$i],
                'payment_type' => 'cash',
                'amount' => $amount,
                'interest_rate' => 0,
                'term_months' => 6,
                'status' => 'pending',
                'purpose' => 'Demo loan for testing approvals',
                'added_by_id' => $addedBy,
                'remaining_balance' => $amount,
            ]);
        }

        $this->info("Created {$employees->count()} pending demo loan(s).");
    }

    /** Remove demo loans, their logs, and the notifications they raised. */
    private function clearDemoLoans(): int
    {
        $ids = Loan::where('loan_no', 'like', 'DEMO-%')->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        DB::table('notifications')->where('data', 'like', '%"reference":"DEMO-%')->delete();
        DB::table('loan_logs')->whereIn('loan_id', $ids)->delete();

        return Loan::whereIn('id', $ids)->delete();
    }

    /**
     * One of every type the panel knows how to draw, newest first, with a mix
     * of read and unread so both tabs have something in them.
     */
    private function samples(ApprovalRegistry $registry): array
    {
        // Point the approval samples at a real pending record when there is one,
        // so clicking through lands on a highlighted row rather than nothing.
        $loanId = ($registry->get('loan')['pending'])()->value('id') ?? 0;
        $poId = ($registry->get('purchase_order')['pending'])()->value('id') ?? 0;

        return [
            [
                'class' => ApprovalRequestNotification::class,
                'minutes_ago' => 2,
                'read' => false,
                'data' => [
                    'type' => 'approval_request',
                    'url' => "/approvals?source=loan&focus={$loanId}",
                    'source' => 'loan',
                    'source_label' => 'Employee Loan',
                    'record_id' => $loanId,
                    'reference' => 'LOAN-2026-0042',
                    'summary' => 'Alden Cruz',
                    'amount' => 15000.00,
                ],
            ],
            [
                'class' => ApprovalRequestNotification::class,
                'minutes_ago' => 14,
                'read' => false,
                'data' => [
                    'type' => 'approval_request',
                    'url' => "/approvals?source=purchase_order&focus={$poId}",
                    'source' => 'purchase_order',
                    'source_label' => 'Purchase Order',
                    'record_id' => $poId,
                    'reference' => 'PR-2026-0117',
                    'summary' => 'Golden Grains Trading',
                    'amount' => 284500.00,
                ],
            ],
            [
                'class' => BouncedCheckNotification::class,
                'minutes_ago' => 47,
                'read' => false,
                'data' => [
                    'type' => 'bounced_check',
                    'url' => '/sales/check-monitoring',
                    'check_id' => 0,
                    'check_number' => '0012345',
                    'amount' => 42750.00,
                    'check_date' => now()->subDays(2)->toDateString(),
                    'bounce_reason' => 'Insufficient funds',
                    'customer_name' => 'Mabuhay Sari-Sari Store',
                ],
            ],
            [
                'class' => LowStockNotification::class,
                'minutes_ago' => 95,
                'read' => false,
                'data' => [
                    'type' => 'low_stock',
                    'url' => '/inventory-stocks',
                    'product_id' => 0,
                    'product_name' => 'Jasmine Premium 25 kg',
                    'current_stock' => 8,
                    'minimum_stock' => 20,
                ],
            ],
            [
                'class' => UnpaidSameDaySalesOrderNotification::class,
                'minutes_ago' => 60 * 4,
                'read' => true,
                'data' => [
                    'type' => 'unpaid_same_day_sales_order',
                    'url' => '/sales-orders',
                    'sales_order_id' => 0,
                    'so_number' => 'SO-2026-0931',
                    'customer_name' => 'Rivera Rice Retailing',
                    'payment_mode' => 'COD',
                    'balance_due' => 18600.00,
                ],
            ],
            [
                'class' => OverdueInvoiceNotification::class,
                'minutes_ago' => 60 * 26,
                'read' => false,
                'data' => [
                    'type' => 'overdue_invoice',
                    'url' => '/ar-invoices',
                    'invoice_id' => 0,
                    'invoice_number' => 'AR-2026-0388',
                    'customer_name' => 'Santos Grocery',
                    'balance_due' => 96200.00,
                    'due_date' => now()->subDays(6)->toDateString(),
                ],
            ],
            [
                'class' => LowBalanceFundNotification::class,
                'minutes_ago' => 60 * 30,
                'read' => true,
                'data' => [
                    'type' => 'low_balance',
                    'url' => '/accounting/petty-cash',
                    'fund_id' => 0,
                    'fund_name' => 'Main Office Petty Cash',
                    'balance' => 1250.00,
                    'threshold' => 5000.00,
                ],
            ],
            [
                'class' => OutstandingDeliveriesNotification::class,
                'minutes_ago' => 60 * 24 * 3,
                'read' => true,
                'data' => [
                    'type' => 'outstanding_deliveries',
                    'url' => '/sales-orders',
                    'scope' => 'all',
                    'days' => 3,
                    'uncollected_count' => 4,
                    'uncollected_amount' => 73400.00,
                    'held_count' => 2,
                    'held_amount' => 21900.00,
                    'message' => '4 deliveries uncollected (₱73,400.00) and 2 held by drivers (₱21,900.00) for 3+ days',
                ],
            ],
        ];
    }
}
