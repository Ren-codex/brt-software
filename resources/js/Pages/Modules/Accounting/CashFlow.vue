<template>
    <div>

        <!-- Summary cards -->
        <div class="row g-3 mb-3">
            <div v-for="card in summaryCards" :key="card.title" class="col-sm-6 col-xl-3">
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

        <!-- Filter bar with period presets -->
        <div class="library-card mb-3">
            <div class="library-card-body py-2">
                <div class="cf-presets mb-2">
                    <span class="cf-presets-label">Quick:</span>
                    <button
                        v-for="p in presets"
                        :key="p.label"
                        class="cf-preset-btn"
                        :class="{ active: dateFrom === p.from && dateTo === p.to }"
                        @click="applyPreset(p)"
                    >{{ p.label }}</button>
                </div>
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

        <!-- Not ready -->
        <div v-if="!dataReady" class="acct-empty-notice">
            <i class="ri-information-line"></i>
            Accounting tables are not ready yet. Run the accounting migrations to populate cash flow data.
        </div>

        <template v-else>

            <!-- Waterfall Chart -->
            <div class="library-card mb-3">
                <div class="library-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon"><i class="ri-bar-chart-horizontal-line"></i></div>
                        <div>
                            <h4 class="header-title mb-0">Cash Flow Overview</h4>
                            <p class="header-subtitle mb-0">Net cash by activity — green = inflow, red = outflow.</p>
                        </div>
                    </div>
                    <button class="cf-toggle-btn" @click="showChart = !showChart">
                        <i :class="showChart ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                        {{ showChart ? 'Hide' : 'Show' }} Chart
                    </button>
                </div>
                <div v-show="showChart" class="library-card-body">
                    <apexchart type="bar" height="180" :options="chartOptions" :series="chartSeries" />
                </div>
            </div>

            <!-- Main Statement Panel -->
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon"><i class="ri-funds-box-line"></i></div>
                        <div>
                            <h4 class="header-title mb-0">Cash Flow Statement</h4>
                            <p class="header-subtitle mb-0">Direct method — operating, investing, and financing cash movements for the selected period.</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="net-chip" :class="totals.net_change >= 0 ? 'positive' : 'negative'">
                            <i :class="totals.net_change >= 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line'"></i>
                            {{ totals.net_change >= 0 ? 'Net Increase' : 'Net Decrease' }}
                        </span>
                        <div class="export-dropdown">
                            <button class="export-btn" @click="exportOpen = !exportOpen">
                                <i class="ri-download-2-line"></i> Export
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div v-if="exportOpen" class="export-menu">
                                <a :href="exportUrl('excel')" class="export-item" @click="exportOpen = false">
                                    <i class="ri-file-excel-2-line"></i> Excel (.xlsx)
                                </a>
                                <a :href="exportUrl('pdf')" class="export-item" @click="exportOpen = false">
                                    <i class="ri-file-pdf-line"></i> PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="library-card-body p-0">

                    <!-- Opening Balance -->
                    <div v-if="filters.date_from" class="cf-balance-banner opening">
                        <span class="cf-balance-label"><i class="ri-time-line me-1"></i>Opening Cash Balance</span>
                        <span class="cf-balance-value">{{ totals.opening_balance_formatted }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table cf-table mb-0">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-muted" style="width:120px">Entries</th>
                                    <th class="text-end" style="width:160px">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="section in sections" :key="section.id">

                                    <!-- Section header row -->
                                    <tr class="cf-section-header-row">
                                        <td colspan="3">
                                            <span class="cf-section-chip" :class="section.id">
                                                <i :class="section.icon"></i>
                                                {{ section.title }}
                                            </span>
                                            <span class="cf-section-desc ms-2">{{ section.description }}</span>
                                        </td>
                                    </tr>

                                    <!-- Empty -->
                                    <tr v-if="section.rows.length === 0">
                                        <td colspan="3" class="text-muted fst-italic ps-4 py-2" style="font-size:0.82rem">
                                            No {{ section.title.toLowerCase() }} entries for this period.
                                        </td>
                                    </tr>

                                    <!-- Data rows -->
                                    <tr
                                        v-for="(row, i) in section.rows"
                                        :key="i"
                                        class="cf-data-row"
                                        :class="{ 'cf-row-clickable': row.details?.length > 0 }"
                                        @click="row.details?.length > 0 && openDrawer(row, section)"
                                    >
                                        <td class="cf-row-label ps-4">
                                            {{ row.label }}
                                            <span v-if="row.note" class="cf-row-note">{{ row.note }}</span>
                                            <i v-if="row.details?.length > 0" class="ri-arrow-right-s-line row-arrow"></i>
                                        </td>
                                        <td class="text-muted" style="font-size:0.78rem">
                                            {{ row.entries ? row.entries + (row.entries === 1 ? ' entry' : ' entries') : '' }}
                                        </td>
                                        <td class="text-end cf-row-amount" :class="row.direction">
                                            <span v-if="row.direction === 'outflow'" class="cf-paren">(</span>{{ row.amount }}<span v-if="row.direction === 'outflow'" class="cf-paren">)</span>
                                        </td>
                                    </tr>

                                    <!-- Section subtotal -->
                                    <tr class="cf-subtotal-row">
                                        <td colspan="2" class="text-end">
                                            <small>Net {{ section.title }}</small>
                                        </td>
                                        <td class="text-end fw-bold" :class="section.net_raw >= 0 ? 'text-inflow' : 'text-outflow'">
                                            <span v-if="section.net_raw < 0">(</span>{{ section.net }}<span v-if="section.net_raw < 0">)</span>
                                        </td>
                                    </tr>

                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Net Change -->
                    <div class="cf-net-change" :class="totals.net_change >= 0 ? 'positive' : 'negative'">
                        <div class="cf-net-left">
                            <i :class="totals.net_change >= 0 ? 'ri-arrow-up-circle-fill' : 'ri-arrow-down-circle-fill'"></i>
                            <div>
                                <div class="cf-net-label">Net Change in Cash</div>
                                <div class="cf-net-value">{{ totals.net_change_formatted }}</div>
                            </div>
                        </div>
                        <div class="cf-net-breakdown">
                            <div class="cf-net-item">
                                <span class="cf-net-item-label">Operating</span>
                                <span class="cf-net-item-value" :class="totals.net_operating >= 0 ? 'text-inflow' : 'text-outflow'">{{ totals.net_operating_formatted }}</span>
                            </div>
                            <div class="cf-net-sep">+</div>
                            <div class="cf-net-item">
                                <span class="cf-net-item-label">Investing</span>
                                <span class="cf-net-item-value" :class="totals.net_investing >= 0 ? 'text-inflow' : 'text-outflow'">{{ totals.net_investing_formatted }}</span>
                            </div>
                            <div class="cf-net-sep">+</div>
                            <div class="cf-net-item">
                                <span class="cf-net-item-label">Financing</span>
                                <span class="cf-net-item-value" :class="totals.net_financing >= 0 ? 'text-inflow' : 'text-outflow'">{{ totals.net_financing_formatted }}</span>
                            </div>
                            <div class="cf-net-sep">=</div>
                            <div class="cf-net-item">
                                <span class="cf-net-item-label">Net Change</span>
                                <span class="cf-net-item-value fw-bold" :class="totals.net_change >= 0 ? 'text-inflow' : 'text-outflow'">{{ totals.net_change_formatted }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Closing Balance -->
                    <div v-if="filters.date_from" class="cf-balance-banner closing">
                        <span class="cf-balance-label"><i class="ri-flag-line me-1"></i>Closing Cash Balance</span>
                        <span class="cf-balance-value" :class="totals.closing_balance >= 0 ? 'text-inflow' : 'text-outflow'">
                            {{ totals.closing_balance_formatted }}
                        </span>
                    </div>

                </div>
            </div>

        </template>

        <!-- ── Drawer overlay ─────────────────────────────────────── -->
        <div v-if="drawer.open" class="drawer-overlay" @click="closeDrawer"></div>

        <!-- ── Drawer panel ───────────────────────────────────────── -->
        <div class="drawer-panel" :class="{ 'drawer-open': drawer.open }">

            <div class="drawer-header">
                <div class="drawer-header-info">
                    <span class="drawer-section-tag" :class="drawer.section?.id">
                        <i :class="drawer.section?.icon"></i>
                        {{ drawer.section?.title }}
                    </span>
                    <h6 class="drawer-row-name">{{ drawer.row?.label }}</h6>
                    <p class="drawer-period mb-0">{{ drawer.row?.note }}</p>
                </div>
                <button class="drawer-close-btn" @click="closeDrawer">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="drawer-filter-bar">
                <div class="drawer-filter-fields">
                    <DrawerDateRangePicker
                        v-model:dateFrom="drawer.dateFrom"
                        v-model:dateTo="drawer.dateTo"
                    />
                </div>
                <div class="drawer-search-wrap mt-2">
                    <i class="ri-search-line drawer-search-icon"></i>
                    <input v-model="drawer.search" type="text" class="drawer-search-input" placeholder="Search journal, description…" />
                    <button v-if="drawer.search" class="drawer-search-clear" @click="drawer.search = ''">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>

            <div class="drawer-body">

                <!-- Opening balance -->
                <div class="drawer-balance-row opening">
                    <span class="dbal-label">Opening Balance</span>
                    <span class="dbal-value">{{ filters.date_from ? totals.opening_balance_formatted : '—' }}</span>
                </div>

                <div class="drawer-table-wrap">
                    <table class="drawer-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Journal #</th>
                                <th>Description</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredDrawerLines.length === 0">
                                <td colspan="5" class="drawer-empty">
                                    <i class="ri-inbox-line"></i>
                                    No transactions found.
                                </td>
                            </tr>
                            <tr v-for="(d, i) in filteredDrawerLines" :key="i" class="drawer-line-row">
                                <td class="text-nowrap">{{ d.date }}</td>
                                <td class="font-monospace drawer-jnum">{{ d.journal_number }}</td>
                                <td class="drawer-desc">{{ d.description }}</td>
                                <td class="text-end drawer-num debit-col">{{ d.debit ?? '' }}</td>
                                <td class="text-end drawer-num credit-col">{{ d.credit ?? '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Closing balance -->
                <div class="drawer-balance-row closing">
                    <span class="dbal-label">Closing Balance</span>
                    <span class="dbal-value">{{ filters.date_from ? totals.closing_balance_formatted : '—' }}</span>
                </div>

                <div class="drawer-summary">
                    <div class="drawer-summary-item">
                        <span class="ds-label">Period Debits</span>
                        <span class="ds-value debit-col">{{ drawerDebits }}</span>
                    </div>
                    <div class="drawer-summary-item">
                        <span class="ds-label">Period Credits</span>
                        <span class="ds-value credit-col">{{ drawerCredits }}</span>
                    </div>
                    <div class="drawer-summary-item">
                        <span class="ds-label">Net Change</span>
                        <span class="ds-value" :class="drawer.row?.direction === 'inflow' ? 'debit-col' : 'credit-col'">
                            <span v-if="drawer.row?.direction === 'outflow'">(</span>{{ drawer.row?.amount }}<span v-if="drawer.row?.direction === 'outflow'">)</span>
                        </span>
                    </div>
                </div>

            </div>

        </div>

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
        stats:        Object,
        dataReady:    Boolean,
        summaryCards: Array,
        sections:     { type: Array,  default: () => [] },
        totals:       { type: Object, default: () => ({}) },
        filters:      { type: Object, default: () => ({}) },
    },
    data() {
        return {
            dateFrom:   this.filters?.date_from || '',
            dateTo:     this.filters?.date_to   || '',
            exportOpen: false,
            showChart:  true,
            drawer: {
                open:     false,
                row:      null,
                section:  null,
                search:   '',
                dateFrom: '',
                dateTo:   '',
            },
        };
    },
    computed: {
        presets() {
            const now   = new Date();
            const pad   = n => String(n).padStart(2, '0');
            const fmt   = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            const today = fmt(now);
            const qM    = Math.floor(now.getMonth() / 3) * 3;
            return [
                { label: 'This Month',   from: fmt(new Date(now.getFullYear(), now.getMonth(), 1)),     to: today },
                { label: 'Last Month',   from: fmt(new Date(now.getFullYear(), now.getMonth() - 1, 1)), to: fmt(new Date(now.getFullYear(), now.getMonth(), 0)) },
                { label: 'This Quarter', from: fmt(new Date(now.getFullYear(), qM, 1)),                 to: today },
                { label: 'YTD',          from: fmt(new Date(now.getFullYear(), 0, 1)),                  to: today },
                { label: 'All Time',     from: '',                                                      to: '' },
            ];
        },
        chartSeries() {
            return [{
                name: 'Net Cash',
                data: [
                    this.totals.net_operating || 0,
                    this.totals.net_investing || 0,
                    this.totals.net_financing || 0,
                    this.totals.net_change    || 0,
                ],
            }];
        },
        chartOptions() {
            const fmtVal = val => {
                const abs = Math.abs(val);
                const s   = '₱' + abs.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return val < 0 ? `(${s})` : s;
            };
            return {
                chart:   { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
                plotOptions: {
                    bar: {
                        horizontal: true, borderRadius: 6, barHeight: '55%',
                        colors: {
                            ranges: [
                                { from: -9999999999, to: -0.005, color: '#ef4444' },
                                { from:  -0.004,    to: 9999999999, color: '#16a34a' },
                            ],
                        },
                    },
                },
                dataLabels: {
                    enabled: true, formatter: fmtVal, offsetX: 4,
                    style: { fontSize: '11px', fontWeight: '600', colors: ['#1a2e29'] },
                },
                xaxis: {
                    categories: ['Operating', 'Investing', 'Financing', 'Net Change'],
                    labels: { formatter: fmtVal, style: { fontSize: '11px', colors: '#527267' } },
                },
                yaxis: { labels: { style: { fontSize: '12px', fontWeight: '600', colors: ['#335c52'] } } },
                grid:  { borderColor: '#e8f0ed', xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
                tooltip: { y: { formatter: fmtVal } },
            };
        },
        filteredDrawerLines() {
            let lines = this.drawer.row?.details || [];
            if (this.drawer.dateFrom) lines = lines.filter(l => (l.date || '') >= this.drawer.dateFrom);
            if (this.drawer.dateTo)   lines = lines.filter(l => (l.date || '') <= this.drawer.dateTo);
            if (!this.drawer.search) return lines;
            const q = this.drawer.search.toLowerCase();
            return lines.filter(l =>
                (l.journal_number || '').toLowerCase().includes(q) ||
                (l.description    || '').toLowerCase().includes(q) ||
                (l.date           || '').includes(q)
            );
        },
        drawerDebits() {
            const total = this.filteredDrawerLines
                .filter(d => d.debit)
                .reduce((s, d) => s + (d.amount_raw || 0), 0);
            return '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        drawerCredits() {
            const total = this.filteredDrawerLines
                .filter(d => d.credit)
                .reduce((s, d) => s + (d.amount_raw || 0), 0);
            return '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    },
    watch: {
        dateTo()           { if (this.dateTo) this.applyFilter(); },
        'drawer.open'(val) { document.body.style.overflow = val ? 'hidden' : ''; },
    },
    methods: {
        applyFilter() {
            router.get('/accounting/cash-flow', {
                date_from: this.dateFrom || undefined,
                date_to:   this.dateTo   || undefined,
            }, { preserveScroll: true });
        },
        clearFilter() {
            this.dateFrom = '';
            this.dateTo   = '';
            router.get('/accounting/cash-flow', {}, { preserveScroll: true });
        },
        applyPreset(p) {
            this.dateFrom = p.from;
            this.dateTo   = p.to;
            router.get('/accounting/cash-flow', {
                date_from: p.from || undefined,
                date_to:   p.to   || undefined,
            }, { preserveScroll: true });
        },
        exportUrl(format) {
            const params = new URLSearchParams({ option: format });
            if (this.filters?.date_from) params.set('date_from', this.filters.date_from);
            if (this.filters?.date_to)   params.set('date_to',   this.filters.date_to);
            return '/accounting/cash-flow?' + params.toString();
        },
        openDrawer(row, section) {
            this.drawer.row      = row;
            this.drawer.section  = section;
            this.drawer.search   = '';
            this.drawer.dateFrom = this.filters?.date_from || '';
            this.drawer.dateTo   = this.filters?.date_to   || '';
            this.drawer.open     = true;
        },
        closeDrawer() {
            this.drawer.open = false;
        },
    },
};
</script>

<style scoped>
/* ── Period presets ─────────────────────────────────────────── */
.cf-presets { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; }
.cf-presets-label { font-size: 0.72rem; font-weight: 700; color: #9ab8af; text-transform: uppercase; letter-spacing: 0.04em; }
.cf-preset-btn {
    padding: 0.22rem 0.7rem; border-radius: 999px;
    border: 1px solid #c4d9d2; background: #f7fbfa;
    color: #335c52; font-size: 0.78rem; font-weight: 600;
    cursor: pointer; transition: all 0.12s;
}
.cf-preset-btn:hover  { background: #edf5f2; border-color: #3d8d7a; }
.cf-preset-btn.active { background: #3d8d7a; border-color: #3d8d7a; color: #fff; }

/* ── Chart toggle ───────────────────────────────────────────── */
.cf-toggle-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.38rem 0.8rem; border-radius: 8px; border: 1px solid #c4d9d2;
    background: #f7fbfa; color: #335c52; font-size: 0.82rem; font-weight: 600;
    cursor: pointer;
}
.cf-toggle-btn:hover { background: #edf5f2; }

/* ── Net chip ───────────────────────────────────────────────── */
.net-chip {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.3rem 0.8rem; border-radius: 999px;
    font-size: 0.78rem; font-weight: 700;
}
.net-chip.positive { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.net-chip.negative { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

/* ── Opening / Closing balance banners ──────────────────────── */
.cf-balance-banner {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.6rem 1rem; font-size: 0.84rem;
}
.cf-balance-banner.opening { background: #f0f9ff; border-bottom: 1px solid #bae6fd; }
.cf-balance-banner.closing { background: #f0fdf4; border-top: 1px solid #bbf7d0; }
.cf-balance-label { font-weight: 600; color: #334155; }
.cf-balance-value { font-weight: 800; font-size: 0.95rem; color: #1a2e29; }

/* ── Main table ─────────────────────────────────────────────── */
.cf-table thead th {
    background: #edf5f2; color: #527267;
    font-size: 0.74rem; font-weight: 700; text-transform: uppercase;
    padding: 0.6rem 0.85rem; white-space: nowrap;
}
.cf-table tbody td { font-size: 0.85rem; vertical-align: middle; padding: 0.55rem 0.85rem; }

.cf-section-header-row td {
    background: #f4f9f7; padding: 0.45rem 0.85rem;
    border-top: 2px solid #daeee6;
}
.cf-section-chip {
    display: inline-flex; align-items: center; gap: 0.35rem;
    font-size: 0.72rem; font-weight: 800;
    letter-spacing: 0.06em; text-transform: uppercase;
    padding: 2px 10px; border-radius: 999px;
}
.cf-section-chip.operating { color: #166534; background: #dcfce7; }
.cf-section-chip.investing  { color: #1e4d8c; background: #dbeafe; }
.cf-section-chip.financing  { color: #5b21b6; background: #ede9fe; }
.cf-section-desc { font-size: 0.75rem; color: #9ab8af; }

.cf-data-row { transition: background 0.12s; }
.cf-data-row.cf-row-clickable { cursor: pointer; }
.cf-data-row.cf-row-clickable:hover { background: #f0faf6; }
.cf-data-row.cf-row-clickable:hover .row-arrow { opacity: 1; transform: translateX(3px); }

.row-arrow {
    font-size: 0.9rem; color: #3d8d7a;
    opacity: 0; transition: opacity 0.15s, transform 0.15s;
    vertical-align: middle; margin-left: 0.2rem;
}

.cf-row-label { width: 55%; }
.cf-row-note  { display: block; font-size: 0.72rem; color: #94a3b8; font-weight: 400; margin-top: 1px; }
.cf-row-amount { font-weight: 600; font-family: 'Courier New', monospace; white-space: nowrap; }
.cf-paren { font-size: 0.85em; }

.cf-subtotal-row td {
    background: #f9fcfb; border-top: 1px dashed #c9e0d8;
    font-size: 0.8rem; color: #4a6b62; padding: 0.4rem 0.85rem;
}

.text-inflow  { color: #166534; }
.text-outflow { color: #991b1b; }
.inflow  { color: #166534; }
.outflow { color: #991b1b; }

/* ── Net Change box ─────────────────────────────────────────── */
.cf-net-change {
    display: flex; align-items: center; justify-content: space-between;
    gap: 1rem; padding: 1rem 1.25rem; flex-wrap: wrap;
}
.cf-net-change.positive { background: #f0fdf4; border-top: 2px solid #86efac; }
.cf-net-change.negative { background: #fef2f2; border-top: 2px solid #fca5a5; }

.cf-net-left { display: flex; align-items: center; gap: 0.75rem; }
.cf-net-left > i { font-size: 1.6rem; }
.cf-net-change.positive .cf-net-left > i { color: #16a34a; }
.cf-net-change.negative .cf-net-left > i { color: #dc2626; }
.cf-net-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
.cf-net-value { font-size: 1.15rem; font-weight: 800; color: #1a2e29; }

.cf-net-breakdown { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.cf-net-item       { display: flex; flex-direction: column; align-items: center; }
.cf-net-item-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: #9ca3af; }
.cf-net-item-value { font-size: 0.82rem; font-weight: 700; }
.cf-net-sep        { font-size: 1rem; color: #9ca3af; font-weight: 700; }

/* ── Export dropdown ────────────────────────────────────────── */
.export-dropdown { position: relative; }
.export-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.38rem 0.8rem; border-radius: 8px; border: 1px solid #c4d9d2;
    background: #f7fbfa; color: #335c52; font-size: 0.82rem; font-weight: 600;
    cursor: pointer; white-space: nowrap;
}
.export-btn:hover { background: #edf5f2; }
.export-menu {
    position: absolute; right: 0; top: calc(100% + 4px); z-index: 100;
    background: #fff; border: 1px solid #d1e4dc; border-radius: 10px;
    box-shadow: 0 4px 16px rgba(28,49,45,0.10); min-width: 160px; overflow: hidden;
}
.export-item {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.6rem 0.9rem; color: #335c52; font-size: 0.84rem;
    text-decoration: none; transition: background 0.12s;
}
.export-item:hover { background: #edf5f2; }
.export-item i { font-size: 1rem; }

/* ── Drawer overlay ─────────────────────────────────────────── */
.drawer-overlay {
    position: fixed; inset: 0; z-index: 1040;
    background: rgba(10, 28, 25, 0.35);
    backdrop-filter: blur(2px);
    animation: fadeIn 0.2s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* ── Drawer panel ───────────────────────────────────────────── */
.drawer-panel {
    position: fixed; top: 0; right: 0; bottom: 0; z-index: 1050;
    width: 560px; max-width: 95vw;
    background: #fff;
    box-shadow: -6px 0 32px rgba(10,28,25,0.14);
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}
.drawer-panel.drawer-open { transform: translateX(0); }

/* ── Drawer header ──────────────────────────────────────────── */
.drawer-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(to right, #cfe0d9 0%, #edf6f2 100%);
    border-bottom: 1px solid #c4d9d2;
    flex-shrink: 0;
}
.drawer-header-info { display: flex; flex-direction: column; gap: 0.25rem; }
.drawer-section-tag {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.68rem; font-weight: 800;
    letter-spacing: 0.06em; text-transform: uppercase;
    padding: 2px 8px; border-radius: 999px; width: fit-content;
}
.drawer-section-tag.operating { color: #166534; background: #dcfce7; }
.drawer-section-tag.investing  { color: #1e4d8c; background: #dbeafe; }
.drawer-section-tag.financing  { color: #5b21b6; background: #ede9fe; }
.drawer-row-name { font-size: 1rem; font-weight: 700; color: #16322e; margin: 0; }
.drawer-period   { font-size: 0.78rem; color: #6b8c85; }

.drawer-close-btn {
    width: 32px; height: 32px; border-radius: 10px;
    border: 1px solid #c4d9d2; background: #fff;
    color: #16322e; font-size: 1.1rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; flex-shrink: 0; transition: background 0.12s;
}
.drawer-close-btn:hover { background: #edf5f2; }

/* ── Drawer filter bar ──────────────────────────────────────── */
.drawer-filter-bar {
    padding: 0.6rem 1.5rem;
    background: #f9fcfb; border-bottom: 1px solid #e4eeea;
    flex-shrink: 0;
}
.drawer-filter-fields { display: flex; align-items: center; gap: 0.5rem; }
.drawer-search-wrap {
    position: relative; display: flex; align-items: center;
}
.drawer-search-icon {
    position: absolute; left: 0.5rem;
    color: #9ab8af; font-size: 0.9rem; pointer-events: none;
}
.drawer-search-input {
    width: 100%; height: 30px;
    padding: 0 1.8rem 0 1.8rem;
    border: 1px solid #c4d9d2; border-radius: 7px;
    font-size: 0.78rem; color: #1e3530; background: #fff; outline: none;
}
.drawer-search-input:focus { border-color: #3d8d7a; }
.drawer-search-clear {
    position: absolute; right: 0.4rem;
    background: none; border: none; padding: 0;
    color: #9ab8af; cursor: pointer; font-size: 0.9rem;
    display: flex; align-items: center;
}
.drawer-search-clear:hover { color: #527267; }

/* ── Drawer body ────────────────────────────────────────────── */
.drawer-body { flex: 1 1 auto; overflow-y: auto; display: flex; flex-direction: column; }

/* ── Balance rows ───────────────────────────────────────────── */
.drawer-balance-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.7rem 1.5rem; font-size: 0.84rem; font-weight: 600;
}
.drawer-balance-row.opening { background: #f4f9f7; border-bottom: 1px solid #e4eeea; }
.drawer-balance-row.closing { background: #f4f9f7; border-top: 2px solid #b8d9cc; }
.dbal-label { color: #527267; }
.dbal-value { font-family: 'Courier New', monospace; color: #1e3530; }

.drawer-table-wrap { flex: 1; overflow-x: auto; }
.drawer-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.drawer-table thead th {
    position: sticky; top: 0;
    background: #edf5f2; color: #527267;
    font-size: 0.67rem; font-weight: 700; text-transform: uppercase;
    padding: 0.45rem 0.75rem; white-space: nowrap;
    border-bottom: 2px solid #d5eae2;
}
.drawer-table tbody td { padding: 0.45rem 0.75rem; border-bottom: 1px solid #f0f7f4; vertical-align: middle; }
.drawer-line-row:hover { background: #fafcfb; }
.drawer-jnum  { font-size: 0.72rem; color: #3d8d7a; font-weight: 700; }
.drawer-desc  { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #527267; }
.drawer-num   { font-family: 'Courier New', monospace; white-space: nowrap; }
.debit-col    { color: #1e4d8c; }
.credit-col   { color: #7c2d12; }

.drawer-empty {
    text-align: center; color: #9ab8af;
    padding: 2rem !important; font-size: 0.84rem;
}
.drawer-empty i { display: block; font-size: 1.5rem; margin-bottom: 0.4rem; }

/* ── Drawer summary ─────────────────────────────────────────── */
.drawer-summary {
    display: flex; gap: 0; border-top: 2px solid #daeee6;
    background: #f7fbfa; flex-shrink: 0;
}
.drawer-summary-item {
    flex: 1; display: flex; flex-direction: column; gap: 0.2rem;
    padding: 0.85rem 1.5rem; border-right: 1px solid #e4eeea;
}
.drawer-summary-item:last-child { border-right: none; }
.ds-label { font-size: 0.67rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #7a9e93; }
.ds-value  { font-size: 0.92rem; font-weight: 700; font-family: 'Courier New', monospace; }
.inflow-col  { color: #166534; }
.outflow-col { color: #991b1b; }
</style>
