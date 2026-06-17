<template>
    <div>

        <!-- Date filter bar -->
        <div class="library-card mb-3">
            <div class="library-card-body py-2">
                <div class="acct-filter-bar">
                    <div class="acct-filter-field">
                        <label class="acct-filter-label">Date Range</label>
                        <DrawerDateRangePicker v-model:dateFrom="dateFrom" v-model:dateTo="dateTo" />
                    </div>
                    <div class="acct-filter-actions">
                        <button type="button" class="acct-btn-secondary" @click="clearFilter">Clear</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Not ready notice -->
        <div v-if="!dataReady" class="acct-empty-notice mb-3">
            <i class="ri-information-line"></i>
            Accounting tables are not ready yet. Run the accounting migrations to enable the dashboard.
        </div>

        <!-- Row 1: KPI Cards -->
        <div class="row g-3 mb-3">
            <div v-for="card in kpiCards" :key="card.title" class="col-sm-6 col-xl-3">
                <div class="acct-stat-card">
                    <div class="acct-stat-icon"><i :class="card.icon"></i></div>
                    <div>
                        <p class="acct-stat-label">{{ card.title }}</p>
                        <h4 class="acct-stat-value">{{ card.value }}</h4>
                        <p class="acct-stat-note">{{ card.description }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="dataReady">

            <!-- Row 2: Revenue vs Expenses chart + Cash & Banks -->
            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <div class="library-card h-100">
                        <div class="library-card-header">
                            <div class="d-flex align-items-center gap-3">
                                <div class="header-icon"><i class="ri-bar-chart-grouped-line"></i></div>
                                <div>
                                    <h4 class="header-title mb-0">Revenue vs Expenses</h4>
                                    <p class="header-subtitle mb-0">Last 6 months</p>
                                </div>
                            </div>
                        </div>
                        <div class="library-card-body">
                            <apexchart
                                v-if="revenueExpenseChart.labels && revenueExpenseChart.labels.length"
                                type="bar"
                                height="240"
                                :options="revExpChartOptions"
                                :series="revExpSeries"
                            />
                            <div v-else class="empty-state">
                                <i class="ri-bar-chart-line"></i>
                                <p>No transaction data yet</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="library-card h-100">
                        <div class="library-card-header">
                            <div class="d-flex align-items-center gap-3">
                                <div class="header-icon"><i class="ri-wallet-3-line"></i></div>
                                <div>
                                    <h4 class="header-title mb-0">Cash &amp; Banks</h4>
                                    <p class="header-subtitle mb-0">Live GL balances</p>
                                </div>
                            </div>
                        </div>
                        <div class="library-card-body p-0">
                            <div class="cash-total-bar">
                                <span class="cash-total-label">Total Cash</span>
                                <span class="cash-total-amount">{{ totalCash.formatted }}</span>
                            </div>
                            <div v-if="bankBalances.length" class="bank-list">
                                <div v-for="bank in bankBalances" :key="bank.bank_name + bank.account_name" class="bank-row">
                                    <div class="bank-icon-wrap"><i class="ri-bank-line"></i></div>
                                    <div class="bank-info">
                                        <span class="bank-name">{{ bank.bank_name }}</span>
                                        <span class="bank-acct">{{ bank.account_name }}</span>
                                    </div>
                                    <span class="bank-balance" :class="bank.balance_raw >= 0 ? 'pos' : 'neg'">{{ bank.balance }}</span>
                                </div>
                            </div>
                            <div v-else class="empty-state py-3">
                                <i class="ri-bank-line"></i>
                                <p class="mb-0 small text-muted">No bank accounts configured</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: AR Aging + AP Aging -->
            <div class="row g-3 mb-3">
                <!-- AR Aging -->
                <div class="col-md-6">
                    <div class="library-card h-100">
                        <div class="library-card-header">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="header-icon"><i class="ri-file-list-3-line"></i></div>
                                    <div>
                                        <h4 class="header-title mb-0">AR Aging</h4>
                                        <p class="header-subtitle mb-0">Customer balances by age</p>
                                    </div>
                                </div>
                                <a href="/accounting/accounts-receivable" class="view-all-link" @click.prevent="goTo('/accounting/accounts-receivable')">
                                    View All <i class="ri-arrow-right-line"></i>
                                </a>
                        </div>
                        <div class="library-card-body">
                            <div class="aging-total">
                                <span class="aging-total-label">Total Outstanding</span>
                                <span class="aging-total-value">{{ arAgingBuckets.total }}</span>
                            </div>
                            <div class="aging-grid">
                                <div class="aging-cell ag-current">
                                    <span class="aging-cell-label">Current</span>
                                    <span class="aging-cell-amount">{{ arAgingBuckets.current }}</span>
                                </div>
                                <div class="aging-cell ag-warn1">
                                    <span class="aging-cell-label">1–30 days</span>
                                    <span class="aging-cell-amount">{{ arAgingBuckets.days_1_30 }}</span>
                                </div>
                                <div class="aging-cell ag-warn2">
                                    <span class="aging-cell-label">31–60 days</span>
                                    <span class="aging-cell-amount">{{ arAgingBuckets.days_31_60 }}</span>
                                </div>
                                <div class="aging-cell ag-warn3">
                                    <span class="aging-cell-label">61–90 days</span>
                                    <span class="aging-cell-amount">{{ arAgingBuckets.days_61_90 }}</span>
                                </div>
                                <div class="aging-cell ag-danger">
                                    <span class="aging-cell-label">90+ days</span>
                                    <span class="aging-cell-amount">{{ arAgingBuckets.days_90_plus }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AP Aging -->
                <div class="col-md-6">
                    <div class="library-card h-100">
                        <div class="library-card-header">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="header-icon"><i class="ri-inbox-archive-line"></i></div>
                                    <div>
                                        <h4 class="header-title mb-0">AP Aging</h4>
                                        <p class="header-subtitle mb-0">Supplier balances by age</p>
                                    </div>
                                </div>
                                <a href="/accounting/accounts-payable" class="view-all-link" @click.prevent="goTo('/accounting/accounts-payable')">
                                    View All <i class="ri-arrow-right-line"></i>
                                </a>
                        </div>
                        <div class="library-card-body">
                            <div class="aging-total">
                                <span class="aging-total-label">Total Outstanding</span>
                                <span class="aging-total-value">{{ apAgingBuckets.total }}</span>
                            </div>
                            <div class="aging-grid">
                                <div class="aging-cell ag-current">
                                    <span class="aging-cell-label">Current</span>
                                    <span class="aging-cell-amount">{{ apAgingBuckets.current }}</span>
                                </div>
                                <div class="aging-cell ag-warn1">
                                    <span class="aging-cell-label">1–30 days</span>
                                    <span class="aging-cell-amount">{{ apAgingBuckets.days_1_30 }}</span>
                                </div>
                                <div class="aging-cell ag-warn2">
                                    <span class="aging-cell-label">31–60 days</span>
                                    <span class="aging-cell-amount">{{ apAgingBuckets.days_31_60 }}</span>
                                </div>
                                <div class="aging-cell ag-warn3">
                                    <span class="aging-cell-label">61–90 days</span>
                                    <span class="aging-cell-amount">{{ apAgingBuckets.days_61_90 }}</span>
                                </div>
                                <div class="aging-cell ag-danger">
                                    <span class="aging-cell-label">90+ days</span>
                                    <span class="aging-cell-amount">{{ apAgingBuckets.days_90_plus }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 4: P&L + Balance Sheet snapshots -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="library-card h-100">
                        <div class="library-card-header">
                            <div class="d-flex align-items-center gap-3">
                                <div class="header-icon"><i class="ri-line-chart-line"></i></div>
                                <div>
                                    <h4 class="header-title mb-0">Profit &amp; Loss</h4>
                                    <p class="header-subtitle mb-0">Income statement snapshot</p>
                                </div>
                            </div>
                        </div>
                        <div class="library-card-body">
                            <table class="snapshot-table w-100">
                                <tbody>
                                    <tr>
                                        <td class="snap-label">Revenue</td>
                                        <td class="snap-value text-end">{{ plSummary.revenue }}</td>
                                    </tr>
                                    <tr>
                                        <td class="snap-label text-muted ps-3">Cost of Sales</td>
                                        <td class="snap-value text-end text-muted">{{ plSummary.cost_of_sales }}</td>
                                    </tr>
                                    <tr class="snap-divider-row">
                                        <td class="snap-label fw-semibold">Gross Profit</td>
                                        <td class="snap-value text-end fw-semibold" :class="plSummary.gross_profit_raw >= 0 ? 'text-success-soft' : 'text-danger-soft'">
                                            {{ plSummary.gross_profit }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="snap-label text-muted ps-3">Operating Expenses</td>
                                        <td class="snap-value text-end text-muted">{{ plSummary.operating_expenses }}</td>
                                    </tr>
                                    <tr class="snap-total-row">
                                        <td class="snap-label fw-bold">Net Income</td>
                                        <td class="snap-value text-end fw-bold">
                                            <span class="net-chip" :class="plSummary.net_income_raw >= 0 ? 'profit' : 'loss'">
                                                {{ plSummary.net_income }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="library-card h-100">
                        <div class="library-card-header">
                            <div class="d-flex align-items-center gap-3">
                                <div class="header-icon"><i class="ri-bank-card-line"></i></div>
                                <div>
                                    <h4 class="header-title mb-0">Balance Sheet</h4>
                                    <p class="header-subtitle mb-0">Financial position snapshot</p>
                                </div>
                            </div>
                        </div>
                        <div class="library-card-body">
                            <table class="snapshot-table w-100">
                                <tbody>
                                    <tr>
                                        <td class="snap-label">Total Assets</td>
                                        <td class="snap-value text-end fw-semibold">{{ bsSummary.total_assets }}</td>
                                    </tr>
                                    <tr>
                                        <td class="snap-label text-muted ps-3">Liabilities</td>
                                        <td class="snap-value text-end text-muted">{{ bsSummary.total_liabilities }}</td>
                                    </tr>
                                    <tr>
                                        <td class="snap-label text-muted ps-3">Equity</td>
                                        <td class="snap-value text-end text-muted">{{ bsSummary.total_equity }}</td>
                                    </tr>
                                    <tr class="snap-divider-row">
                                        <td class="snap-label fw-semibold">Liabilities + Equity</td>
                                        <td class="snap-value text-end fw-semibold">{{ bsSummary.liabilities_and_equity }}</td>
                                    </tr>
                                    <tr class="snap-total-row">
                                        <td colspan="2" class="text-center pt-2">
                                            <span class="balance-chip" :class="bsSummary.is_balanced ? 'balanced' : 'unbalanced'">
                                                <i :class="bsSummary.is_balanced ? 'ri-checkbox-circle-line' : 'ri-error-warning-line'"></i>
                                                {{ bsSummary.is_balanced ? 'Balanced' : 'Out of Balance' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 5: Recent journal entries -->
            <div class="library-card">
                <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-book-2-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Recent Journal Entries</h4>
                                <p class="header-subtitle mb-0">Latest 10 postings</p>
                            </div>
                        </div>
                        <a href="/accounting/journal-entries" class="view-all-link" @click.prevent="goTo('/accounting/journal-entries')">
                            View All <i class="ri-arrow-right-line"></i>
                        </a>
                </div>
                <div class="library-card-body p-0">
                    <div v-if="recentEntries.length === 0" class="empty-state">
                        <i class="ri-book-open-line"></i>
                        <p class="mb-1">No journal entries found</p>
                        <small>Entries will appear here once transactions are posted.</small>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table dash-je-table mb-0">
                            <thead>
                                <tr>
                                    <th>Journal #</th>
                                    <th>Date</th>
                                    <th>Source</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="entry in recentEntries" :key="entry.id">
                                    <td class="font-monospace">{{ entry.journal_number }}</td>
                                    <td>{{ entry.entry_date }}</td>
                                    <td>
                                        <span class="source-badge" :class="sourceBadgeClass(entry.source_label)">
                                            {{ entry.source_label }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ entry.debit_total }}</td>
                                    <td class="text-end">{{ entry.credit_total }}</td>
                                    <td class="text-center">
                                        <span class="status-chip" :class="entry.status">{{ entry.status }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- /v-if dataReady -->

    </div>
</template>

<script>
import { router } from "@inertiajs/vue3";
import MainLayout from "@/Shared/Layouts/Main.vue";
import AccountingLayout from "@/Pages/Modules/Accounting/AccountingLayout.vue";
import DrawerDateRangePicker from "@/Pages/Modules/Accounting/Components/DrawerDateRangePicker.vue";

export default {
    layout: [MainLayout, AccountingLayout],
    components: { DrawerDateRangePicker },
    props: {
        stats:               { type: Object,  default: () => ({}) },
        dataReady:           { type: Boolean, default: false },
        filters:             { type: Object,  default: () => ({}) },
        kpiCards:            { type: Array,   default: () => [] },
        plSummary:           { type: Object,  default: () => ({}) },
        bsSummary:           { type: Object,  default: () => ({}) },
        recentEntries:       { type: Array,   default: () => [] },
        topAccounts:         { type: Array,   default: () => [] },
        totalCash:           { type: Object,  default: () => ({ formatted: 'P 0.00', raw: 0 }) },
        outstandingAr:       { type: Object,  default: () => ({ formatted: 'P 0.00', raw: 0 }) },
        arAgingBuckets:      { type: Object,  default: () => ({}) },
        apAgingBuckets:      { type: Object,  default: () => ({}) },
        revenueExpenseChart: { type: Object,  default: () => ({ labels: [], revenue: [], expenses: [] }) },
        bankBalances:        { type: Array,   default: () => [] },
    },
    data() {
        return {
            dateFrom: this.filters?.date_from || '',
            dateTo:   this.filters?.date_to   || '',
        };
    },
    watch: {
        dateTo() { if (this.dateTo) this.applyFilter(); },
    },
    computed: {
        revExpSeries() {
            return [
                { name: 'Revenue',  data: this.revenueExpenseChart.revenue  || [] },
                { name: 'Expenses', data: this.revenueExpenseChart.expenses || [] },
            ];
        },
        revExpChartOptions() {
            return {
                chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
                colors: ['#3d8d7a', '#e05252'],
                plotOptions: { bar: { columnWidth: '60%', borderRadius: 3 } },
                dataLabels: { enabled: false },
                xaxis: {
                    categories: this.revenueExpenseChart.labels || [],
                    labels: { style: { fontSize: '11px', colors: '#6b8c85' } },
                },
                yaxis: {
                    labels: {
                        formatter: (v) => 'P ' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }),
                        style: { fontSize: '11px', colors: ['#6b8c85'] },
                    },
                },
                tooltip: {
                    y: { formatter: (v) => 'P ' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
                },
                legend: { position: 'top', fontSize: '12px' },
                grid: { strokeDashArray: 4, borderColor: '#e5ede9' },
            };
        },
    },
    methods: {
        applyFilter() {
            router.get('/accounting', {
                date_from: this.dateFrom || undefined,
                date_to:   this.dateTo   || undefined,
            }, { preserveScroll: true });
        },
        clearFilter() {
            this.dateFrom = '';
            this.dateTo   = '';
            router.get('/accounting', {}, { preserveScroll: true });
        },
        goTo(href) {
            router.visit(href, { preserveScroll: true });
        },
        sourceBadgeClass(label) {
            const map = {
                'AR':        'badge-ar',
                'AP':        'badge-ap',
                'Payroll':   'badge-payroll',
                'Inventory': 'badge-inventory',
                'Manual':    'badge-manual',
            };
            return map[label] || 'badge-manual';
        },
    },
};
</script>

<style scoped>
/* Snapshot tables */
.snapshot-table { border-collapse: collapse; }
.snapshot-table td { padding: 0.5rem 0.25rem; font-size: 0.85rem; }
.snap-divider-row td { border-top: 1px solid #dde8e3; padding-top: 0.65rem; }
.snap-total-row td { border-top: 2px solid #c4d9d2; padding-top: 0.65rem; }
.text-success-soft { color: #166534; }
.text-danger-soft  { color: #991b1b; }

/* Net income chip */
.net-chip {
    display: inline-flex;
    align-items: center;
    padding: 3px 12px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
}
.net-chip.profit { background: #dcfce7; color: #166534; }
.net-chip.loss   { background: #fee2e2; color: #991b1b; }

/* Balance sheet chip */
.balance-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 3px 12px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}
.balance-chip.balanced   { background: #dcfce7; color: #166534; }
.balance-chip.unbalanced { background: #fee2e2; color: #991b1b; }

/* View all link */
.view-all-link {
    font-size: 0.8rem;
    font-weight: 700;
    color: #3d8d7a;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.view-all-link:hover { color: #277660; text-decoration: underline; }

/* Journal entries table */
.dash-je-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}
.dash-je-table tbody td { font-size: 0.83rem; vertical-align: middle; }

/* Source badges */
.source-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
}
.badge-ar        { background: #dbeafe; color: #1e4d8c; }
.badge-ap        { background: #fee2e2; color: #7c2d12; }
.badge-payroll   { background: #ede9fe; color: #5b21b6; }
.badge-inventory { background: #dcfce7; color: #166534; }
.badge-manual    { background: #f1f5f9; color: #475569; }

/* Status chip */
.status-chip {
    display: inline-flex;
    align-items: center;
    padding: 2px 9px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: capitalize;
}
.status-chip.posted    { background: #e7f7f2; color: #277660; }
.status-chip.draft     { background: #f1f5f9; color: #475569; }
.status-chip.reversed  { background: #fff1f1; color: #b15050; }

/* Empty state */
.empty-state {
    padding: 2rem;
    text-align: center;
    color: #648b74;
}
.empty-state i {
    font-size: 2rem;
    color: #3d8d7a;
    display: block;
    margin-bottom: 0.5rem;
}

/* Cash & Banks card */
.cash-total-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1.1rem;
    background: linear-gradient(to right, #1a4035, #2d6b58);
    color: #fff;
}
.cash-total-label  { font-size: 0.78rem; font-weight: 600; opacity: 0.85; }
.cash-total-amount { font-size: 1rem; font-weight: 800; }

.bank-list { }
.bank-row {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.6rem 1.1rem;
    border-bottom: 1px solid #edf4f1;
}
.bank-row:last-child { border-bottom: none; }
.bank-icon-wrap {
    width: 30px;
    height: 30px;
    border-radius: 7px;
    background: rgba(61, 141, 122, 0.1);
    color: #3d8d7a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.bank-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}
.bank-name {
    font-size: 0.8rem;
    font-weight: 700;
    color: #16322e;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bank-acct {
    font-size: 0.7rem;
    color: #6b8c85;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bank-balance {
    font-size: 0.8rem;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
}
.bank-balance.pos { color: #166534; }
.bank-balance.neg { color: #991b1b; }

/* Aging sections */
.aging-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.85rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid #edf4f1;
}
.aging-total-label { font-size: 0.8rem; color: #6b8c85; }
.aging-total-value { font-size: 0.95rem; font-weight: 800; color: #16322e; }

.aging-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.5rem;
}
@media (max-width: 900px) {
    .aging-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 600px) {
    .aging-grid { grid-template-columns: repeat(2, 1fr); }
}

.aging-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.65rem 0.4rem 0.5rem;
    border-radius: 8px;
    text-align: center;
    border-top: 3px solid transparent;
}
.aging-cell-label  { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.35rem; }
.aging-cell-amount { font-size: 0.78rem; font-weight: 800; }

.aging-cell.ag-current  { background: #e7f7ef; border-top-color: #3d8d7a; }
.aging-cell.ag-current .aging-cell-label  { color: #277660; }
.aging-cell.ag-current .aging-cell-amount { color: #166534; }

.aging-cell.ag-warn1    { background: #fefce8; border-top-color: #ca8a04; }
.aging-cell.ag-warn1 .aging-cell-label  { color: #92400e; }
.aging-cell.ag-warn1 .aging-cell-amount { color: #78350f; }

.aging-cell.ag-warn2    { background: #fff7ed; border-top-color: #ea580c; }
.aging-cell.ag-warn2 .aging-cell-label  { color: #9a3412; }
.aging-cell.ag-warn2 .aging-cell-amount { color: #7c2d12; }

.aging-cell.ag-warn3    { background: #fff1ee; border-top-color: #dc2626; }
.aging-cell.ag-warn3 .aging-cell-label  { color: #991b1b; }
.aging-cell.ag-warn3 .aging-cell-amount { color: #7f1d1d; }

.aging-cell.ag-danger   { background: #fef2f2; border-top-color: #991b1b; }
.aging-cell.ag-danger .aging-cell-label  { color: #7f1d1d; }
.aging-cell.ag-danger .aging-cell-amount { color: #450a0a; }
</style>
