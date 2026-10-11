<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollLog;
use App\Models\PayrollTemplate;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sample payrolls for trying out the payroll screens, payslips and approvals.
 *
 * Three payrolls are created, one at each stage before release — Draft, awaiting
 * Approval, and For Release — over the same set of employees. Everything is
 * numbered or named DEMO so --clear can find it again without touching real
 * payroll.
 *
 * No released (Completed) payroll is created on purpose: releasing posts a
 * journal entry and deducts loan payments, and a demo row that claimed to be
 * released without either would put a payroll in the books that the books know
 * nothing about. Release the For Release one from the screen to see that stage.
 */
class SeedDemoPayrolls extends Command
{
    protected $signature = 'payroll:demo {--clear : Remove the demo payrolls instead of adding them}';

    protected $description = 'Add (or clear) sample payrolls for testing the payroll screens';

    private const TEMPLATE_NAME = 'DEMO Regular Staff';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to write demo payrolls in production.');

            return self::FAILURE;
        }

        $removed = $this->clear();

        if ($this->option('clear')) {
            $this->info("Removed {$removed} demo payroll(s).");

            return self::SUCCESS;
        }

        $employees = Employee::with('position')->where('is_active', 1)->orderBy('id')->limit(6)->get();
        $creator = User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))->first() ?? User::first();

        if ($employees->isEmpty() || ! $creator) {
            $this->error('Need at least one active employee and one user to build demo payrolls.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($employees, $creator) {
            // The edit screen reads its employee list from a template, so the
            // demo payrolls need one to be editable.
            $template = PayrollTemplate::create([
                'name' => self::TEMPLATE_NAME,
                'created_by_id' => $creator->id,
                'is_active' => true,
            ]);

            foreach ($employees as $employee) {
                DB::table('payroll_template_employees')->insert([
                    'payroll_template_id' => $template->id,
                    'employee_id' => $employee->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $periods = [
                // Oldest first, furthest along first.
                ['slug' => 'for-release', 'start' => now()->subMonth()->startOfMonth()->addDays(15), 'end' => now()->subMonth()->endOfMonth()],
                ['slug' => 'approval', 'start' => now()->startOfMonth(), 'end' => now()->startOfMonth()->addDays(14)],
                ['slug' => 'draft', 'start' => now()->startOfMonth()->addDays(15), 'end' => now()->endOfMonth()],
            ];

            foreach ($periods as $i => $period) {
                $this->createPayroll($i + 1, $period, $employees, $template, $creator);
            }
        });

        $this->info('Created 3 demo payrolls (Draft, Approval, For Release) for '.$employees->count().' employee(s). Run with --clear to remove them.');

        return self::SUCCESS;
    }

    private function createPayroll(int $number, array $period, $employees, PayrollTemplate $template, User $creator): void
    {
        $status = ListStatus::getBySlug($period['slug']);

        if (! $status) {
            $this->warn("Status '{$period['slug']}' is missing, so that demo payroll was skipped.");

            return;
        }

        $lines = $employees->values()->map(fn ($employee, $index) => $this->payrollLine($employee, $index, $number));
        $approved = $period['slug'] === 'for-release';

        $payroll = Payroll::create([
            'payroll_no' => 'DEMO-PR-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'pay_period_start' => $period['start']->toDateString(),
            'pay_period_end' => $period['end']->toDateString(),
            'status_id' => $status->id,
            'total_amount' => round($lines->sum('net_salary'), 2),
            'payroll_template_id' => $template->id,
            'created_by' => $creator->id,
            'approved_by_id' => $approved ? $creator->id : null,
            'approved_at' => $approved ? now()->subDays(2) : null,
        ]);

        foreach ($lines as $line) {
            PayrollItem::create($line + ['payroll_id' => $payroll->id]);
        }

        PayrollLog::create(['payroll_id' => $payroll->id, 'action' => 'created', 'actioned_by_id' => $creator->id, 'remarks' => 'Demo payroll']);

        if ($approved) {
            PayrollLog::create(['payroll_id' => $payroll->id, 'action' => 'status updated to for-release', 'actioned_by_id' => $creator->id]);
        }
    }

    /**
     * One employee's line. Figures follow the same sums the payroll screen
     * uses: total earnings is basic pay plus the added earnings, and net pay is
     * that less the deductions — so a demo payslip adds up like a real one.
     */
    private function payrollLine(Employee $employee, int $index, int $number): array
    {
        $rate = (float) ($employee->position->rate_per_day ?? 0) ?: 500.0;
        $days = [13, 12, 13, 11, 13, 12.5][$index % 6];
        $basicPay = round($rate * $days, 2);

        $earnings = [];
        if ($index % 2 === 0) {
            $earnings[] = ['description' => 'Overtime', 'amount' => round($rate / 8 * 1.25 * 4, 2)];
        }
        if ($index % 3 === 0) {
            $earnings[] = ['description' => 'Rice Allowance', 'amount' => 500.0];
        }

        $deductions = [
            ['description' => 'SSS', 'amount' => 225.0],
            ['description' => 'PhilHealth', 'amount' => 125.0],
        ];
        if ($index % 3 === 1) {
            $deductions[] = ['description' => 'Cash Advance', 'amount' => 500.0 + 100 * $number];
        }

        $totalEarnings = round($basicPay + array_sum(array_column($earnings, 'amount')), 2);
        $totalDeductions = round(array_sum(array_column($deductions, 'amount')), 2);
        $overtime = collect($earnings)->firstWhere('description', 'Overtime');

        return [
            'employee_id' => $employee->id,
            'basic_salary' => $rate,
            'total_days' => $days,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => round($totalEarnings - $totalDeductions, 2),
            'loans' => [],
            'hours_per_day' => 8,
            'overtime_hours' => $overtime ? 4 : 0,
            'overtime_rate' => $overtime ? round($rate / 8 * 1.25, 2) : null,
            'overtime_amount' => $overtime['amount'] ?? 0,
        ];
    }

    /** Remove the demo payrolls, their lines and logs, and the demo template. */
    private function clear(): int
    {
        $ids = Payroll::where('payroll_no', 'like', 'DEMO-PR-%')->pluck('id');

        DB::table('notifications')->where('data', 'like', '%"reference":"DEMO-PR-%')->delete();
        DB::table('payroll_logs')->whereIn('payroll_id', $ids)->delete();
        DB::table('payroll_items')->whereIn('payroll_id', $ids)->delete();
        $removed = Payroll::whereIn('id', $ids)->delete();

        $templateIds = PayrollTemplate::where('name', self::TEMPLATE_NAME)->pluck('id');
        DB::table('payroll_template_employees')->whereIn('payroll_template_id', $templateIds)->delete();
        PayrollTemplate::whereIn('id', $templateIds)->delete();

        return (int) $removed;
    }
}
