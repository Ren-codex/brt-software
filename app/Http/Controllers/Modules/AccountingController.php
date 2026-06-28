<?php

namespace App\Http\Controllers\Modules;

use App\Exports\AccountingReportExport;
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
use Maatwebsite\Excel\Facades\Excel;

class AccountingController extends Controller
{
    private const JOURNAL_ENTRY_COLUMNS = [
        'reversal_of_id',
        'reversed_at',
        'reversal_reason',
    ];

    public function index(Request $request)
    {
        if (!$request->option && $request->tab === 'accounts_payable') {
            return redirect('/inventory?tab=accountsPayable');
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

        $dataReady  = $this->hasCoreAccountingTables();
        $balances   = $dataReady ? $this->buildAccountBalances($dateFrom, $dateTo) : collect();
        $plTotals   = $this->buildProfitLossTotals($balances);

        $totalAssets      = (float) $balances->where('type', 'asset')->sum('balance');
        $totalLiabilities = (float) $balances->where('type', 'liability')->sum('balance');
        $totalEquity      = (float) $balances->where('type', 'equity')->sum('balance');
        $totalRevenue     = $plTotals['revenue_raw'];
        $netIncome        = $plTotals['net_income_raw'];

        $totalCash     = $dataReady ? $this->buildTotalCash() : ['formatted' => $this->formatCurrency(0), 'raw' => 0.0];
        $outstandingAr = $this->buildOutstandingArTotal();

        $kpiCards = [
            [
                'title'       => 'Net Income',
                'value'       => $this->formatCurrency($netIncome),
                'description' => $netIncome >= 0 ? 'Profit for the period' : 'Net loss for the period',
                'icon'        => $netIncome >= 0 ? 'ri-arrow-up-circle-line' : 'ri-arrow-down-circle-line',
            ],
            [
                'title'       => 'Total Revenue',
                'value'       => $this->formatCurrency($totalRevenue),
                'description' => 'Gross revenue for the period',
                'icon'        => 'ri-funds-line',
            ],
            [
                'title'       => 'Total Cash',
                'value'       => $totalCash['formatted'],
                'description' => 'Bank & petty cash (live GL)',
                'icon'        => 'ri-wallet-3-line',
            ],
            [
                'title'       => 'Outstanding AR',
                'value'       => $outstandingAr['formatted'],
                'description' => 'Unpaid customer invoices',
                'icon'        => 'ri-file-list-3-line',
            ],
        ];

        $grossProfit = round($totalRevenue - $plTotals['cost_of_sales_raw'], 2);

        return inertia('Modules/Accounting/Dashboard', [
            'stats'               => $this->buildStats($dateFrom, $dateTo),
            'dataReady'           => $dataReady,
            'filters'             => ['date_from' => $dateFrom, 'date_to' => $dateTo],
            'kpiCards'            => $kpiCards,
            'plSummary'           => [
                'revenue'            => $this->formatCurrency($totalRevenue),
                'cost_of_sales'      => $this->formatCurrency($plTotals['cost_of_sales_raw']),
                'gross_profit'       => $this->formatCurrency($grossProfit),
                'gross_profit_raw'   => $grossProfit,
                'operating_expenses' => $this->formatCurrency($plTotals['operating_expenses_raw']),
                'net_income'         => $this->formatCurrency($netIncome),
                'net_income_raw'     => $netIncome,
            ],
            'bsSummary'           => [
                'total_assets'           => $this->formatCurrency($totalAssets),
                'total_liabilities'      => $this->formatCurrency($totalLiabilities),
                'total_equity'           => $this->formatCurrency($totalEquity),
                'liabilities_and_equity' => $this->formatCurrency($totalLiabilities + $totalEquity),
                'is_balanced'            => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
            ],
            'recentEntries'       => $this->buildDashboardRecentEntries($dateFrom, $dateTo),
            'topAccounts'         => $dataReady
                ? $balances->sortByDesc('debit_total')->take(5)->map(fn ($a) => [
                    'code'        => $a['code'],
                    'name'        => $a['name'],
                    'type'        => $a['type'],
                    'balance'     => $a['balance_formatted'],
                    'debit_total' => $a['debit_total_formatted'],
                  ])->values()->all()
                : [],
            'totalCash'           => $totalCash,
            'outstandingAr'       => $outstandingAr,
            'arAgingBuckets'      => $this->buildArAgingSummary(),
            'apAgingBuckets'      => $this->buildApAgingSummary(),
            'revenueExpenseChart' => $dataReady ? $this->buildRevenueExpenseChart() : ['labels' => [], 'revenue' => [], 'expenses' => []],
            'bankBalances'        => $dataReady ? $this->buildBankBalanceSummary() : [],
        ]);
    }

    public function journalEntries(Request $request)
    {
        if ($request->option === 'lists') {
            return $this->journalEntryLists($request);
        }

        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $accounts = $this->hasCoreAccountingTables()
            ? DB::table('accounts')
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type'])
                ->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type])
                ->values()->all()
            : [];

        return inertia('Modules/Accounting/JournalEntries', [
            'stats'           => $this->buildStats($dateFrom, $dateTo),
            'accounts'        => $accounts,
            'journalFeatures' => [
                'reversal_ready'        => $this->hasJournalReversalColumns(),
                'compatibility_message' => $this->hasJournalReversalColumns()
                    ? null
                    : 'Reversal columns not yet migrated. Run <code>php artisan migrate</code> to enable reversals.',
            ],
        ]);
    }

    public function storeManualJournal(Request $request)
    {
        $data = $request->validate([
            'entry_date' => 'required|date',
            'memo'       => 'nullable|string|max:500',
            'lines'      => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.line_type'  => 'required|in:debit,credit',
            'lines.*.amount'     => 'required|numeric|min:0.01',
            'lines.*.description'=> 'nullable|string|max:255',
        ]);

        $debitTotal  = collect($data['lines'])->where('line_type', 'debit')->sum('amount');
        $creditTotal = collect($data['lines'])->where('line_type', 'credit')->sum('amount');

        if (abs($debitTotal - $creditTotal) > 0.01) {
            return response()->json([
                'message' => 'Journal entry must balance: total debits must equal total credits.',
                'errors'  => ['lines' => ['Debits (' . number_format($debitTotal, 2) . ') ≠ Credits (' . number_format($creditTotal, 2) . ')']],
            ], 422);
        }

        $entryDate = Carbon::parse($data['entry_date']);

        DB::transaction(function () use ($data, $entryDate) {
            $entry = JournalEntry::create([
                'journal_number' => JournalEntry::generateJournalNumber($entryDate),
                'entry_date'     => $entryDate->format('Y-m-d'),
                'entry_type'     => 'manual',
                'source_type'    => null,
                'source_id'      => null,
                'memo'           => $data['memo'] ?? null,
                'status'         => 'posted',
                'posted_at'      => now(),
            ]);

            foreach ($data['lines'] as $order => $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'line_type'        => $line['line_type'],
                    'amount'           => $line['amount'],
                    'description'      => $line['description'] ?? null,
                    'line_order'       => $order + 1,
                ]);
            }
        });

        return response()->json(['message' => 'Journal entry posted successfully.'], 201);
    }

    public function accountsReceivable(Request $request)
    {
        if (!Schema::hasTable('ar_invoices')) {
            return inertia('Modules/Accounting/AccountsReceivable', [
                'stats' => [],
                'dataReady' => false,
                'summaryCards' => [],
                'agingRows' => [],
                'invoiceRows' => [],
                'filters' => [],
            ]);
        }

        $keyword = $request->string('keyword')->toString();
        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->string('as_of'))->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        // Effective due date: ar_invoice.due_date → sales_order.due_date → invoice_date
        $eff = "COALESCE(ai.due_date, so.due_date, ai.invoice_date)";

        $agingRows = DB::table('ar_invoices as ai')
            ->join('sales_orders as so', 'so.id', '=', 'ai.sales_order_id')
            ->join('customers as c', 'c.id', '=', 'so.customer_id')
            ->where('ai.balance_due', '>', 0)
            ->when($keyword, fn ($q) => $q->where('c.name', 'like', '%' . $keyword . '%'))
            ->select([
                'c.id as customer_id',
                'c.name as customer_name',
                DB::raw("SUM(ai.balance_due) as total_outstanding"),
                DB::raw("SUM(CASE WHEN {$eff} >= '{$asOf}' THEN ai.balance_due ELSE 0 END) as current_bucket"),
                DB::raw("SUM(CASE WHEN {$eff} < '{$asOf}' AND DATEDIFF('{$asOf}', {$eff}) BETWEEN 1 AND 30 THEN ai.balance_due ELSE 0 END) as days_1_30"),
                DB::raw("SUM(CASE WHEN {$eff} < '{$asOf}' AND DATEDIFF('{$asOf}', {$eff}) BETWEEN 31 AND 60 THEN ai.balance_due ELSE 0 END) as days_31_60"),
                DB::raw("SUM(CASE WHEN {$eff} < '{$asOf}' AND DATEDIFF('{$asOf}', {$eff}) BETWEEN 61 AND 90 THEN ai.balance_due ELSE 0 END) as days_61_90"),
                DB::raw("SUM(CASE WHEN {$eff} < '{$asOf}' AND DATEDIFF('{$asOf}', {$eff}) > 90 THEN ai.balance_due ELSE 0 END) as days_90_plus"),
                DB::raw("COUNT(ai.id) as invoice_count"),
            ])
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('total_outstanding')
            ->get()
            ->map(fn ($row) => [
                'customer_id'       => $row->customer_id,
                'customer_name'     => $row->customer_name,
                'invoice_count'     => $row->invoice_count,
                'total_outstanding' => $this->formatCurrency($row->total_outstanding),
                'current'           => $this->formatCurrency($row->current_bucket),
                'days_1_30'         => $this->formatCurrency($row->days_1_30),
                'days_31_60'        => $this->formatCurrency($row->days_31_60),
                'days_61_90'        => $this->formatCurrency($row->days_61_90),
                'days_90_plus'      => $this->formatCurrency($row->days_90_plus),
                'has_overdue'       => ($row->days_1_30 + $row->days_31_60 + $row->days_61_90 + $row->days_90_plus) > 0,
                'total_raw'         => (float) $row->total_outstanding,
            ]);

        $invoiceRows = DB::table('ar_invoices as ai')
            ->join('sales_orders as so', 'so.id', '=', 'ai.sales_order_id')
            ->join('customers as c', 'c.id', '=', 'so.customer_id')
            ->where('ai.balance_due', '>', 0)
            ->when($keyword, fn ($q) => $q->where('c.name', 'like', '%' . $keyword . '%'))
            ->select([
                'ai.id',
                'ai.invoice_number',
                'ai.invoice_date',
                'ai.due_date',
                DB::raw("{$eff} as effective_due_date"),
                'ai.amount_paid',
                'ai.balance_due',
                'c.name as customer_name',
                DB::raw("CASE WHEN {$eff} >= '{$asOf}' THEN 0 ELSE DATEDIFF('{$asOf}', {$eff}) END as days_overdue"),
            ])
            ->orderByDesc(DB::raw("CASE WHEN {$eff} >= '{$asOf}' THEN 0 ELSE DATEDIFF('{$asOf}', {$eff}) END"))
            ->orderBy('c.name')
            ->get()
            ->map(fn ($row) => [
                'id'                 => $row->id,
                'invoice_number'     => $row->invoice_number,
                'invoice_date'       => $row->invoice_date,
                'due_date'           => $row->due_date,
                'effective_due_date' => $row->effective_due_date,
                'customer_name'      => $row->customer_name,
                'amount_paid'        => $this->formatCurrency($row->amount_paid),
                'balance_due'        => $this->formatCurrency($row->balance_due),
                'days_overdue'       => (int) $row->days_overdue,
                'aging_bucket'       => $this->classifyAgingBucket((int) $row->days_overdue),
            ]);

        $totalOutstanding = $agingRows->sum('total_raw');
        $overdueRows = $agingRows->where('has_overdue', true);

        return inertia('Modules/Accounting/AccountsReceivable', [
            'stats' => $this->buildStats(),
            'dataReady' => true,
            'summaryCards' => [
                $this->makeMetricCard('Total Outstanding', $this->formatCurrency($totalOutstanding), 'Sum of all unpaid invoice balances.', 'ri-file-list-3-line'),
                $this->makeMetricCard('Customers with AR', $agingRows->count(), 'Customers with at least one open invoice.', 'ri-group-line'),
                $this->makeMetricCard('Overdue Customers', $overdueRows->count(), 'Customers with invoices past due date.', 'ri-alarm-warning-line'),
                $this->makeMetricCard('Open Invoices', $invoiceRows->count(), 'Total invoices with outstanding balance.', 'ri-coupon-3-line'),
            ],
            'agingRows' => $agingRows->values(),
            'invoiceRows' => $invoiceRows->values(),
            'filters' => ['as_of' => $asOf, 'keyword' => $keyword],
        ]);
    }

    private function classifyAgingBucket(int $daysOverdue): string
    {
        if ($daysOverdue === 0) return 'current';
        if ($daysOverdue <= 30) return '1-30';
        if ($daysOverdue <= 60) return '31-60';
        if ($daysOverdue <= 90) return '61-90';
        return '90+';
    }

    public function accountsPayable(Request $request)
    {
        if (!Schema::hasTable('received_stocks') || !Schema::hasTable('received_items')) {
            return inertia('Modules/Accounting/AccountsPayable', [
                'stats'        => [],
                'dataReady'    => false,
                'summaryCards' => [],
                'agingRows'    => [],
                'receivingRows'=> [],
                'filters'      => [],
            ]);
        }

        $keyword = $request->string('keyword')->toString();
        $asOf    = $request->filled('as_of')
            ? Carbon::parse($request->string('as_of'))->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        if ($request->option === 'excel') {
            $data    = $this->buildApAgingData($keyword, $asOf);
            $filters = ['as_of' => $asOf, 'keyword' => $keyword];
            return Excel::download(
                new AccountingReportExport('accounts_payable', $data, $filters),
                'ap-aging-' . now()->format('Ymd') . '.xlsx'
            );
        }

        if ($request->option === 'pdf') {
            $data    = $this->buildApAgingData($keyword, $asOf);
            $filters = ['as_of' => $asOf, 'keyword' => $keyword];
            return \PDF::loadView('prints.accounting-accounts-payable', array_merge($data, ['filters' => $filters]))
                ->setPaper('A4', 'landscape')
                ->download('ap-aging-' . now()->format('Ymd') . '.pdf');
        }

        $data = $this->buildApAgingData($keyword, $asOf);
        $totalOutstanding = collect($data['agingRows'])->sum('total_raw');
        $overdueCount     = collect($data['agingRows'])->where('has_overdue', true)->count();

        return inertia('Modules/Accounting/AccountsPayable', [
            'stats'         => $this->buildStats(),
            'dataReady'     => true,
            'summaryCards'  => [
                $this->makeMetricCard('Total Outstanding',      $this->formatCurrency($totalOutstanding),          'Sum of all unpaid supplier balances.',               'ri-wallet-3-line'),
                $this->makeMetricCard('Suppliers with AP',      collect($data['agingRows'])->count(),               'Suppliers with at least one open receiving record.',  'ri-building-2-line'),
                $this->makeMetricCard('Overdue Suppliers',      $overdueCount,                                      'Suppliers with balances past receiving date.',        'ri-alarm-warning-line'),
                $this->makeMetricCard('Open Receivings',        collect($data['receivingRows'])->count(),           'Receiving records with outstanding balance.',         'ri-inbox-archive-line'),
            ],
            'agingRows'     => $data['agingRows'],
            'receivingRows' => $data['receivingRows'],
            'filters'       => ['as_of' => $asOf, 'keyword' => $keyword],
        ]);
    }

    private function buildApAgingData(string $keyword, string $asOf): array
    {
        // Per-receiving balance: SUM(items.total_cost) - SUM(payments.amount_paid)
        $receivings = DB::table('received_stocks as rs')
            ->join('list_suppliers as s', 's.id', '=', 'rs.supplier_id')
            ->leftJoin('received_items as ri', 'ri.received_id', '=', 'rs.id')
            ->leftJoin('received_stock_payments as rsp', 'rsp.received_stock_id', '=', 'rs.id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'rs.po_id')
            ->when($keyword, fn ($q) => $q->where(function ($inner) use ($keyword) {
                $inner->where('s.name', 'like', '%' . $keyword . '%')
                      ->orWhere('rs.received_no', 'like', '%' . $keyword . '%')
                      ->orWhere('po.po_number', 'like', '%' . $keyword . '%');
            }))
            ->select([
                'rs.id',
                'rs.received_no',
                'rs.received_date',
                's.id as supplier_id',
                's.name as supplier_name',
                'po.po_number',
                DB::raw('COALESCE(SUM(DISTINCT ri.total_cost * 1), 0) as total_cost'),
                DB::raw('COALESCE(rs.amount_paid, 0) as amount_paid_legacy'),
                DB::raw('COALESCE(SUM(DISTINCT rsp.amount_paid * 1), 0) as payments_total'),
            ])
            ->groupBy('rs.id', 'rs.received_no', 'rs.received_date', 's.id', 's.name', 'po.po_number', 'rs.amount_paid')
            ->get()
            ->map(function ($row) use ($asOf) {
                $totalCost    = (float) $row->total_cost;
                $paid         = (float) max($row->amount_paid_legacy, $row->payments_total);
                $balance      = round(max($totalCost - $paid, 0), 2);
                $daysOut      = (int) max(0, Carbon::parse($row->received_date)->diffInDays(Carbon::parse($asOf), false));

                return (object) [
                    'id'            => $row->id,
                    'received_no'   => $row->received_no,
                    'received_date' => $row->received_date,
                    'supplier_id'   => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'po_number'     => $row->po_number,
                    'total_cost'    => $totalCost,
                    'amount_paid'   => $paid,
                    'balance_due'   => $balance,
                    'days_out'      => $daysOut,
                    'bucket'        => $this->classifyAgingBucket($daysOut),
                ];
            })
            ->filter(fn ($r) => $r->balance_due > 0.009);

        // Supplier-level aging summary
        $agingRows = $receivings
            ->groupBy('supplier_id')
            ->map(function ($rows, $supplierId) {
                $first         = $rows->first();
                $totalOut      = $rows->sum('balance_due');
                $current       = $rows->where('bucket', 'current')->sum('balance_due');
                $d1_30         = $rows->where('bucket', '1-30')->sum('balance_due');
                $d31_60        = $rows->where('bucket', '31-60')->sum('balance_due');
                $d61_90        = $rows->where('bucket', '61-90')->sum('balance_due');
                $d90plus       = $rows->where('bucket', '90+')->sum('balance_due');

                return [
                    'supplier_id'   => $supplierId,
                    'supplier_name' => $first->supplier_name,
                    'record_count'  => $rows->count(),
                    'total_outstanding' => $this->formatCurrency($totalOut),
                    'current'       => $this->formatCurrency($current),
                    'days_1_30'     => $this->formatCurrency($d1_30),
                    'days_31_60'    => $this->formatCurrency($d31_60),
                    'days_61_90'    => $this->formatCurrency($d61_90),
                    'days_90_plus'  => $this->formatCurrency($d90plus),
                    'has_overdue'   => ($d1_30 + $d31_60 + $d61_90 + $d90plus) > 0,
                    'total_raw'     => $totalOut,
                ];
            })
            ->sortByDesc('total_raw')
            ->values();

        // Per-receiving detail rows
        $receivingRows = $receivings
            ->sortByDesc('days_out')
            ->map(fn ($r) => [
                'id'            => $r->id,
                'received_no'   => $r->received_no,
                'received_date' => $r->received_date,
                'supplier_name' => $r->supplier_name,
                'po_number'     => $r->po_number ?: '—',
                'total_cost'    => $this->formatCurrency($r->total_cost),
                'amount_paid'   => $this->formatCurrency($r->amount_paid),
                'balance_due'   => $this->formatCurrency($r->balance_due),
                'days_out'      => $r->days_out,
                'bucket'        => $r->bucket,
            ])
            ->values();

        return ['agingRows' => $agingRows, 'receivingRows' => $receivingRows];
    }

    public function chartOfAccounts()
    {
        if (!Schema::hasTable('accounts')) {
            return inertia('Modules/Accounting/ChartOfAccounts', [
                'stats' => [],
                'dataReady' => false,
                'summaryCards' => [],
                'rows' => [],
            ]);
        }

        $balances = $this->buildAccountBalances();

        return inertia('Modules/Accounting/ChartOfAccounts', [
            'stats' => $this->buildStats(),
            'dataReady' => true,
            'summaryCards' => [
                $this->makeMetricCard('Total Accounts', $balances->count(), 'All accounts in the chart.', 'ri-node-tree'),
                $this->makeMetricCard('Active', $balances->where('is_active', true)->count(), 'Accounts available for posting.', 'ri-checkbox-circle-line'),
                $this->makeMetricCard('Inactive', $balances->where('is_active', false)->count(), 'Accounts disabled from posting.', 'ri-forbid-line'),
                $this->makeMetricCard('Types', $balances->pluck('type')->unique()->count(), 'Distinct account types in use.', 'ri-stack-line'),
            ],
            'rows' => $balances->values(),
        ]);
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'code'     => 'required|string|max:20|unique:accounts,code',
            'name'     => 'required|string|max:120',
            'type'     => 'required|in:asset,liability,equity,revenue,expense',
            'subtype'  => 'nullable|string|max:60',
            'is_active'=> 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        Account::create($data);

        return response()->json(['message' => 'Account created successfully.']);
    }

    public function updateAccount(Request $request, int $id)
    {
        $account = Account::findOrFail($id);

        $data = $request->validate([
            'code'    => 'required|string|max:20|unique:accounts,code,' . $id,
            'name'    => 'required|string|max:120',
            'type'    => 'required|in:asset,liability,equity,revenue,expense',
            'subtype' => 'nullable|string|max:60',
        ]);

        $account->update($data);

        return response()->json(['message' => 'Account updated successfully.']);
    }

    public function toggleAccount(int $id)
    {
        $account = Account::findOrFail($id);
        $account->update(['is_active' => !$account->is_active]);

        return response()->json(['message' => 'Account status updated.']);
    }

    public function destroyAccount(int $id)
    {
        $account = Account::findOrFail($id);
        $account->delete();

        return response()->json(['message' => 'Account deleted.']);
    }

    public function settings()
    {
        $dataReady    = Schema::hasTable('accounts');
        $balances     = $dataReady ? $this->buildAccountBalances() : collect();
        $bankAccounts = \App\Models\BankAccount::orderBy('bank_name')->orderBy('account_name')->get();

        return inertia('Modules/Accounting/AccountingSettings', [
            'stats'            => $this->buildStats(),
            'dataReady'        => $dataReady,
            'summaryCards'     => $dataReady ? [
                $this->makeMetricCard('Total Accounts', $balances->count(), 'All accounts in the chart.', 'ri-node-tree'),
                $this->makeMetricCard('Active', $balances->where('is_active', true)->count(), 'Accounts available for posting.', 'ri-checkbox-circle-line'),
                $this->makeMetricCard('Inactive', $balances->where('is_active', false)->count(), 'Accounts disabled from posting.', 'ri-forbid-line'),
                $this->makeMetricCard('Types', $balances->pluck('type')->unique()->count(), 'Distinct account types in use.', 'ri-stack-line'),
            ] : [],
            'accounts'         => $balances->values(),
            'bankAccounts'     => $bankAccounts,
            'bankSummaryCards' => [
                $this->makeMetricCard('Total Banks', $bankAccounts->count(), 'Configured bank accounts.', 'ri-bank-line'),
                $this->makeMetricCard('Active', $bankAccounts->where('is_active', true)->count(), 'Enabled for payment recording.', 'ri-checkbox-circle-line'),
            ],
        ]);
    }

    public function generalLedger(Request $request)
    {
        if ($request->option === 'account_lines') {
            return $this->accountLinesForDrilldown($request);
        }

        if ($request->option === 'journal_lines') {
            [$dateFrom, $dateTo] = $this->resolveDateRange($request);
            return response()->json($this->buildLedgerLines($request, $dateFrom, $dateTo));
        }

        if ($request->option === 'accounts_list') {
            if (!$this->hasCoreAccountingTables()) {
                return response()->json([]);
            }
            $sourceFilter = $request->string('source_filter')->toString();
            $q = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->join('accounts as a', 'a.id', '=', 'jel.account_id')
                ->select(['a.id', 'a.code', 'a.name', 'a.type'])
                ->distinct();
            if ($sourceFilter && $sourceFilter !== 'all') {
                $types = $this->entryTypesForSource($sourceFilter);
                if (!empty($types)) {
                    $q->whereIn('je.entry_type', $types);
                }
            }
            return response()->json($q->orderBy('a.code')->get());
        }

        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $balances    = $this->buildAccountBalances($dateFrom, $dateTo);
        $ledgerData  = $this->buildLedgerLines($request, $dateFrom, $dateTo);

        return inertia('Modules/Accounting/GeneralLedger', [
            'stats'           => $this->buildStats($dateFrom, $dateTo),
            'dataReady'       => $this->hasCoreAccountingTables(),
            'summaryCards'    => [
                $this->makeMetricCard('Accounts', $balances->count(), 'Accounts available in the ledger.', 'ri-book-open-line'),
                $this->makeMetricCard('Active', $balances->where('is_active', true)->count(), 'Accounts currently marked active.', 'ri-checkbox-circle-line'),
                $this->makeMetricCard('Debit Volume', $this->formatCurrency($balances->sum('debit_total')), 'Total debits across all accounts.', 'ri-arrow-left-down-line'),
                $this->makeMetricCard('Credit Volume', $this->formatCurrency($balances->sum('credit_total')), 'Total credits across all accounts.', 'ri-arrow-right-up-line'),
            ],
            'accountBalances' => $balances->values(),
            'ledgerLines'     => $ledgerData['data'],
            'ledgerStats'     => $ledgerData['stats'],
            'ledgerMeta'      => $ledgerData['meta'],
            'ledgerLinks'     => $ledgerData['links'],
            'filters'         => [
                'date_from'     => $dateFrom,
                'date_to'       => $dateTo,
                'search'        => $request->string('search')->toString(),
                'source_filter' => $request->string('source_filter')->toString(),
            ],
        ]);
    }

    private function buildLedgerLines(Request $request, ?string $dateFrom, ?string $dateTo): array
    {
        $empty = [
            'lines' => [],
            'stats' => [
                'total_debits'  => $this->formatCurrency(0),
                'total_credits' => $this->formatCurrency(0),
                'is_balanced'   => true,
                'entry_count'   => 0,
                'line_count'    => 0,
            ],
        ];

        if (!$this->hasCoreAccountingTables()) {
            return $empty;
        }

        $search       = $request->string('search')->toString();
        $sourceFilter = $request->string('source_filter')->toString();

        $base = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by_id');

        $this->applyDateRange($base, 'je.entry_date', $dateFrom, $dateTo);

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('jel.description', 'like', '%' . $search . '%')
                  ->orWhere('a.name', 'like', '%' . $search . '%')
                  ->orWhere('a.code', 'like', '%' . $search . '%')
                  ->orWhere('je.journal_number', 'like', '%' . $search . '%');
            });
        }

        if ($sourceFilter && $sourceFilter !== 'all') {
            $types = $this->entryTypesForSource($sourceFilter);
            if (!empty($types)) {
                $base->whereIn('je.entry_type', $types);
            }
        }

        $accountId = (int) $request->input('account_id', 0);
        if ($accountId > 0) {
            $base->where('jel.account_id', $accountId);
        }

        $statsRow = (clone $base)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN jel.line_type = 'debit'  THEN jel.amount ELSE 0 END), 0) as total_debits,
                COALESCE(SUM(CASE WHEN jel.line_type = 'credit' THEN jel.amount ELSE 0 END), 0) as total_credits,
                COUNT(DISTINCT je.id) as entry_count,
                COUNT(jel.id) as line_count
            ")
            ->first();

        $totalDebits  = (float) $statsRow->total_debits;
        $totalCredits = (float) $statsRow->total_credits;

        $perPage = max(1, min(100, (int) ($request->per_page ?? 10)));

        $paginator = (clone $base)
            ->select([
                'jel.id',
                'je.journal_number',
                'je.entry_date',
                'je.posted_at',
                'je.entry_type',
                'je.source_type',
                'je.source_id',
                'a.code as account_code',
                'a.name as account_name',
                'a.type as account_type',
                'jel.line_type',
                'jel.amount',
                'jel.description',
                'u.username as posted_by',
            ])
            ->orderByDesc('je.entry_date')
            ->orderByDesc('je.id')
            ->orderByDesc('jel.id')
            ->paginate($perPage);

        $pageCollection   = $paginator->getCollection();
        $sourceMap        = $this->batchResolveSourceRefs($pageCollection);
        $runningBalances  = $this->computeRunningBalances($pageCollection->pluck('id')->all());

        $paginator->through(function ($line) use ($sourceMap, $runningBalances) {
            $rb    = $runningBalances[(int) $line->id] ?? null;
            $rbDr  = $rb === null ? null : ($rb >= 0);

            return [
                'id'                      => $line->id,
                'journal_number'          => $line->journal_number,
                'entry_date'              => $line->entry_date,
                'post_date'               => $line->posted_at
                    ? Carbon::parse($line->posted_at)->format('Y-m-d')
                    : $line->entry_date,
                'account_code'            => $line->account_code,
                'account_name'            => $line->account_name,
                'account_type'            => $line->account_type,
                'entry_type'              => Str::of($line->entry_type)->replace('_', ' ')->title()->value(),
                'source_label'            => $this->resolveSourceLabel($line->entry_type),
                'reference'               => ($line->source_type && $line->source_id)
                    ? ($sourceMap[$line->source_type . ':' . $line->source_id] ?? null)
                    : null,
                'description'             => $line->description ?: '—',
                'line_type'               => $line->line_type,
                'debit'                   => $line->line_type === 'debit'  ? $this->formatCurrency($line->amount) : null,
                'credit'                  => $line->line_type === 'credit' ? $this->formatCurrency($line->amount) : null,
                'posted_by'               => $line->posted_by ?: 'System',
                'running_balance_raw'     => $rb,
                'running_balance'         => $rb !== null ? $this->formatCurrency(abs($rb)) : null,
                'running_balance_dr'      => $rbDr,
            ];
        });

        $paged = $paginator->toArray();

        return [
            'data'  => $paged['data'],
            'meta'  => [
                'current_page' => $paged['current_page'],
                'from'         => $paged['from'],
                'last_page'    => $paged['last_page'],
                'links'        => $paged['links'],
                'path'         => $paged['path'],
                'per_page'     => $paged['per_page'],
                'to'           => $paged['to'],
                'total'        => $paged['total'],
            ],
            'links' => [
                'first' => $paged['first_page_url'],
                'last'  => $paged['last_page_url'],
                'prev'  => $paged['prev_page_url'],
                'next'  => $paged['next_page_url'],
            ],
            'stats' => [
                'total_debits'  => $this->formatCurrency($totalDebits),
                'total_credits' => $this->formatCurrency($totalCredits),
                'is_balanced'   => abs($totalDebits - $totalCredits) < 0.01,
                'entry_count'   => (int) $statsRow->entry_count,
                'line_count'    => (int) $statsRow->line_count,
            ],
        ];
    }

    private function resolveSourceLabel(string $entryType): string
    {
        return match (true) {
            in_array($entryType, ['sales_revenue', 'receipt_collection', 'sales_return', 'sales_return_inventory']) => 'AR',
            in_array($entryType, ['purchase_receipt', 'purchase_receipt_cash', 'accounts_payable_payment'])         => 'AP',
            str_contains($entryType, 'payroll')   => 'Payroll',
            str_contains($entryType, 'inventory') => 'Inventory',
            default                               => 'Manual',
        };
    }

    private function entryTypesForSource(string $source): array
    {
        return match ($source) {
            'ar'        => ['sales_revenue', 'receipt_collection', 'sales_return', 'sales_return_inventory'],
            'ap'        => ['purchase_receipt', 'purchase_receipt_cash', 'accounts_payable_payment'],
            'payroll'   => ['payroll', 'payroll_disbursement'],
            'inventory' => ['inventory_out', 'inventory_adjustment'],
            'manual'    => ['manual', 'opening_entry', 'adjusting_entry'],
            default     => [],
        };
    }

    private function accountLinesForDrilldown(Request $request)
    {
        $empty = fn () => response()->json([
            'opening_balance'     => $this->formatCurrency(0),
            'opening_balance_raw' => 0,
            'lines'               => [],
            'totals'              => null,
        ]);

        if (!$this->hasCoreAccountingTables()) {
            return $empty();
        }

        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $accountId = $request->integer('account_id');

        $account = Account::find($accountId);
        if (!$account) {
            return $empty();
        }

        // Opening balance — sum of all activity strictly before the date range
        $openingBalanceRaw = 0.0;
        if ($dateFrom) {
            $ob = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('jel.account_id', $accountId)
                ->whereDate('je.entry_date', '<', $dateFrom)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN jel.line_type = 'debit'  THEN jel.amount ELSE 0 END), 0) as debit_total,
                    COALESCE(SUM(CASE WHEN jel.line_type = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total
                ")
                ->first();

            $openingBalanceRaw = $this->calculateAccountBalance(
                $account->type,
                (float) $ob->debit_total,
                (float) $ob->credit_total
            );
        }

        // Period lines — ASC so running balance flows chronologically
        $lineQuery = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('jel.account_id', $accountId)
            ->select([
                'jel.id',
                'je.journal_number',
                'je.entry_date',
                'je.entry_type',
                'je.source_type',
                'je.source_id',
                'jel.line_type',
                'jel.amount',
                'jel.description',
            ]);

        $this->applyDateRange($lineQuery, 'je.entry_date', $dateFrom, $dateTo);
        $lines = $lineQuery->orderBy('je.entry_date')->orderBy('jel.id')->get();

        $sourceMap      = $this->batchResolveSourceRefs(collect($lines));
        $isDebitNormal  = in_array($account->type, ['asset', 'expense']);
        $runningBalance = $openingBalanceRaw;
        $periodDebits   = 0.0;
        $periodCredits  = 0.0;

        $mappedLines = $lines->map(function ($line) use (
            &$runningBalance, &$periodDebits, &$periodCredits, $isDebitNormal, $sourceMap
        ) {
            $amount  = (float) $line->amount;
            $isDebit = $line->line_type === 'debit';

            $runningBalance += $isDebitNormal
                ? ($isDebit ? $amount : -$amount)
                : ($isDebit ? -$amount : $amount);

            if ($isDebit) $periodDebits += $amount;
            else          $periodCredits += $amount;

            $sourceKey = ($line->source_type && $line->source_id)
                ? $line->source_type . ':' . $line->source_id
                : null;

            return [
                'id'              => $line->id,
                'journal_number'  => $line->journal_number,
                'entry_date'      => $line->entry_date,
                'entry_type'      => Str::of($line->entry_type)->replace('_', ' ')->title()->value(),
                'line_type'       => $line->line_type,
                'amount'          => $this->formatCurrency($amount),
                'description'     => $line->description ?: '—',
                'running_balance' => $this->formatCurrency($runningBalance),
                'source_ref'      => $sourceKey ? ($sourceMap[$sourceKey] ?? null) : null,
            ];
        });

        $closingBalance = $runningBalance;
        $netChange      = round($closingBalance - $openingBalanceRaw, 2);

        return response()->json([
            'opening_balance'     => $this->formatCurrency($openingBalanceRaw),
            'opening_balance_raw' => $openingBalanceRaw,
            'lines'               => $mappedLines->values(),
            'totals'              => [
                'period_debits'   => $this->formatCurrency($periodDebits),
                'period_credits'  => $this->formatCurrency($periodCredits),
                'net_change'      => $this->formatCurrency(abs($netChange)),
                'net_change_raw'  => $netChange,
                'closing_balance' => $this->formatCurrency($closingBalance),
            ],
        ]);
    }

    public function trialBalance(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $balances    = $this->buildAccountBalances($dateFrom, $dateTo);
        $debitTotal  = $balances->sum('debit_total');
        $creditTotal = $balances->sum('credit_total');
        $difference  = abs($debitTotal - $creditTotal);
        $filters     = ['date_from' => $dateFrom, 'date_to' => $dateTo];
        $totals      = [
            'debits'      => $this->formatCurrency($debitTotal),
            'credits'     => $this->formatCurrency($creditTotal),
            'difference'  => $this->formatCurrency($difference),
            'is_balanced' => $difference < 0.01,
        ];

        if ($request->option === 'excel') {
            return Excel::download(
                new AccountingReportExport('trial_balance', ['rows' => $balances->values()->all(), 'totals' => $totals], $filters),
                'trial-balance-' . now()->format('Ymd') . '.xlsx'
            );
        }

        if ($request->option === 'pdf') {
            return \PDF::loadView('prints.accounting-trial-balance', [
                'rows'    => $balances->values()->all(),
                'totals'  => $totals,
                'filters' => $filters,
            ])->setPaper('A4', 'portrait')->download('trial-balance-' . now()->format('Ymd') . '.pdf');
        }

        return inertia('Modules/Accounting/TrialBalance', [
            'stats'        => $this->buildStats($dateFrom, $dateTo),
            'dataReady'    => $this->hasCoreAccountingTables(),
            'summaryCards' => [
                $this->makeMetricCard('Accounts', $balances->count(), 'Accounts included in the trial balance.', 'ri-scales-3-line'),
                $this->makeMetricCard('Total Debits', $this->formatCurrency($debitTotal), 'Aggregate debit postings.', 'ri-arrow-left-down-line'),
                $this->makeMetricCard('Total Credits', $this->formatCurrency($creditTotal), 'Aggregate credit postings.', 'ri-arrow-right-up-line'),
                $this->makeMetricCard('Difference', $this->formatCurrency($difference), 'Should be zero when balanced.', 'ri-focus-3-line'),
            ],
            'rows'    => $balances->values(),
            'totals'  => $totals,
            'filters' => $filters,
        ]);
    }

    public function profitLoss(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $balances    = $this->buildAccountBalances($dateFrom, $dateTo);
        $pl          = $this->buildProfitLossTotals($balances);
        $grossProfit = round($pl['revenue_raw'] - $pl['cost_of_sales_raw'], 2);
        $filters     = ['date_from' => $dateFrom, 'date_to' => $dateTo];

        $revenueAccounts          = $balances->where('type', 'revenue')->values();
        $costOfSalesAccounts      = $balances->where('type', 'expense')->where('subtype', 'cost_of_sales')->values();
        $operatingExpenseAccounts = $balances->where('type', 'expense')->where('subtype', '!=', 'cost_of_sales')->values();
        $totals = [
            'revenue'             => $this->formatCurrency($pl['revenue_raw']),
            'cost_of_sales'       => $this->formatCurrency($pl['cost_of_sales_raw']),
            'gross_profit'        => $this->formatCurrency($grossProfit),
            'operating_expenses'  => $this->formatCurrency($pl['operating_expenses_raw']),
            'net_income'          => $this->formatCurrency($pl['net_income_raw']),
            'net_income_raw'      => $pl['net_income_raw'],
            'gross_profit_raw'    => $grossProfit,
        ];

        if ($request->option === 'excel') {
            return Excel::download(
                new AccountingReportExport('profit_loss', [
                    'revenueAccounts'          => $revenueAccounts->all(),
                    'costOfSalesAccounts'      => $costOfSalesAccounts->all(),
                    'operatingExpenseAccounts' => $operatingExpenseAccounts->all(),
                    'totals'                   => $totals,
                ], $filters),
                'profit-loss-' . now()->format('Ymd') . '.xlsx'
            );
        }

        if ($request->option === 'pdf') {
            return \PDF::loadView('prints.accounting-profit-loss', [
                'revenueAccounts'          => $revenueAccounts->all(),
                'costOfSalesAccounts'      => $costOfSalesAccounts->all(),
                'operatingExpenseAccounts' => $operatingExpenseAccounts->all(),
                'totals'                   => $totals,
                'filters'                  => $filters,
            ])->setPaper('A4', 'portrait')->download('profit-loss-' . now()->format('Ymd') . '.pdf');
        }

        return inertia('Modules/Accounting/ProfitLoss', [
            'stats'        => $this->buildStats($dateFrom, $dateTo),
            'dataReady'    => $this->hasCoreAccountingTables(),
            'summaryCards' => [
                $this->makeMetricCard('Revenue', $this->formatCurrency($pl['revenue_raw']), 'Net revenue from posted entries.', 'ri-funds-line'),
                $this->makeMetricCard('Cost Of Sales', $this->formatCurrency($pl['cost_of_sales_raw']), 'Inventory-out cost postings.', 'ri-shopping-basket-line'),
                $this->makeMetricCard('Operating Expenses', $this->formatCurrency($pl['operating_expenses_raw']), 'Non-COGS operating expenses.', 'ri-wallet-line'),
                $this->makeMetricCard('Net Income', $this->formatCurrency($pl['net_income_raw']), 'Revenue less total expenses.', 'ri-line-chart-line'),
            ],
            'revenueAccounts'          => $revenueAccounts,
            'costOfSalesAccounts'      => $costOfSalesAccounts,
            'operatingExpenseAccounts' => $operatingExpenseAccounts,
            'totals'                   => $totals,
            'filters'                  => $filters,
        ]);
    }

    public function balanceSheet(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $balances = $this->buildAccountBalances($dateFrom, $dateTo);
        $pl       = $this->buildProfitLossTotals($balances);
        $filters  = ['date_from' => $dateFrom, 'date_to' => $dateTo];

        $assetAccounts     = $balances->where('type', 'asset')->values();
        $liabilityAccounts = $balances->where('type', 'liability')->values();
        $equityAccounts    = $balances->where('type', 'equity')->values();

        $totalAssets               = round($assetAccounts->sum('balance'), 2);
        $totalLiabilities          = round($liabilityAccounts->sum('balance'), 2);
        $totalEquity               = round($equityAccounts->sum('balance'), 2);
        $currentEarnings           = round($pl['net_income_raw'], 2);
        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity + $currentEarnings, 2);
        $isBalanced                = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01;

        $totals = [
            'assets'                   => $this->formatCurrency($totalAssets),
            'liabilities'              => $this->formatCurrency($totalLiabilities),
            'equity'                   => $this->formatCurrency($totalEquity),
            'current_period_earnings'  => $this->formatCurrency($currentEarnings),
            'total_equity'             => $this->formatCurrency($totalEquity + $currentEarnings),
            'liabilities_and_equity'   => $this->formatCurrency($totalLiabilitiesAndEquity),
            'is_balanced'              => $isBalanced,
            'assets_raw'               => $totalAssets,
            'liabilities_and_equity_raw' => $totalLiabilitiesAndEquity,
        ];

        if ($request->option === 'excel') {
            return Excel::download(
                new AccountingReportExport('balance_sheet', [
                    'assetAccounts'     => $assetAccounts->all(),
                    'liabilityAccounts' => $liabilityAccounts->all(),
                    'equityAccounts'    => $equityAccounts->all(),
                    'totals'            => $totals,
                ], $filters),
                'balance-sheet-' . now()->format('Ymd') . '.xlsx'
            );
        }

        if ($request->option === 'pdf') {
            return \PDF::loadView('prints.accounting-balance-sheet', [
                'assetAccounts'     => $assetAccounts->all(),
                'liabilityAccounts' => $liabilityAccounts->all(),
                'equityAccounts'    => $equityAccounts->all(),
                'totals'            => $totals,
                'filters'           => $filters,
            ])->setPaper('A4', 'portrait')->download('balance-sheet-' . now()->format('Ymd') . '.pdf');
        }

        return inertia('Modules/Accounting/BalanceSheet', [
            'stats'        => $this->buildStats($dateFrom, $dateTo),
            'dataReady'    => $this->hasCoreAccountingTables(),
            'summaryCards' => [
                $this->makeMetricCard('Total Assets', $this->formatCurrency($totalAssets), 'Sum of all asset account balances.', 'ri-coins-line'),
                $this->makeMetricCard('Total Liabilities', $this->formatCurrency($totalLiabilities), 'Sum of all liability account balances.', 'ri-secure-payment-line'),
                $this->makeMetricCard('Total Equity', $this->formatCurrency($totalEquity + $currentEarnings), 'Book equity plus current period earnings.', 'ri-building-line'),
                $this->makeMetricCard('Balance Check', $isBalanced ? 'Balanced ✓' : 'Unbalanced !', 'Assets should equal Liabilities + Equity.', 'ri-scales-3-line'),
            ],
            'assetAccounts'     => $assetAccounts,
            'liabilityAccounts' => $liabilityAccounts,
            'equityAccounts'    => $equityAccounts,
            'totals'            => $totals,
            'filters'           => $filters,
        ]);
    }

    public function cashFlowStatement(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $data    = $this->buildCashFlowData($dateFrom, $dateTo);
        $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo];

        if ($request->option === 'pdf') {
            return \PDF::loadView('prints.accounting-cash-flow', [
                'sections' => $data['sections'],
                'totals'   => $data['totals'],
                'filters'  => $filters,
            ])->setPaper('A4', 'portrait')->download('cash-flow-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->option === 'excel') {
            return Excel::download(
                new AccountingReportExport('cash_flow', $data, $filters),
                'cash-flow-' . now()->format('Ymd') . '.xlsx'
            );
        }

        $netChange = $data['totals']['net_change'];

        return inertia('Modules/Accounting/CashFlow', [
            'stats'        => $this->buildStats($dateFrom, $dateTo),
            'dataReady'    => $this->hasCoreAccountingTables(),
            'summaryCards' => [
                $this->makeMetricCard('Net Operating Cash',  $data['totals']['net_operating_formatted'],  'Cash from core operations.',           'ri-store-2-line'),
                $this->makeMetricCard('Net Investing Cash',  $data['totals']['net_investing_formatted'],  'Cash from long-term asset activity.',   'ri-building-line'),
                $this->makeMetricCard('Net Financing Cash',  $data['totals']['net_financing_formatted'],  'Cash from equity and debt financing.',  'ri-bank-line'),
                $this->makeMetricCard('Net Change in Cash',  $data['totals']['net_change_formatted'],     $netChange >= 0 ? 'Net cash increase.' : 'Net cash decrease.', $netChange >= 0 ? 'ri-arrow-up-circle-line' : 'ri-arrow-down-circle-line'),
            ],
            'sections' => $data['sections'],
            'totals'   => $data['totals'],
            'filters'  => $filters,
        ]);
    }

    private function buildCashFlowData(?string $dateFrom, ?string $dateTo): array
    {
        if (!$this->hasCoreAccountingTables()) {
            return $this->emptyCashFlow();
        }

        // ── OPERATING (indirect method) ───────────────────────────────────────
        $opRows = $this->buildIndirectOperatingRows($dateFrom, $dateTo);
        $netOp  = collect($opRows)->sum(fn ($r) => $r['direction'] === 'inflow' ? $r['amount_raw'] : -$r['amount_raw']);

        // ── INVESTING ────────────────────────────────────────────────────────────
        $manual  = $this->buildManualCashRows($dateFrom, $dateTo);
        $invRows = $manual['investing'];
        $netInv  = collect($invRows)->sum(fn ($r) => $r['direction'] === 'inflow' ? $r['amount_raw'] : -$r['amount_raw']);

        // ── FINANCING ────────────────────────────────────────────────────────────
        $finRows = $manual['financing'];
        $netFin  = collect($finRows)->sum(fn ($r) => $r['direction'] === 'inflow' ? $r['amount_raw'] : -$r['amount_raw']);

        $netChange      = round($netOp + $netInv + $netFin, 2);
        $openingBalance = $this->buildOpeningCashBalance($dateFrom);
        $closingBalance = round($openingBalance + $netChange, 2);

        return [
            'sections' => [
                [
                    'id'          => 'operating',
                    'title'       => 'Operating Activities',
                    'description' => 'Cash generated from or used in day-to-day business operations.',
                    'icon'        => 'ri-store-2-line',
                    'rows'        => $opRows,
                    'net_raw'     => $netOp,
                    'net'         => $this->formatCurrency($netOp),
                ],
                [
                    'id'          => 'investing',
                    'title'       => 'Investing Activities',
                    'description' => 'Cash used for or received from long-term asset purchases and disposals.',
                    'icon'        => 'ri-building-line',
                    'rows'        => $invRows,
                    'net_raw'     => $netInv,
                    'net'         => $this->formatCurrency($netInv),
                ],
                [
                    'id'          => 'financing',
                    'title'       => 'Financing Activities',
                    'description' => 'Cash flows from equity contributions, loans taken, or loan repayments.',
                    'icon'        => 'ri-bank-line',
                    'rows'        => $finRows,
                    'net_raw'     => $netFin,
                    'net'         => $this->formatCurrency($netFin),
                ],
            ],
            'totals' => [
                'net_operating'             => $netOp,
                'net_operating_formatted'   => $this->formatCurrency($netOp),
                'net_investing'             => $netInv,
                'net_investing_formatted'   => $this->formatCurrency($netInv),
                'net_financing'             => $netFin,
                'net_financing_formatted'   => $this->formatCurrency($netFin),
                'net_change'                => $netChange,
                'net_change_formatted'      => $this->formatCurrency($netChange),
                'opening_balance'           => $openingBalance,
                'opening_balance_formatted' => $this->formatCurrency($openingBalance),
                'closing_balance'           => $closingBalance,
                'closing_balance_formatted' => $this->formatCurrency($closingBalance),
            ],
        ];
    }

    private function buildIndirectOperatingRows(?string $dateFrom, ?string $dateTo): array
    {
        // Cumulative balances up to the day before the period start (opening snapshot)
        $openingDate = $dateFrom ? Carbon::parse($dateFrom)->subDay()->format('Y-m-d') : null;
        $openingBals = $openingDate ? $this->buildAccountBalances(null, $openingDate) : collect();
        $closingBals = $this->buildAccountBalances(null, $dateTo);

        // Net income for the period = closing P&L minus opening P&L
        $plClosing = $this->buildProfitLossTotals($closingBals);
        $plOpening = $this->buildProfitLossTotals($openingBals);
        $netIncome = round($plClosing['net_income_raw'] - $plOpening['net_income_raw'], 2);

        $makeRow = fn (string $label, float $amount, string $dir, string $note = '') => [
            'label'      => $label,
            'note'       => $note,
            'amount_raw' => abs($amount),
            'amount'     => $this->formatCurrency(abs($amount)),
            'entries'    => null,
            'direction'  => $dir,
            'details'    => [],
        ];

        // Helper: sum a filtered balance collection then diff opening vs closing
        $delta = fn (callable $filter) => round(
            (float) $closingBals->filter($filter)->sum('balance')
            - (float) $openingBals->filter($filter)->sum('balance'),
            2
        );

        $rows = [];

        // ── Net Income (always shown) ─────────────────────────────────────────
        $rows[] = $makeRow(
            $netIncome >= 0 ? 'Net Income' : 'Net Loss',
            abs($netIncome),
            $netIncome >= 0 ? 'inflow' : 'outflow',
            'Profit or loss for the period'
        );

        // ── Depreciation & Amortization add-back ─────────────────────────────
        $deprDelta = $delta(fn ($a) =>
            in_array($a['subtype'], ['depreciation', 'amortization'])
            || str_contains(strtolower($a['name']), 'depreciation')
            || str_contains(strtolower($a['name']), 'amortization')
        );
        if (abs($deprDelta) >= 0.01) {
            $rows[] = $makeRow('Add: Depreciation & Amortization', abs($deprDelta), 'inflow', 'Non-cash expense added back');
        }

        // ── Working capital — asset accounts (increase = outflow) ─────────────
        $assetItems = [
            [
                '(Increase) / Decrease in Accounts Receivable',
                $delta(fn ($a) => $a['slug'] === 'accounts_receivable' || $a['subtype'] === 'accounts_receivable'),
                'Changes in accounts receivable balance',
            ],
            [
                '(Increase) / Decrease in Inventory',
                $delta(fn ($a) => in_array($a['subtype'], ['inventory', 'merchandise_inventory'])),
                'Changes in inventory balance',
            ],
            [
                '(Increase) / Decrease in Prepaid Expenses',
                $delta(fn ($a) => $a['subtype'] === 'prepaid'),
                'Changes in prepaid expenses balance',
            ],
        ];

        foreach ($assetItems as [$label, $d, $note]) {
            if (abs($d) < 0.01) continue;
            $rows[] = $makeRow($label, abs($d), $d > 0 ? 'outflow' : 'inflow', $note);
        }

        // ── Working capital — liability accounts (increase = inflow) ──────────
        $liabItems = [
            [
                'Increase / (Decrease) in Accounts Payable',
                $delta(fn ($a) => $a['slug'] === 'accounts_payable' || $a['subtype'] === 'accounts_payable'),
                'Changes in accounts payable balance',
            ],
            [
                'Increase / (Decrease) in Accrued Liabilities',
                $delta(fn ($a) => in_array($a['subtype'], ['accrued_liabilities', 'accrued'])),
                'Changes in accrued liabilities balance',
            ],
        ];

        foreach ($liabItems as [$label, $d, $note]) {
            if (abs($d) < 0.01) continue;
            $rows[] = $makeRow($label, abs($d), $d > 0 ? 'inflow' : 'outflow', $note);
        }

        return $rows;
    }

    private function resolveCashAccountIds(): \Illuminate\Support\Collection
    {
        return DB::table('accounts')
            ->where('type', 'asset')
            ->where(function ($q) {
                $q->whereIn('subtype', ['cash', 'bank', 'petty_cash', 'cash_equivalents'])
                  ->orWhere('name', 'like', '%Cash%')
                  ->orWhere('name', 'like', '%Bank%')
                  ->orWhere('slug', 'like', 'cash%')
                  ->orWhere('slug', 'like', '%bank%');
            })
            ->pluck('id');
    }

    private function buildOpeningCashBalance(?string $dateFrom): float
    {
        if (!$dateFrom) return 0.0;

        $cashIds = $this->resolveCashAccountIds();
        if ($cashIds->isEmpty()) return 0.0;

        $q = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('jel.account_id', $cashIds)
            ->where('je.entry_date', '<', $dateFrom)
            ->selectRaw("SUM(CASE WHEN jel.line_type = 'debit' THEN jel.amount ELSE -jel.amount END) as balance");

        if ($this->hasJournalReversalColumns()) {
            $q->whereNull('je.reversed_at')->whereNull('je.reversal_of_id');
        }

        return round((float) ($q->value('balance') ?? 0), 2);
    }

    private function buildAllRowDetails(?string $dateFrom, ?string $dateTo): array
    {
        $types = ['receipt_collection', 'accounts_payable_payment', 'purchase_receipt_cash', 'payroll_disbursement'];

        $q = DB::table('journal_entries as je')
            ->join('journal_entry_lines as jel', 'jel.journal_entry_id', '=', 'je.id')
            ->whereIn('je.entry_type', $types)
            ->select(
                'je.entry_type',
                'je.entry_date',
                'je.journal_number',
                'je.memo',
                'jel.line_type',
                'jel.amount',
                'jel.description'
            )
            ->orderByDesc('je.entry_date')
            ->orderByDesc('je.id');

        if ($this->hasJournalReversalColumns()) {
            $q->whereNull('je.reversed_at')->whereNull('je.reversal_of_id');
        }

        $this->applyDateRange($q, 'je.entry_date', $dateFrom, $dateTo);

        $grouped = $q->get()->groupBy('entry_type');

        $result = [];
        foreach ($types as $type) {
            $result[$type] = ($grouped->get($type) ?? collect())
                ->take(60)
                ->map(fn ($e) => [
                    'date'           => $e->entry_date,
                    'journal_number' => $e->journal_number ?? '—',
                    'description'    => $e->description ?: ($e->memo ?: '—'),
                    'debit'          => $e->line_type === 'debit'  ? $this->formatCurrency((float) $e->amount) : null,
                    'credit'         => $e->line_type === 'credit' ? $this->formatCurrency((float) $e->amount) : null,
                    'amount_raw'     => (float) $e->amount,
                ])
                ->values()
                ->toArray();
        }

        return $result;
    }

    private function buildManualCashRows(?string $dateFrom, ?string $dateTo): array
    {
        $result  = ['operating' => [], 'investing' => [], 'financing' => []];
        $cashIds = $this->resolveCashAccountIds();

        if ($cashIds->isEmpty()) {
            return $result;
        }

        $q = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('jel.account_id', $cashIds)
            ->whereIn('je.entry_type', ['manual', 'adjusting_entry'])
            ->select([
                'je.id',
                'je.entry_date',
                'je.memo',
                'jel.line_type',
                'jel.amount',
                'jel.description',
            ]);

        if ($this->hasJournalReversalColumns()) {
            $q->whereNull('je.reversed_at')
              ->whereNull('je.reversal_of_id');
        }

        $this->applyDateRange($q, 'je.entry_date', $dateFrom, $dateTo);

        $lines = $q->get()->groupBy('id');

        foreach ($lines as $entryLines) {
            $first   = $entryLines->first();
            $label   = Str::limit($first->memo ?: $first->description ?: 'Manual cash movement', 60);
            $debit   = (float) $entryLines->where('line_type', 'debit')->sum('amount');
            $credit  = (float) $entryLines->where('line_type', 'credit')->sum('amount');
            $netCash = $debit - $credit;

            if (abs($netCash) < 0.01) {
                continue;
            }

            $result['operating'][] = [
                'label'      => $label,
                'note'       => 'Manual entry',
                'amount_raw' => abs($netCash),
                'amount'     => $this->formatCurrency(abs($netCash)),
                'entries'    => 1,
                'direction'  => $netCash > 0 ? 'inflow' : 'outflow',
                'details'    => [[
                    'date'           => $first->entry_date,
                    'journal_number' => '—',
                    'description'    => $label,
                    'debit'          => $netCash > 0 ? $this->formatCurrency(abs($netCash)) : null,
                    'credit'         => $netCash < 0 ? $this->formatCurrency(abs($netCash)) : null,
                    'amount_raw'     => abs($netCash),
                ]],
            ];
        }

        return $result;
    }

    private function emptyCashFlow(): array
    {
        $zero = $this->formatCurrency(0);
        $sections = [
            ['id' => 'operating', 'title' => 'Operating Activities',  'description' => 'Cash from day-to-day business operations.',    'icon' => 'ri-store-2-line',  'rows' => [], 'net_raw' => 0, 'net' => $zero],
            ['id' => 'investing', 'title' => 'Investing Activities',  'description' => 'Cash used for or from long-term assets.',       'icon' => 'ri-building-line', 'rows' => [], 'net_raw' => 0, 'net' => $zero],
            ['id' => 'financing', 'title' => 'Financing Activities',  'description' => 'Cash from equity and debt financing.',          'icon' => 'ri-bank-line',     'rows' => [], 'net_raw' => 0, 'net' => $zero],
        ];

        return [
            'sections' => $sections,
            'totals'   => [
                'net_operating' => 0, 'net_operating_formatted' => $zero,
                'net_investing' => 0, 'net_investing_formatted' => $zero,
                'net_financing' => 0, 'net_financing_formatted' => $zero,
                'net_change'    => 0, 'net_change_formatted'    => $zero,
                'opening_balance' => 0, 'opening_balance_formatted' => $zero,
                'closing_balance' => 0, 'closing_balance_formatted' => $zero,
            ],
        ];
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
                'rows' => $balances->values(),
                'totals' => [
                    'debits' => $this->formatCurrency($balances->sum('debit_total')),
                    'credits' => $this->formatCurrency($balances->sum('credit_total')),
                    'difference' => $this->formatCurrency(abs($balances->sum('debit_total') - $balances->sum('credit_total'))),
                    'is_balanced' => abs($balances->sum('debit_total') - $balances->sum('credit_total')) < 0.01,
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

        $query = JournalEntry::query()
            ->select([
                'id',
                'journal_number',
                'entry_date',
                'entry_type',
                'status',
                'memo',
            ]);

        $this->applyDateRange($query, 'entry_date', $dateFrom, $dateTo);

        return $query
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'journal_number' => $entry->journal_number,
                'entry_date' => optional($entry->entry_date)->format('Y-m-d'),
                'entry_type' => Str::of($entry->entry_type)->replace('_', ' ')->title()->value(),
                'status' => Str::of($entry->status)->replace('_', ' ')->title()->value(),
                'memo' => $entry->memo ?: '-',
            ])
            ->values();
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

    private function buildDashboardRecentEntries(?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (!$this->hasCoreAccountingTables()) {
            return [];
        }

        $query = DB::table('journal_entries as je')
            ->leftJoin('journal_entry_lines as jel', 'jel.journal_entry_id', '=', 'je.id')
            ->select([
                'je.id',
                'je.journal_number',
                'je.entry_date',
                'je.entry_type',
                'je.status',
                DB::raw("COALESCE(SUM(CASE WHEN jel.line_type = 'debit'  THEN jel.amount ELSE 0 END), 0) as debit_total"),
                DB::raw("COALESCE(SUM(CASE WHEN jel.line_type = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total"),
            ])
            ->groupBy('je.id', 'je.journal_number', 'je.entry_date', 'je.entry_type', 'je.status');

        $this->applyDateRange($query, 'je.entry_date', $dateFrom, $dateTo);

        return $query
            ->orderByDesc('je.entry_date')
            ->orderByDesc('je.id')
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'id'             => $e->id,
                'journal_number' => $e->journal_number,
                'entry_date'     => $e->entry_date,
                'entry_type'     => Str::of($e->entry_type)->replace('_', ' ')->title()->value(),
                'source_label'   => $this->resolveSourceLabel($e->entry_type),
                'status'         => $e->status,
                'debit_total'    => $this->formatCurrency((float) $e->debit_total),
                'credit_total'   => $this->formatCurrency((float) $e->credit_total),
            ])
            ->values()
            ->all();
    }

    private function journalEntryLists(Request $request)
    {
        if (!Schema::hasTable('journal_entries')) {
            return response()->json([
                'data' => [],
                'links' => [],
                'meta' => [
                    'current_page' => 1,
                    'from' => null,
                    'last_page' => 1,
                    'links' => [],
                    'path' => url('/accounting/journal-entries'),
                    'per_page' => 10,
                    'to' => null,
                    'total' => 0,
                ],
            ]);
        }

        $hasReversalColumns = $this->hasJournalReversalColumns();
        $withRelations = ['lines.account'];

        if ($hasReversalColumns) {
            $withRelations[] = 'reversalOf';
            $withRelations[] = 'reversals';
        }

        $paginator = JournalEntry::with($withRelations)
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
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('entry_date', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('entry_date', '<=', $request->date_to);
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($request->count ?? 15);

        // Build source ref map in at most one query per distinct source type on this page.
        $refMap = $this->batchResolveSourceRefs($paginator->getCollection());

        $paginator->through(function (JournalEntry $entry) use ($hasReversalColumns, $refMap) {
            return [
                'id' => $entry->id,
                'journal_number' => $entry->journal_number,
                'entry_date' => optional($entry->entry_date)->format('Y-m-d'),
                'entry_type' => Str::of($entry->entry_type)->replace('_', ' ')->title()->value(),
                'status' => $entry->status,
                'memo' => $entry->memo,
                'source_type' => class_basename($entry->source_type ?? ''),
                'source_id' => $entry->source_id,
                'source_ref' => $refMap[$entry->source_type . ':' . $entry->source_id] ?? null,
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
                'lines' => $entry->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'account_name' => optional($line->account)->name,
                    'account_code' => optional($line->account)->code,
                    'line_type' => $line->line_type,
                    'amount' => number_format((float) $line->amount, 2, '.', ','),
                    'description' => $line->description,
                ])->values(),
            ];
        });

        $paged = $paginator->toArray();

        return response()->json([
            'data'  => $paged['data'],
            'meta'  => [
                'current_page' => $paged['current_page'],
                'from'         => $paged['from'],
                'last_page'    => $paged['last_page'],
                'links'        => $paged['links'],
                'path'         => $paged['path'],
                'per_page'     => $paged['per_page'],
                'to'           => $paged['to'],
                'total'        => $paged['total'],
            ],
            'links' => [
                'first' => $paged['first_page_url'],
                'last'  => $paged['last_page_url'],
                'prev'  => $paged['prev_page_url'],
                'next'  => $paged['next_page_url'],
            ],
        ]);
    }

    private function batchResolveSourceRefs(Collection $entries): array
    {
        $fieldMap = [
            'SalesOrder'  => ['table' => 'sales_orders',    'column' => 'so_number'],
            'ArInvoice'   => ['table' => 'ar_invoices',     'column' => 'invoice_number'],
            'Receipt'     => ['table' => 'receipts',        'column' => 'receipt_number'],
            'ReceivedStock' => ['table' => 'received_stocks', 'column' => 'received_no'],
        ];

        $refMap = [];

        $entries
            ->filter(fn ($e) => $e->source_type && $e->source_id)
            ->groupBy(fn ($e) => class_basename($e->source_type))
            ->each(function (Collection $group, string $basename) use ($fieldMap, &$refMap) {
                if (!isset($fieldMap[$basename])) {
                    return;
                }

                $config = $fieldMap[$basename];
                $ids = $group->pluck('source_id')->unique()->all();

                $numbers = DB::table($config['table'])
                    ->whereIn('id', $ids)
                    ->pluck($config['column'], 'id');

                foreach ($group as $entry) {
                    $refMap[$entry->source_type . ':' . $entry->source_id] = $numbers->get($entry->source_id);
                }
            });

        return $refMap;
    }

    private function resolveSourceRef(?string $sourceType, ?int $sourceId): ?string
    {
        if (!$sourceType || !$sourceId) {
            return null;
        }

        $numberField = match (class_basename($sourceType)) {
            'SalesOrder'   => ['table' => 'sales_orders',    'column' => 'so_number'],
            'ArInvoice'    => ['table' => 'ar_invoices',     'column' => 'invoice_number'],
            'Receipt'      => ['table' => 'receipts',        'column' => 'receipt_number'],
            'ReceivedStock'=> ['table' => 'received_stocks', 'column' => 'received_no'],
            default        => null,
        };

        if (!$numberField) {
            return null;
        }

        $row = DB::table($numberField['table'])
            ->where('id', $sourceId)
            ->value($numberField['column']);

        return $row ?? null;
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

    /**
     * For each jel.id in $lineIds, compute the running balance of its account
     * up to and including that line (chronological order: entry_date, je.id, jel.id).
     * Returns [ jel_id => float, ... ]
     */
    private function computeRunningBalances(array $lineIds): array
    {
        if (empty($lineIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($lineIds), '?'));

        $rows = DB::select("
            SELECT jel.id,
                   COALESCE((
                       SELECT SUM(CASE WHEN jel2.line_type = 'debit' THEN jel2.amount ELSE -jel2.amount END)
                       FROM journal_entry_lines jel2
                       INNER JOIN journal_entries je2 ON je2.id = jel2.journal_entry_id
                       WHERE jel2.account_id = jel.account_id
                         AND (
                             je2.entry_date < je.entry_date
                             OR (je2.entry_date = je.entry_date AND je2.id < je.id)
                             OR (je2.entry_date = je.entry_date AND je2.id = je.id AND jel2.id <= jel.id)
                         )
                   ), 0) AS running_balance
            FROM journal_entry_lines jel
            INNER JOIN journal_entries je ON je.id = jel.journal_entry_id
            WHERE jel.id IN ($placeholders)
        ", $lineIds);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->id] = (float) $row->running_balance;
        }
        return $map;
    }

    private function formatCurrency(float|int|null $value): string
    {
        return 'P ' . number_format((float) $value, 2, '.', ',');
    }

    private function buildTotalCash(): array
    {
        if (!$this->hasCoreAccountingTables()) {
            return ['formatted' => $this->formatCurrency(0), 'raw' => 0.0];
        }

        $cashAccountIds = DB::table('accounts')
            ->where('type', 'asset')
            ->where(function ($q) {
                $q->whereIn('subtype', ['cash', 'bank', 'petty_cash', 'cash_equivalents'])
                  ->orWhere('name', 'like', '%Cash%')
                  ->orWhere('name', 'like', '%Bank%');
            })
            ->pluck('id');

        if ($cashAccountIds->isEmpty()) {
            return ['formatted' => $this->formatCurrency(0), 'raw' => 0.0];
        }

        $totals = DB::table('journal_entry_lines')
            ->whereIn('account_id', $cashAccountIds)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN line_type = 'debit'  THEN amount ELSE 0 END), 0) as debit_total,
                COALESCE(SUM(CASE WHEN line_type = 'credit' THEN amount ELSE 0 END), 0) as credit_total
            ")
            ->first();

        $total = round((float) $totals->debit_total - (float) $totals->credit_total, 2);

        return ['formatted' => $this->formatCurrency($total), 'raw' => $total];
    }

    private function buildOutstandingArTotal(): array
    {
        if (!Schema::hasTable('ar_invoices')) {
            return ['formatted' => $this->formatCurrency(0), 'raw' => 0.0];
        }

        $total = (float) DB::table('ar_invoices')->where('balance_due', '>', 0)->sum('balance_due');

        return ['formatted' => $this->formatCurrency($total), 'raw' => $total];
    }

    private function buildArAgingSummary(): array
    {
        $zero  = $this->formatCurrency(0);
        $empty = [
            'current' => $zero, 'days_1_30' => $zero, 'days_31_60' => $zero,
            'days_61_90' => $zero, 'days_90_plus' => $zero, 'total' => $zero,
            'current_raw' => 0.0, 'days_1_30_raw' => 0.0, 'days_31_60_raw' => 0.0,
            'days_61_90_raw' => 0.0, 'days_90_plus_raw' => 0.0, 'total_raw' => 0.0,
        ];

        if (!Schema::hasTable('ar_invoices')) {
            return $empty;
        }

        $asOf = Carbon::today()->format('Y-m-d');
        $eff  = "COALESCE(ai.due_date, so.due_date, ai.invoice_date)";

        $row = DB::table('ar_invoices as ai')
            ->join('sales_orders as so', 'so.id', '=', 'ai.sales_order_id')
            ->where('ai.balance_due', '>', 0)
            ->selectRaw("
                COALESCE(SUM(ai.balance_due), 0) as total,
                COALESCE(SUM(CASE WHEN {$eff} >= '{$asOf}' THEN ai.balance_due ELSE 0 END), 0) as current_bucket,
                COALESCE(SUM(CASE WHEN {$eff} < '{$asOf}' AND DATEDIFF('{$asOf}', {$eff}) BETWEEN 1 AND 30 THEN ai.balance_due ELSE 0 END), 0) as days_1_30,
                COALESCE(SUM(CASE WHEN {$eff} < '{$asOf}' AND DATEDIFF('{$asOf}', {$eff}) BETWEEN 31 AND 60 THEN ai.balance_due ELSE 0 END), 0) as days_31_60,
                COALESCE(SUM(CASE WHEN {$eff} < '{$asOf}' AND DATEDIFF('{$asOf}', {$eff}) BETWEEN 61 AND 90 THEN ai.balance_due ELSE 0 END), 0) as days_61_90,
                COALESCE(SUM(CASE WHEN {$eff} < '{$asOf}' AND DATEDIFF('{$asOf}', {$eff}) > 90 THEN ai.balance_due ELSE 0 END), 0) as days_90_plus
            ")
            ->first();

        return [
            'current'          => $this->formatCurrency($row->current_bucket),
            'days_1_30'        => $this->formatCurrency($row->days_1_30),
            'days_31_60'       => $this->formatCurrency($row->days_31_60),
            'days_61_90'       => $this->formatCurrency($row->days_61_90),
            'days_90_plus'     => $this->formatCurrency($row->days_90_plus),
            'total'            => $this->formatCurrency($row->total),
            'current_raw'      => (float) $row->current_bucket,
            'days_1_30_raw'    => (float) $row->days_1_30,
            'days_31_60_raw'   => (float) $row->days_31_60,
            'days_61_90_raw'   => (float) $row->days_61_90,
            'days_90_plus_raw' => (float) $row->days_90_plus,
            'total_raw'        => (float) $row->total,
        ];
    }

    private function buildApAgingSummary(): array
    {
        $zero  = $this->formatCurrency(0);
        $empty = [
            'current' => $zero, 'days_1_30' => $zero, 'days_31_60' => $zero,
            'days_61_90' => $zero, 'days_90_plus' => $zero, 'total' => $zero,
            'current_raw' => 0.0, 'days_1_30_raw' => 0.0, 'days_31_60_raw' => 0.0,
            'days_61_90_raw' => 0.0, 'days_90_plus_raw' => 0.0, 'total_raw' => 0.0,
        ];

        if (!Schema::hasTable('received_stocks') || !Schema::hasTable('received_items')) {
            return $empty;
        }

        $asOf = Carbon::today()->format('Y-m-d');

        $receivings = DB::table('received_stocks as rs')
            ->leftJoin('received_items as ri', 'ri.received_id', '=', 'rs.id')
            ->leftJoin('received_stock_payments as rsp', 'rsp.received_stock_id', '=', 'rs.id')
            ->select([
                'rs.id',
                'rs.received_date',
                'rs.amount_paid',
                DB::raw('COALESCE(SUM(DISTINCT ri.total_cost * 1), 0) as total_cost'),
                DB::raw('COALESCE(SUM(DISTINCT rsp.amount_paid * 1), 0) as payments_total'),
            ])
            ->groupBy('rs.id', 'rs.received_date', 'rs.amount_paid')
            ->get()
            ->map(function ($row) use ($asOf) {
                $balance = round(max((float) $row->total_cost - (float) max($row->amount_paid, $row->payments_total), 0), 2);
                $daysOut = (int) max(0, Carbon::parse($row->received_date)->diffInDays(Carbon::parse($asOf), false));
                return ['balance' => $balance, 'days_out' => $daysOut];
            })
            ->filter(fn ($r) => $r['balance'] > 0.009);

        $total   = $receivings->sum('balance');
        $current = $receivings->where('days_out', 0)->sum('balance');
        $d1_30   = $receivings->filter(fn ($r) => $r['days_out'] >= 1 && $r['days_out'] <= 30)->sum('balance');
        $d31_60  = $receivings->filter(fn ($r) => $r['days_out'] >= 31 && $r['days_out'] <= 60)->sum('balance');
        $d61_90  = $receivings->filter(fn ($r) => $r['days_out'] >= 61 && $r['days_out'] <= 90)->sum('balance');
        $d90plus = $receivings->filter(fn ($r) => $r['days_out'] > 90)->sum('balance');

        return [
            'current'          => $this->formatCurrency($current),
            'days_1_30'        => $this->formatCurrency($d1_30),
            'days_31_60'       => $this->formatCurrency($d31_60),
            'days_61_90'       => $this->formatCurrency($d61_90),
            'days_90_plus'     => $this->formatCurrency($d90plus),
            'total'            => $this->formatCurrency($total),
            'current_raw'      => (float) $current,
            'days_1_30_raw'    => (float) $d1_30,
            'days_31_60_raw'   => (float) $d31_60,
            'days_61_90_raw'   => (float) $d61_90,
            'days_90_plus_raw' => (float) $d90plus,
            'total_raw'        => (float) $total,
        ];
    }

    private function buildRevenueExpenseChart(): array
    {
        if (!$this->hasCoreAccountingTables()) {
            return ['labels' => [], 'revenue' => [], 'expenses' => []];
        }

        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth()->format('Y-m-d');

        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->whereIn('a.type', ['revenue', 'expense'])
            ->whereDate('je.entry_date', '>=', $sixMonthsAgo)
            ->selectRaw("
                DATE_FORMAT(je.entry_date, '%Y-%m') as month,
                COALESCE(SUM(CASE WHEN a.type = 'revenue' AND jel.line_type = 'credit' THEN jel.amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN a.type = 'revenue' AND jel.line_type = 'debit'  THEN jel.amount ELSE 0 END), 0) as revenue,
                COALESCE(SUM(CASE WHEN a.type = 'expense' AND jel.line_type = 'debit'  THEN jel.amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN a.type = 'expense' AND jel.line_type = 'credit' THEN jel.amount ELSE 0 END), 0) as expenses
            ")
            ->groupByRaw("DATE_FORMAT(je.entry_date, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(je.entry_date, '%Y-%m')")
            ->get()
            ->keyBy('month');

        $labels   = [];
        $revenue  = [];
        $expenses = [];

        for ($i = 5; $i >= 0; $i--) {
            $month    = Carbon::now()->subMonths($i)->format('Y-m');
            $labels[] = Carbon::now()->subMonths($i)->format('M Y');
            $row      = $rows->get($month);
            $revenue[]  = round((float) ($row?->revenue  ?? 0), 2);
            $expenses[] = round((float) ($row?->expenses ?? 0), 2);
        }

        return ['labels' => $labels, 'revenue' => $revenue, 'expenses' => $expenses];
    }

    private function buildBankBalanceSummary(): array
    {
        if (!$this->hasCoreAccountingTables() || !Schema::hasTable('bank_accounts')) {
            return [];
        }

        $bankAccounts = DB::table('bank_accounts')
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->get(['bank_name', 'account_name', 'gl_code']);

        $result = [];

        foreach ($bankAccounts as $bank) {
            $balance = 0.0;

            if ($bank->gl_code) {
                $accountId = DB::table('accounts')->where('code', $bank->gl_code)->value('id');

                if ($accountId) {
                    $totals = DB::table('journal_entry_lines')
                        ->where('account_id', $accountId)
                        ->selectRaw("
                            COALESCE(SUM(CASE WHEN line_type = 'debit'  THEN amount ELSE 0 END), 0) as debit_total,
                            COALESCE(SUM(CASE WHEN line_type = 'credit' THEN amount ELSE 0 END), 0) as credit_total
                        ")
                        ->first();

                    $balance = round((float) $totals->debit_total - (float) $totals->credit_total, 2);
                }
            }

            $result[] = [
                'bank_name'    => $bank->bank_name,
                'account_name' => $bank->account_name,
                'balance'      => $this->formatCurrency($balance),
                'balance_raw'  => $balance,
            ];
        }

        return $result;
    }
}
