<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountingController extends Controller
{
    private const JOURNAL_ENTRY_COLUMNS = [
        'reversal_of_id',
        'reversed_at',
        'reversal_reason',
    ];

    public function index(Request $request)
    {
        if ($request->option === 'journal_entries_pdf') {
            [$dateFrom, $dateTo] = $this->resolveDateRange($request);
            $filename = 'journal-entries-' . now()->format('Ymd_His') . '.pdf';
            $entries = $this->buildJournalEntryReportEntries($dateFrom, $dateTo);

            $pdf = \PDF::loadView('prints.accounting-journal-entries', [
                'entries' => $entries,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ])->setPaper('A4', 'portrait');

            return $pdf->download($filename);
        }

        if ($request->option === 'stats') {
            [$dateFrom, $dateTo] = $this->resolveDateRange($request);

            return $this->buildStats($dateFrom, $dateTo);
        }

        if ($request->option === 'report_data') {
            [$dateFrom, $dateTo] = $this->resolveDateRange($request);

            return response()->json([
                'stats' => $this->buildStats($dateFrom, $dateTo),
                'sectionMetrics' => $this->buildSectionMetrics($dateFrom, $dateTo),
                'reportData' => $this->buildOverviewReportData($dateFrom, $dateTo),
                'dataReady' => $this->hasCoreAccountingTables(),
            ]);
        }

        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        return inertia('Modules/Accounting/Index', [
            'stats' => $this->buildStats($dateFrom, $dateTo),
            'sectionMetrics' => $this->buildSectionMetrics($dateFrom, $dateTo),
            'reportData' => $this->buildOverviewReportData($dateFrom, $dateTo),
            'dataReady' => $this->hasCoreAccountingTables(),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function journalEntries(Request $request)
    {
        return inertia('Modules/Accounting/JournalEntries', [
            'stats' => $this->buildStats(),
            'journalFeatures' => $this->buildJournalEntryFeatures(),
            'entryTypes' => [],
        ]);
    }

    public function journalEntriesApi(Request $request)
    {
        $lists = $this->journalEntryLists($request);

        return response()->json([
            'data' => $lists->items(),
            'links' => [
                'first' => $lists->url(1),
                'last' => $lists->url($lists->lastPage()),
                'prev' => $lists->previousPageUrl(),
                'next' => $lists->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $lists->currentPage(),
                'from' => $lists->firstItem(),
                'last_page' => $lists->lastPage(),
                'links' => [],
                'path' => $lists->path(),
                'per_page' => $lists->perPage(),
                'to' => $lists->lastItem(),
                'total' => $lists->total(),
            ],
            'stats' => $this->buildStats(),
            'journalFeatures' => $this->buildJournalEntryFeatures(),
            'entryTypes' => $this->buildJournalEntryTypes(),
        ]);
    }

    public function accountsReceivable()
    {
        return redirect('/accounting?tab=accounts_receivable');
    }

    public function accountsPayable()
    {
        return redirect('/accounting?tab=accounts_payable');
    }

    public function chartOfAccounts()
    {
        return redirect('/accounting?tab=chart_of_accounts');
    }

    public function generalLedger()
    {
        return redirect('/accounting?tab=general_ledger');
    }

    public function trialBalance()
    {
        return redirect('/accounting?tab=trial_balance');
    }

    public function profitLoss()
    {
        return redirect('/accounting?tab=profit_loss');
    }

    public function balanceSheet()
    {
        return redirect('/accounting?tab=balance_sheet');
    }

    private function buildStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (!Schema::hasTable('journal_entries')) {
            return [
                'open_periods' => 0,
                'pending_entries' => 0,
                'unreconciled_items' => 0,
                'generated_reports' => 0,
            ];
        }

        $hasReversalColumns = $this->hasJournalReversalColumns();
        $journalEntryQuery = JournalEntry::query();
        $this->applyDateRange($journalEntryQuery, 'entry_date', $dateFrom, $dateTo);

        return [
            'open_periods' => 0,
            'pending_entries' => (clone $journalEntryQuery)->whereIn('status', ['posted', 'reversal_posted'])->count(),
            'unreconciled_items' => $hasReversalColumns
                ? (clone $journalEntryQuery)->whereNull('reversed_at')->whereNull('reversal_of_id')->count()
                : (clone $journalEntryQuery)->count(),
            'generated_reports' => $hasReversalColumns
                ? (clone $journalEntryQuery)->whereNotNull('reversal_of_id')->count()
                : 0,
        ];
    }

    private function buildSectionMetrics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $balances = $this->buildAccountBalances($dateFrom, $dateTo);
        $totals = $this->buildProfitLossTotals($balances);
        $journalEntryQuery = Schema::hasTable('journal_entries') ? JournalEntry::query() : null;
        $journalLineQuery = Schema::hasTable('journal_entry_lines')
            ? JournalEntryLine::query()->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            : null;

        if ($journalEntryQuery) {
            $this->applyDateRange($journalEntryQuery, 'entry_date', $dateFrom, $dateTo);
        }

        if ($journalLineQuery) {
            $this->applyDateRange($journalLineQuery, 'journal_entries.entry_date', $dateFrom, $dateTo);
        }

        $journalEntryCount = $journalEntryQuery ? (clone $journalEntryQuery)->count() : 0;
        $journalLineCount = $journalLineQuery ? (clone $journalLineQuery)->count('journal_entry_lines.id') : 0;
        $latestEntryDate = $journalEntryQuery ? optional((clone $journalEntryQuery)->latest('entry_date')->first())->entry_date?->format('Y-m-d') : null;
        $reversalCount = $this->hasJournalReversalColumns() && $journalEntryQuery
            ? (clone $journalEntryQuery)->whereNotNull('reversal_of_id')->count()
            : 0;
        $arAccount = $balances->firstWhere('slug', 'accounts_receivable');
        $apAccount = $balances->firstWhere('slug', 'accounts_payable');
        $assetAccounts = $balances->where('type', 'asset');
        $liabilityAccounts = $balances->where('type', 'liability');
        $equityAccounts = $balances->where('type', 'equity');

        return [
            'general_ledger' => [
                $this->makeMetricCard('Accounts', $balances->count(), 'Accounts available in the ledger.', 'ri-book-open-line'),
                $this->makeMetricCard('Posted Entries', $journalEntryCount, 'Journal entries currently stored.', 'ri-file-list-3-line'),
                $this->makeMetricCard('Debit Volume', $this->formatCurrency($balances->sum('debit_total')), 'Total debits posted across all accounts.', 'ri-arrow-left-down-line'),
                $this->makeMetricCard('Credit Volume', $this->formatCurrency($balances->sum('credit_total')), 'Total credits posted across all accounts.', 'ri-arrow-right-up-line'),
            ],
            'trial_balance' => [
                $this->makeMetricCard('Accounts', $balances->count(), 'Accounts included in the trial balance.', 'ri-scales-3-line'),
                $this->makeMetricCard('Total Debits', $this->formatCurrency($balances->sum('debit_total')), 'Aggregate debit postings.', 'ri-arrow-left-down-line'),
                $this->makeMetricCard('Total Credits', $this->formatCurrency($balances->sum('credit_total')), 'Aggregate credit postings.', 'ri-arrow-right-up-line'),
                $this->makeMetricCard('Difference', $this->formatCurrency(abs($balances->sum('debit_total') - $balances->sum('credit_total'))), 'Difference should be zero when balanced.', 'ri-focus-3-line'),
            ],
            'profit_loss' => [
                $this->makeMetricCard('Revenue', $this->formatCurrency($totals['revenue_raw']), 'Net revenue activity from posted entries.', 'ri-funds-line'),
                $this->makeMetricCard('Cost Of Sales', $this->formatCurrency($totals['cost_of_sales_raw']), 'Cost recognized from inventory-out activity.', 'ri-shopping-basket-line'),
                $this->makeMetricCard('Operating Expenses', $this->formatCurrency($totals['operating_expenses_raw']), 'Non-COGS operating expenses.', 'ri-wallet-line'),
                $this->makeMetricCard('Net Income', $this->formatCurrency($totals['net_income_raw']), 'Revenue less total expenses.', 'ri-line-chart-line'),
            ],
            'balance_sheet' => [
                $this->makeMetricCard('Assets', $this->formatCurrency($assetAccounts->sum('balance')), 'Total asset balances.', 'ri-coins-line'),
                $this->makeMetricCard('Liabilities', $this->formatCurrency($liabilityAccounts->sum('balance')), 'Total liability balances.', 'ri-secure-payment-line'),
                $this->makeMetricCard('Equity', $this->formatCurrency($equityAccounts->sum('balance')), 'Posted equity balances.', 'ri-building-line'),
                $this->makeMetricCard('Current Earnings', $this->formatCurrency($totals['net_income_raw']), 'Current period earnings in view.', 'ri-bar-chart-box-line'),
            ],
            'accounts_receivable' => [
                $this->makeMetricCard('AR Balance', $this->formatCurrency($arAccount['balance'] ?? 0), 'Current balance of Accounts Receivable.', 'ri-file-list-3-line'),
                $this->makeMetricCard('Receipt Entries', $journalEntryQuery ? (clone $journalEntryQuery)->where('entry_type', 'receipt_collection')->count() : 0, 'Receipts applied against receivables.', 'ri-money-dollar-circle-line'),
                $this->makeMetricCard('Sales Entries', $journalEntryQuery ? (clone $journalEntryQuery)->where('entry_type', 'sales_revenue')->count() : 0, 'Sales postings that affect customer balances.', 'ri-shopping-bag-3-line'),
                $this->makeMetricCard('Latest Entry', $latestEntryDate ?: 'N/A', 'Most recent posting date in accounting.', 'ri-calendar-check-line'),
            ],
            'accounts_payable' => [
                $this->makeMetricCard('AP Balance', $this->formatCurrency($apAccount['balance'] ?? 0), 'Current balance of Accounts Payable.', 'ri-wallet-3-line'),
                $this->makeMetricCard('Purchase Receipts', $journalEntryQuery ? (clone $journalEntryQuery)->where('entry_type', 'purchase_receipt')->count() : 0, 'Received stock entries posted to payables.', 'ri-inbox-archive-line'),
                $this->makeMetricCard('Liability Accounts', $liabilityAccounts->count(), 'Configured liability accounts.', 'ri-secure-payment-line'),
                $this->makeMetricCard('Latest Entry', $latestEntryDate ?: 'N/A', 'Most recent posting date in accounting.', 'ri-calendar-check-line'),
            ],
            'chart_of_accounts' => [
                $this->makeMetricCard('Total Accounts', Schema::hasTable('accounts') ? Account::count() : 0, 'All accounts in the chart.', 'ri-node-tree'),
                $this->makeMetricCard('Active Accounts', Schema::hasTable('accounts') ? Account::where('is_active', true)->count() : 0, 'Accounts available for posting.', 'ri-checkbox-circle-line'),
                $this->makeMetricCard('Balance Sheet Accts', $assetAccounts->count() + $liabilityAccounts->count() + $equityAccounts->count(), 'Accounts tagged for balance sheet sections.', 'ri-bank-card-line'),
                $this->makeMetricCard('P&L Accts', $balances->whereIn('type', ['revenue', 'expense'])->count(), 'Accounts used in income statement reporting.', 'ri-line-chart-line'),
            ],
            'journal_entries' => [
                $this->makeMetricCard('Entries', $journalEntryCount, 'Total journal entries stored.', 'ri-book-2-line'),
                $this->makeMetricCard('Lines', $journalLineCount, 'Journal lines across all entries.', 'ri-menu-2-line'),
                $this->makeMetricCard('Reversals', $reversalCount, 'Reversal entries logged so far.', 'ri-arrow-go-back-line'),
                $this->makeMetricCard('Latest Entry', $latestEntryDate ?: 'N/A', 'Most recent journal entry date.', 'ri-calendar-check-line'),
            ],
        ];
    }

    private function buildOverviewReportData(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $balances = $this->buildAccountBalances($dateFrom, $dateTo);
        $profitLoss = $this->buildProfitLossTotals($balances);
        $assetAccounts = $balances->where('type', 'asset')->values();
        $liabilityAccounts = $balances->where('type', 'liability')->values();
        $equityAccounts = $balances->where('type', 'equity')->values();

        return [
            'general_ledger' => [
                'account_balances' => $balances->take(12)->values(),
                'recent_lines' => collect($this->buildRecentLedgerLines($dateFrom, $dateTo))->take(12)->values(),
            ],
            'trial_balance' => [
                'rows' => $balances->take(20)->values(),
                'totals' => [
                    'debits' => $this->formatCurrency($balances->sum('debit_total')),
                    'credits' => $this->formatCurrency($balances->sum('credit_total')),
                ],
            ],
            'profit_loss' => [
                'revenue_accounts' => $balances->where('type', 'revenue')->take(12)->values(),
                'expense_accounts' => $balances->where('type', 'expense')->take(12)->values(),
                'totals' => [
                    'revenue' => $this->formatCurrency($profitLoss['revenue_raw']),
                    'expenses' => $this->formatCurrency($profitLoss['expenses_raw']),
                    'net_income' => $this->formatCurrency($profitLoss['net_income_raw']),
                ],
            ],
            'balance_sheet' => [
                'asset_accounts' => $assetAccounts->take(12)->values(),
                'liability_accounts' => $liabilityAccounts->take(12)->values(),
                'equity_accounts' => $equityAccounts->take(12)->values(),
                'totals' => [
                    'assets' => $this->formatCurrency($assetAccounts->sum('balance')),
                    'liabilities_and_equity' => $this->formatCurrency(
                        $liabilityAccounts->sum('balance') + $equityAccounts->sum('balance') + $profitLoss['net_income_raw']
                    ),
                ],
            ],
            'accounts_receivable' => [
                'rows' => $this->buildAccountMovementPreview('accounts_receivable', $dateFrom, $dateTo),
            ],
            'accounts_payable' => [
                'rows' => $this->buildAccountMovementPreview('accounts_payable', $dateFrom, $dateTo),
            ],
            'chart_of_accounts' => [
                'rows' => $balances->map(fn ($account) => [
                    'id' => $account['id'],
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'type' => Str::of($account['type'] ?? '')->replace('_', ' ')->title()->value(),
                    'subtype' => $account['subtype']
                        ? Str::of($account['subtype'])->replace('_', ' ')->title()->value()
                        : 'General',
                    'status' => $account['is_active'] ? 'Active' : 'Inactive',
                    'balance_formatted' => $account['balance_formatted'],
                ])->take(20)->values(),
            ],
            'journal_entries' => [
                'rows' => $this->buildJournalEntryPreviewRows($dateFrom, $dateTo),
            ],
        ];
    }

    private function buildGeneralLedgerSummaryCards(Collection $balances): array
    {
        return [
            $this->makeMetricCard('Accounts', $balances->count(), 'Accounts in the general ledger.', 'ri-book-open-line'),
            $this->makeMetricCard('Active Accounts', Schema::hasTable('accounts') ? Account::where('is_active', true)->count() : 0, 'Accounts currently marked active.', 'ri-checkbox-circle-line'),
            $this->makeMetricCard('Posted Entries', Schema::hasTable('journal_entries') ? JournalEntry::count() : 0, 'Journal entries available for drill-down.', 'ri-file-list-3-line'),
            $this->makeMetricCard('Latest Posting', Schema::hasTable('journal_entries') ? (optional(JournalEntry::latest('entry_date')->first())->entry_date?->format('Y-m-d') ?: 'N/A') : 'N/A', 'Most recent accounting date.', 'ri-calendar-check-line'),
        ];
    }

    private function buildRecentLedgerLines(?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (!$this->hasCoreAccountingTables()) {
            return [];
        }

        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->select([
                'jel.id',
                'je.journal_number',
                'je.entry_date',
                'je.entry_type',
                'a.code as account_code',
                'a.name as account_name',
                'jel.account',
                'jel.line_type',
                'jel.amount',
                'jel.description',
            ]);

        $this->applyDateRange($query, 'je.entry_date', $dateFrom, $dateTo);

        return $query
            ->orderByDesc('je.entry_date')
            ->orderByDesc('jel.id')
            ->limit(20)
            ->get()
            ->map(fn ($line) => [
                'id' => $line->id,
                'journal_number' => $line->journal_number,
                'entry_date' => $line->entry_date,
                'entry_type' => Str::of($line->entry_type)->replace('_', ' ')->title()->value(),
                'account_code' => $line->account_code,
                'account_name' => $line->account_name,
                'account' => $line->account,
                'line_type' => Str::of($line->line_type)->title()->value(),
                'amount' => $this->formatCurrency($line->amount),
                'description' => $line->description,
            ])
            ->values()
            ->all();
    }

    private function buildAccountMovementPreview(string $slug, ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        if (!$this->hasCoreAccountingTables()) {
            return collect();
        }

        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('a.slug', $slug)
            ->select([
                'jel.id',
                'je.journal_number',
                'je.entry_date',
                'je.entry_type',
                'jel.line_type',
                'jel.amount',
                'jel.description',
            ]);

        $this->applyDateRange($query, 'je.entry_date', $dateFrom, $dateTo);

        return $query
            ->orderByDesc('je.entry_date')
            ->orderByDesc('jel.id')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'journal_number' => $row->journal_number,
                'entry_date' => $row->entry_date,
                'entry_type' => Str::of($row->entry_type)->replace('_', ' ')->title()->value(),
                'line_type' => Str::of($row->line_type)->title()->value(),
                'amount' => $this->formatCurrency($row->amount),
                'description' => $row->description ?: '-',
            ])
            ->values();
    }

    private function buildJournalEntryPreviewRows(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        if (!Schema::hasTable('journal_entries')) {
            return collect();
        }

        $canLoadLines = Schema::hasTable('journal_entry_lines');
        $query = JournalEntry::query()
            ->select([
                'id',
                'journal_number',
                'entry_date',
                'entry_type',
                'status',
                'memo',
            ]);

        if ($canLoadLines) {
            $query->with('lines');
        }

        $this->applyDateRange($query, 'entry_date', $dateFrom, $dateTo);

        return $query
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(function (JournalEntry $entry) use ($canLoadLines) {
                return [
                    'id' => $entry->id,
                    'journal_number' => $entry->journal_number,
                    'entry_date' => optional($entry->entry_date)->format('Y-m-d'),
                    'entry_type' => Str::of($entry->entry_type)->replace('_', ' ')->title()->value(),
                    'status' => Str::of($entry->status)->replace('_', ' ')->title()->value(),
                    'memo' => $entry->memo ?: '-',
                    'lines' => $canLoadLines
                        ? $entry->lines->map(fn (JournalEntryLine $line) => $this->transformJournalEntryLine($line))->values()->all()
                        : [],
                ];
            })
            ->values();
    }

    private function buildJournalEntryReportEntries(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        if (!$this->hasCoreAccountingTables()) {
            return collect();
        }

        $query = JournalEntry::query()
            ->select([
                'id',
                'journal_number',
                'entry_date',
                'memo',
            ])
            ->with([
                'lines' => function ($lineQuery) {
                    $lineQuery
                        ->select([
                            'id',
                            'journal_entry_id',
                            'account_id',
                            'account',
                            'line_type',
                            'amount',
                            'description',
                            'line_order',
                        ])
                        ->orderBy('line_order')
                        ->orderBy('id');
                },
            ]);

        $this->applyDateRange($query, 'entry_date', $dateFrom, $dateTo);

        return $query
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get()
            ->map(function (JournalEntry $entry) {
                return [
                    'journal_number' => $entry->journal_number ?: '#',
                    'entry_date' => optional($entry->entry_date)->format('Y-m-d'),
                    'memo' => $entry->memo ?: '',
                    'lines' => $entry->lines
                        ->map(function (JournalEntryLine $line) {
                            return [
                                'account' => trim((string) $line->getAttribute('account')) ?: 'Unmapped Account',
                                'line_type' => $line->line_type,
                                'amount' => (float) $line->amount,
                                'description' => $line->description,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values();
    }

    private function transformJournalEntryLine(JournalEntryLine $line): array
    {
        $lineAccount = $line->getAttribute('account');

        return [
            'id' => $line->id,
            'account' => $lineAccount,
            'account_name' => $lineAccount,
            'account_code' => null,
            'line_type' => $line->line_type,
            'amount' => number_format((float) $line->amount, 2, '.', ','),
            'description' => $line->description,
        ];
    }

    private function buildAccountBalances(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        if (!$this->hasCoreAccountingTables()) {
            return collect();
        }

        $accounts = Account::query()
            ->select(['id', 'code', 'slug', 'name', 'type', 'subtype', 'is_active'])
            ->orderBy('code')
            ->get();

        $lineTotalsQuery = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->select([
                'jel.account_id',
                DB::raw("COALESCE(SUM(CASE WHEN jel.line_type = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total"),
                DB::raw("COALESCE(SUM(CASE WHEN jel.line_type = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total"),
            ]);

        $this->applyDateRange($lineTotalsQuery, 'je.entry_date', $dateFrom, $dateTo);

        $lineTotals = $lineTotalsQuery
            ->groupBy('jel.account_id')
            ->get()
            ->keyBy('account_id');

        return $accounts->map(function ($account) use ($lineTotals) {
                $totals = $lineTotals->get($account->id);
                $debitTotal = (float) ($totals->debit_total ?? 0);
                $creditTotal = (float) ($totals->credit_total ?? 0);
                $balance = $this->calculateAccountBalance($account->type, $debitTotal, $creditTotal);

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'slug' => $account->slug,
                    'name' => $account->name,
                    'type' => $account->type,
                    'subtype' => $account->subtype,
                    'is_active' => (bool) $account->is_active,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'balance' => $balance,
                    'debit_total_formatted' => $this->formatCurrency($debitTotal),
                    'credit_total_formatted' => $this->formatCurrency($creditTotal),
                    'balance_formatted' => $this->formatCurrency($balance),
                ];
            })
            ->values();
    }

    private function resolveDateRange(Request $request): array
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->string('date_from'))->format('Y-m-d')
            : null;
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->string('date_to'))->format('Y-m-d')
            : null;

        return [$dateFrom, $dateTo];
    }

    private function applyDateRange($query, string $column, ?string $dateFrom, ?string $dateTo): void
    {
        if ($dateFrom) {
            $query->whereDate($column, '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate($column, '<=', $dateTo);
        }
    }

    private function buildProfitLossTotals(Collection $balances): array
    {
        $revenue = (float) $balances->where('type', 'revenue')->sum('balance');
        $expenses = (float) $balances->where('type', 'expense')->sum('balance');
        $costOfSales = (float) $balances->where('type', 'expense')->where('subtype', 'cost_of_sales')->sum('balance');
        $operatingExpenses = round($expenses - $costOfSales, 2);
        $netIncome = round($revenue - $expenses, 2);

        return [
            'revenue_raw' => $revenue,
            'cost_of_sales_raw' => $costOfSales,
            'operating_expenses_raw' => $operatingExpenses,
            'expenses_raw' => $expenses,
            'net_income_raw' => $netIncome,
        ];
    }

    private function journalEntryLists(Request $request)
    {
        if (!Schema::hasTable('journal_entries')) {
            return new LengthAwarePaginator(
                items: collect(),
                total: 0,
                perPage: (int) ($request->count ?? 10),
                currentPage: LengthAwarePaginator::resolveCurrentPage(),
                options: [
                    'path' => url('/api/accounting/journal-entries'),
                ]
            );
        }

        $hasReversalColumns = $this->hasJournalReversalColumns();
        $withRelations = ['lines'];

        if ($hasReversalColumns) {
            $withRelations[] = 'reversalOf';
            $withRelations[] = 'reversals';
        }

        return JournalEntry::with($withRelations)
            ->when($request->keyword, function ($query, $keyword) {
                $query->where(function ($inner) use ($keyword) {
                    $inner->where('journal_number', 'like', '%' . $keyword . '%')
                        ->orWhere('memo', 'like', '%' . $keyword . '%')
                        ->orWhere('entry_type', 'like', '%' . $keyword . '%')
                        ->orWhere('status', 'like', '%' . $keyword . '%');
                });
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->entry_type, function ($query, $entryType) {
                $query->where('entry_type', $entryType);
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($request->count ?? 10)
            ->through(function (JournalEntry $entry) use ($hasReversalColumns) {
                return [
                    'id' => $entry->id,
                    'journal_number' => $entry->journal_number,
                    'entry_date' => optional($entry->entry_date)->format('Y-m-d'),
                    'entry_type' => Str::of($entry->entry_type)->replace('_', ' ')->title()->value(),
                    'status' => $entry->status,
                    'memo' => $entry->memo,
                    'source_type' => class_basename($entry->source_type ?? ''),
                    'source_id' => $entry->source_id,
                    'reversal_of' => $hasReversalColumns && $entry->reversalOf ? [
                        'id' => $entry->reversalOf->id,
                        'journal_number' => $entry->reversalOf->journal_number,
                        'entry_date' => optional($entry->reversalOf->entry_date)->format('Y-m-d'),
                    ] : null,
                    'reversals' => $hasReversalColumns
                        ? $entry->reversals->map(fn ($reversal) => [
                            'id' => $reversal->id,
                            'journal_number' => $reversal->journal_number,
                            'entry_date' => optional($reversal->entry_date)->format('Y-m-d'),
                            'status' => $reversal->status,
                        ])->values()
                        : [],
                    'reversed_at' => $hasReversalColumns ? $entry->reversed_at?->format('Y-m-d H:i:s') : null,
                    'reversal_reason' => $hasReversalColumns ? $entry->reversal_reason : null,
                    'lines' => $entry->lines->map(fn (JournalEntryLine $line) => $this->transformJournalEntryLine($line))->values(),
                ];
            });
    }

    private function buildJournalEntryFeatures(): array
    {
        $reversalReady = $this->hasJournalReversalColumns();

        return [
            'reversal_ready' => $reversalReady,
            'compatibility_message' => $reversalReady
                ? null
                : 'The journal_entries table is missing reversal tracking columns (reversal_of_id, reversed_at, reversal_reason). Apply the latest accounting migration to enable original-to-reversal links.',
        ];
    }

    private function buildJournalEntryTypes(): array
    {
        if (!Schema::hasTable('journal_entries')) {
            return [];
        }

        return JournalEntry::query()
            ->whereNotNull('entry_type')
            ->where('entry_type', '!=', '')
            ->orderBy('entry_type')
            ->distinct()
            ->pluck('entry_type')
            ->values()
            ->all();
    }

    private function hasCoreAccountingTables(): bool
    {
        return Schema::hasTable('accounts')
            && Schema::hasTable('journal_entries')
            && Schema::hasTable('journal_entry_lines');
    }

    private function hasJournalReversalColumns(): bool
    {
        if (!Schema::hasTable('journal_entries')) {
            return false;
        }

        foreach (self::JOURNAL_ENTRY_COLUMNS as $column) {
            if (!Schema::hasColumn('journal_entries', $column)) {
                return false;
            }
        }

        return true;
    }

    private function calculateAccountBalance(?string $type, float $debitTotal, float $creditTotal): float
    {
        return match ($type) {
            'asset', 'expense' => round($debitTotal - $creditTotal, 2),
            'liability', 'equity', 'revenue' => round($creditTotal - $debitTotal, 2),
            default => round($debitTotal - $creditTotal, 2),
        };
    }

    private function makeMetricCard(string $title, mixed $value, string $description, string $icon): array
    {
        return [
            'title' => $title,
            'value' => $value,
            'description' => $description,
            'icon' => $icon,
        ];
    }

    private function formatCurrency(float|int|null $value): string
    {
        return 'P ' . number_format((float) $value, 2, '.', ',');
    }
}
