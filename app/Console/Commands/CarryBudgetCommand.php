<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\ExpenseBudget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CarryBudgetCommand extends Command
{
    protected $signature   = 'expense:carry-budget {--month=} {--year=}';
    protected $description = 'Carry unused expense budget from prior month into current month';

    private const TYPES = ['operational', 'utilities', 'supplies', 'transportation', 'maintenance', 'others'];

    public function handle(): int
    {
        $month = (int) ($this->option('month') ?? now()->month);
        $year  = (int) ($this->option('year') ?? now()->year);

        if ($month === 1) {
            $priorMonth = 12;
            $priorYear  = $year - 1;
        } else {
            $priorMonth = $month - 1;
            $priorYear  = $year;
        }

        DB::transaction(function () use ($month, $year, $priorMonth, $priorYear) {
            foreach (self::TYPES as $type) {
                $priorBudget = ExpenseBudget::where('expense_type', $type)
                    ->where('month', $priorMonth)
                    ->where('year', $priorYear)
                    ->first();

                if (! $priorBudget || $priorBudget->amount <= 0) {
                    $this->line("Skipped {$type} — no prior budget");
                    continue;
                }

                $spent = (float) Expense::where('expense_type', $type)
                    ->where('status', 'released')
                    ->whereMonth('expense_date', $priorMonth)
                    ->whereYear('expense_date', $priorYear)
                    ->sum('amount');

                $remainder = round($priorBudget->amount - $spent, 2);

                if ($remainder <= 0) {
                    $this->line("Skipped {$type} — budget fully used");
                    continue;
                }

                $exists = ExpenseBudget::where('expense_type', $type)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->exists();

                if ($exists) {
                    $this->line("Skipped {$type} — budget already set");
                    continue;
                }

                ExpenseBudget::create([
                    'expense_type'  => $type,
                    'month'         => $month,
                    'year'          => $year,
                    'amount'        => $remainder,
                    'created_by_id' => null,
                ]);

                $this->line("Carried ₱{$remainder} from {$type} ({$priorYear}-{$priorMonth}) → ({$year}-{$month})");
            }
        });

        return self::SUCCESS;
    }
}
