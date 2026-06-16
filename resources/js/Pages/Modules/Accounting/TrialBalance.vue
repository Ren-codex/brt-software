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

        <!-- Filter bar -->
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

        <!-- Not ready -->
        <div v-if="!dataReady" class="acct-empty-notice">
            <i class="ri-information-line"></i>
            Accounting tables are not ready yet. Run the accounting migrations to populate trial balance data.
        </div>

        <!-- Main panel -->
        <div v-else class="library-card">
            <div class="library-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon"><i class="ri-scales-3-line"></i></div>
                    <div>
                        <h4 class="header-title mb-0">Trial Balance</h4>
                        <p class="header-subtitle mb-0">
                            {{ filters.date_from || filters.date_to
                                ? (filters.date_from || '—') + ' to ' + (filters.date_to || '—')
                                : 'All periods' }}
                        </p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="balance-chip" :class="totals.is_balanced ? 'balanced' : 'unbalanced'">
                        <i :class="totals.is_balanced ? 'ri-checkbox-circle-line' : 'ri-alert-line'"></i>
                        {{ totals.is_balanced ? 'Balanced' : 'Unbalanced' }}
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
                <div class="table-responsive">
                    <table class="table report-table mb-0">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="section in sections" :key="section.type">

                                <!-- Section header -->
                                <tr class="group-header-row">
                                    <td colspan="3">
                                        <span class="group-type-label" :class="section.type">{{ section.label }}</span>
                                    </td>
                                </tr>

                                <!-- Account rows -->
                                <tr v-for="row in section.rows" :key="row.id"
                                    class="account-row"
                                    @click="openDrawer(row)"
                                    title="Click to view transactions">
                                    <td>
                                        <span class="acc-code">{{ row.code }}</span>
                                        {{ row.name }}
                                        <i class="ri-arrow-right-s-line row-arrow"></i>
                                    </td>
                                    <td class="text-end tb-amount">
                                        {{ row.tb_debit > 0 ? fmt(row.tb_debit) : '' }}
                                    </td>
                                    <td class="text-end tb-amount">
                                        {{ row.tb_credit > 0 ? fmt(row.tb_credit) : '' }}
                                    </td>
                                </tr>

                                <!-- Section subtotal -->
                                <tr class="group-subtotal-row">
                                    <td class="text-end">
                                        <small>Total {{ section.label }}</small>
                                    </td>
                                    <td class="text-end fw-bold">{{ fmt(section.totalDebit) }}</td>
                                    <td class="text-end fw-bold">{{ fmt(section.totalCredit) }}</td>
                                </tr>

                            </template>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Grand Total</th>
                                <th class="text-end">{{ totals.debits }}</th>
                                <th class="text-end">
                                    <span :class="totals.is_balanced ? 'text-balanced' : 'text-unbalanced'">
                                        {{ totals.credits }}
                                    </span>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Drawer overlay ───────────────────────────────────── -->
        <div v-if="drawer.open" class="drawer-overlay" @click="closeDrawer"></div>

        <!-- ── Drawer panel ─────────────────────────────────────── -->
        <div class="drawer-panel" :class="{ 'drawer-open': drawer.open }">

            <!-- Drawer header -->
            <div class="drawer-header">
                <div class="drawer-header-info">
                    <span class="drawer-acc-code">{{ drawer.account?.code }}</span>
                    <h6 class="drawer-acc-name">{{ drawer.account?.name }}</h6>
                    <p class="drawer-period mb-0">
                        {{ drawer.dateFrom || drawer.dateTo
                            ? (drawer.dateFrom || '—') + ' to ' + (drawer.dateTo || '—')
                            : 'All periods' }}
                    </p>
                </div>
                <button class="drawer-close-btn" @click="closeDrawer">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <!-- Drawer filter bar -->
            <div class="drawer-filter-bar">
                <div class="drawer-filter-fields">
                    <DrawerDateRangePicker
                        v-model:dateFrom="drawer.dateFrom"
                        v-model:dateTo="drawer.dateTo"
                    />
                </div>
                <div class="drawer-search-wrap">
                    <i class="ri-search-line drawer-search-icon"></i>
                    <input v-model="drawer.search" type="text" class="drawer-search-input" placeholder="Search journal, description…" />
                    <button v-if="drawer.search" class="drawer-search-clear" @click="drawer.search = ''">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>

            <!-- Drawer body -->
            <div class="drawer-body">

                <!-- Loading -->
                <div v-if="drawer.loading" class="drawer-loading">
                    <i class="ri-loader-4-line spin"></i>
                    Loading transactions…
                </div>

                <template v-else>

                    <!-- Opening balance -->
                    <div class="drawer-balance-row opening">
                        <span class="dbal-label">Opening Balance</span>
                        <span class="dbal-value">{{ drawer.data.opening_balance }}</span>
                    </div>

                    <!-- Lines table -->
                    <div class="drawer-table-wrap">
                        <table class="drawer-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Journal #</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="filteredDrawerLines.length === 0">
                                    <td colspan="6" class="drawer-empty">
                                        <i class="ri-inbox-line"></i>
                                        No transactions found.
                                    </td>
                                </tr>
                                <tr v-for="line in filteredDrawerLines" :key="line.id" class="drawer-line-row">
                                    <td class="text-nowrap">{{ line.entry_date }}</td>
                                    <td class="font-monospace drawer-jnum">{{ line.journal_number }}</td>
                                    <td class="drawer-desc">{{ line.description }}</td>
                                    <td class="text-end drawer-num debit-col">
                                        {{ line.line_type === 'debit' ? line.amount : '' }}
                                    </td>
                                    <td class="text-end drawer-num credit-col">
                                        {{ line.line_type === 'credit' ? line.amount : '' }}
                                    </td>
                                    <td class="text-end drawer-num bal-col">{{ line.running_balance }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Closing balance -->
                    <div class="drawer-balance-row closing">
                        <span class="dbal-label">Closing Balance</span>
                        <span class="dbal-value">{{ drawer.data.totals?.closing_balance }}</span>
                    </div>

                    <!-- Period summary -->
                    <div v-if="drawer.data.totals" class="drawer-summary">
                        <div class="drawer-summary-item">
                            <span class="ds-label">Period Debits</span>
                            <span class="ds-value debit-col">{{ drawer.data.totals.period_debits }}</span>
                        </div>
                        <div class="drawer-summary-item">
                            <span class="ds-label">Period Credits</span>
                            <span class="ds-value credit-col">{{ drawer.data.totals.period_credits }}</span>
                        </div>
                        <div class="drawer-summary-item">
                            <span class="ds-label">Net Change</span>
                            <span class="ds-value" :class="drawer.data.totals.net_change_raw >= 0 ? 'debit-col' : 'credit-col'">
                                {{ drawer.data.totals.net_change }}
                            </span>
                        </div>
                    </div>

                </template>
            </div>
        </div>

    </div>
</template>

<script>
import axios from 'axios';
import { router } from "@inertiajs/vue3";
import MainLayout from "@/Shared/Layouts/Main.vue";
import AccountingLayout from "@/Pages/Modules/Accounting/AccountingLayout.vue";
import DrawerDateRangePicker from "@/Pages/Modules/Accounting/Components/DrawerDateRangePicker.vue";

const SECTION_ORDER = [
    { type: 'asset',     label: 'Assets' },
    { type: 'liability', label: 'Liabilities' },
    { type: 'equity',    label: 'Equity' },
    { type: 'revenue',   label: 'Income' },
    { type: 'expense',   label: 'Expenses' },
];

export default {
    layout: [MainLayout, AccountingLayout],
    components: { DrawerDateRangePicker },
    props: {
        stats:        Object,
        dataReady:    Boolean,
        summaryCards: Array,
        rows:         Array,
        totals:       Object,
        filters:      { type: Object, default: () => ({}) },
    },
    data() {
        return {
            dateFrom:   this.filters?.date_from || '',
            dateTo:     this.filters?.date_to   || '',
            exportOpen: false,
            drawer: {
                open:     false,
                loading:  false,
                account:  null,
                dateFrom: '',
                dateTo:   '',
                search:   '',
                _init:    false,
                data: { opening_balance: '—', opening_balance_raw: 0, lines: [], totals: null },
            },
        };
    },
    watch: {
        dateTo()           { if (this.dateTo) this.applyFilter(); },
        'drawer.open'(val) { document.body.style.overflow = val ? 'hidden' : ''; },
        'drawer.dateTo'()  { if (this.drawer.open && this.drawer.account && !this.drawer._init) this.applyDrawerFilter(); },
    },
    computed: {
        filteredDrawerLines() {
            const lines = this.drawer.data.lines || [];
            if (!this.drawer.search) return lines;
            const q = this.drawer.search.toLowerCase();
            return lines.filter(l =>
                (l.journal_number || '').toLowerCase().includes(q) ||
                (l.description    || '').toLowerCase().includes(q) ||
                (l.entry_date     || '').includes(q) ||
                String(l.amount   || '').includes(q)
            );
        },
        sections() {
            return SECTION_ORDER
                .map(({ type, label }) => {
                    const rows = (this.rows || [])
                        .filter(r => r.type === type)
                        .map(r => ({
                            ...r,
                            tb_debit:  r.balance > 0 ? r.balance          : 0,
                            tb_credit: r.balance < 0 ? Math.abs(r.balance) : 0,
                        }));
                    if (!rows.length) return null;
                    return {
                        type,
                        label,
                        rows,
                        totalDebit:  rows.reduce((s, r) => s + r.tb_debit,  0),
                        totalCredit: rows.reduce((s, r) => s + r.tb_credit, 0),
                    };
                })
                .filter(Boolean);
        },
    },
    methods: {
        applyFilter() {
            router.get('/accounting/trial-balance', {
                date_from: this.dateFrom || undefined,
                date_to:   this.dateTo   || undefined,
            }, { preserveScroll: true });
        },
        clearFilter() {
            this.dateFrom = '';
            this.dateTo   = '';
            router.get('/accounting/trial-balance', {}, { preserveScroll: true });
        },
        exportUrl(format) {
            const params = new URLSearchParams({ option: format });
            if (this.filters?.date_from) params.set('date_from', this.filters.date_from);
            if (this.filters?.date_to)   params.set('date_to',   this.filters.date_to);
            return '/accounting/trial-balance?' + params.toString();
        },
        fmt(value) {
            if (!value) return '';
            return 'P ' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        openDrawer(row) {
            this.drawer._init    = true;
            this.drawer.account  = { id: row.id, code: row.code, name: row.name };
            this.drawer.open     = true;
            this.drawer.loading  = true;
            this.drawer.search   = '';
            this.drawer.dateFrom = this.filters?.date_from || '';
            this.drawer.dateTo   = this.filters?.date_to   || '';
            this.drawer.data     = { opening_balance: '—', opening_balance_raw: 0, lines: [], totals: null };
            this.$nextTick(() => { this.drawer._init = false; });
            this.fetchDrawerData();
        },
        fetchDrawerData() {
            const params = { option: 'account_lines', account_id: this.drawer.account.id };
            if (this.drawer.dateFrom) params.date_from = this.drawer.dateFrom;
            if (this.drawer.dateTo)   params.date_to   = this.drawer.dateTo;

            axios.get('/accounting/general-ledger', { params })
                .then(({ data }) => { this.drawer.data = data; })
                .catch(() => {})
                .finally(() => { this.drawer.loading = false; });
        },
        applyDrawerFilter() {
            this.drawer.loading = true;
            this.drawer.search  = '';
            this.drawer.data    = { opening_balance: '—', opening_balance_raw: 0, lines: [], totals: null };
            this.fetchDrawerData();
        },
        closeDrawer() {
            this.drawer.open = false;
        },
    },
};
</script>

<style scoped>
/* ── Existing styles ─────────────────────────────── */
.balance-chip {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.3rem 0.8rem; border-radius: 999px;
    font-size: 0.78rem; font-weight: 700;
}
.balance-chip.balanced   { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.balance-chip.unbalanced { background: #fff3cd; color: #92400e; border: 1px solid #fcd34d; }

.report-table thead th,
.report-table tfoot th {
    background: #edf5f2; color: #527267;
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    padding: 0.6rem 0.85rem;
}
.report-table tbody td { font-size: 0.84rem; padding: 0.55rem 0.85rem; vertical-align: middle; }

.group-header-row td {
    background: #f4f9f7; padding: 0.45rem 0.85rem;
    border-top: 2px solid #daeee6;
}
.group-type-label {
    font-size: 0.72rem; font-weight: 800;
    letter-spacing: 0.06em; text-transform: uppercase;
    padding: 2px 10px; border-radius: 999px;
}
.group-type-label.asset     { color: #1e4d8c; background: #dbeafe; }
.group-type-label.liability { color: #7c2d12; background: #fee2e2; }
.group-type-label.equity    { color: #5b21b6; background: #ede9fe; }
.group-type-label.revenue   { color: #166534; background: #dcfce7; }
.group-type-label.expense   { color: #92400e; background: #fef3c7; }

.account-row { cursor: pointer; transition: background 0.12s; }
.account-row:hover { background: #f0faf6; }
.account-row:hover .row-arrow { opacity: 1; transform: translateX(3px); }

.row-arrow {
    font-size: 0.9rem; color: #3d8d7a;
    opacity: 0; transition: opacity 0.15s, transform 0.15s;
    vertical-align: middle;
}

.acc-code {
    font-family: monospace; font-size: 0.72rem;
    color: #9ab8af; margin-right: 0.4rem;
}

.tb-amount { font-family: 'Courier New', monospace; white-space: nowrap; color: #1e3530; }

.group-subtotal-row td {
    background: #f9fcfb; border-top: 1px dashed #c9e0d8;
    font-size: 0.8rem; color: #4a6b62; padding: 0.4rem 0.85rem;
}

.text-balanced   { color: #166534; font-weight: 700; }
.text-unbalanced { color: #b45309; font-weight: 700; }

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

/* ── Drawer overlay ──────────────────────────────── */
.drawer-overlay {
    position: fixed; inset: 0; z-index: 1040;
    background: rgba(10, 28, 25, 0.35);
    backdrop-filter: blur(2px);
    animation: fadeIn 0.2s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* ── Drawer panel ────────────────────────────────── */
.drawer-panel {
    position: fixed; top: 0; right: 0; bottom: 0; z-index: 1050;
    width: 680px; max-width: 95vw;
    background: #fff;
    box-shadow: -6px 0 32px rgba(10,28,25,0.14);
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}
.drawer-panel.drawer-open { transform: translateX(0); }

/* ── Drawer header ───────────────────────────────── */
.drawer-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(to right, #cfe0d9 0%, #edf6f2 100%);
    border-bottom: 1px solid #c4d9d2;
    flex-shrink: 0;
}
.drawer-header-info { display: flex; flex-direction: column; gap: 0.1rem; }
.drawer-acc-code {
    font-family: monospace; font-size: 0.72rem;
    color: #6b8c85; font-weight: 600; letter-spacing: 0.05em;
}
.drawer-acc-name { font-size: 1rem; font-weight: 700; color: #16322e; margin: 0; }
.drawer-period   { font-size: 0.78rem; color: #6b8c85; }

.drawer-close-btn {
    width: 32px; height: 32px; border-radius: 10px;
    border: 1px solid #c4d9d2; background: #fff;
    color: #16322e; font-size: 1.1rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; flex-shrink: 0;
    transition: background 0.12s;
}
.drawer-close-btn:hover { background: #edf5f2; }

/* ── Drawer body ─────────────────────────────────── */
.drawer-body {
    flex: 1 1 auto; overflow-y: auto;
    padding: 0;
    display: flex; flex-direction: column;
}

/* ── Loading ─────────────────────────────────────── */
.drawer-loading {
    flex: 1; display: flex; align-items: center; justify-content: center;
    gap: 0.6rem; color: #6b8c85; font-size: 0.88rem;
}
.spin { animation: spin 0.7s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Balance rows ────────────────────────────────── */
.drawer-balance-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.7rem 1.5rem; font-size: 0.84rem; font-weight: 600;
}
.drawer-balance-row.opening { background: #f4f9f7; border-bottom: 1px solid #e4eeea; }
.drawer-balance-row.closing { background: #f4f9f7; border-top: 2px solid #b8d9cc; }
.dbal-label { color: #527267; }
.dbal-value { font-family: 'Courier New', monospace; color: #1e3530; }

/* ── Lines table ─────────────────────────────────── */
.drawer-table-wrap { flex: 1; overflow-x: auto; }
.drawer-table {
    width: 100%; border-collapse: collapse;
    font-size: 0.78rem;
}
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
.drawer-desc  { max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #527267; }
.drawer-num   { font-family: 'Courier New', monospace; white-space: nowrap; }
.debit-col    { color: #1e4d8c; }
.credit-col   { color: #7c2d12; }
.bal-col      { color: #1e3530; font-weight: 600; }

.drawer-empty {
    text-align: center; color: #9ab8af;
    padding: 2rem !important; font-size: 0.84rem;
}
.drawer-empty i { display: block; font-size: 1.5rem; margin-bottom: 0.4rem; }

/* ── Period summary ──────────────────────────────── */
.drawer-summary {
    display: flex; gap: 0; border-top: 2px solid #daeee6;
    background: #f7fbfa; flex-shrink: 0;
}
.drawer-summary-item {
    flex: 1; display: flex; flex-direction: column; gap: 0.2rem;
    padding: 0.85rem 1.5rem;
    border-right: 1px solid #e4eeea;
}
.drawer-summary-item:last-child { border-right: none; }
.ds-label { font-size: 0.67rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #7a9e93; }
.ds-value { font-size: 0.92rem; font-weight: 700; font-family: 'Courier New', monospace; }

/* ── Drawer filter bar ───────────────────────────── */
.drawer-filter-bar {
    display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
    padding: 0.6rem 1.5rem;
    background: #f9fcfb; border-bottom: 1px solid #e4eeea;
    flex-shrink: 0;
}
.drawer-filter-fields { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; }
.drawer-search-wrap {
    position: relative; flex: 1; min-width: 160px;
    display: flex; align-items: center;
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
</style>
