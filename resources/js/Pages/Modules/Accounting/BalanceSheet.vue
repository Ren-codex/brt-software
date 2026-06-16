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
                        Accounting tables are not ready yet. Run the accounting migrations to populate the balance sheet.
                    </div>

                    <!-- Main panel -->
                    <div v-else class="library-card">
                        <div class="library-card-header">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="header-icon"><i class="ri-building-line"></i></div>
                                    <div>
                                        <h4 class="header-title mb-0">Statement of Financial Position</h4>
                                        <p class="header-subtitle mb-0">Assets must equal Liabilities + Equity for the books to be in balance.</p>
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
                        <div class="library-card-body">
                            <div class="statement-body">

                                <!-- ASSETS -->
                                <div class="statement-section">
                                    <div class="section-label assets">
                                        <i class="ri-coins-line"></i>
                                        Assets
                                    </div>
                                    <table class="table statement-table mb-0">
                                        <tbody>
                                            <tr v-if="assetAccounts.length === 0">
                                                <td colspan="2" class="text-muted fst-italic py-2 ps-3">No asset accounts posted.</td>
                                            </tr>
                                            <tr v-for="account in assetAccounts" :key="account.id" class="bs-account-row" @click="openDrawer(account)">
                                                <td>
                                                    <span class="acct-code">{{ account.code }}</span>
                                                    {{ account.name }}
                                                </td>
                                                <td class="text-end">
                                                    {{ account.balance_formatted }}
                                                    <i class="ri-arrow-right-s-line row-arrow ms-1"></i>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="subtotal-row">
                                                <th>Total Assets</th>
                                                <th class="text-end">{{ totals.assets }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- LIABILITIES -->
                                <div class="statement-section">
                                    <div class="section-label liabilities">
                                        <i class="ri-secure-payment-line"></i>
                                        Liabilities
                                    </div>
                                    <table class="table statement-table mb-0">
                                        <tbody>
                                            <tr v-if="liabilityAccounts.length === 0">
                                                <td colspan="2" class="text-muted fst-italic py-2 ps-3">No liability accounts posted.</td>
                                            </tr>
                                            <tr v-for="account in liabilityAccounts" :key="account.id" class="bs-account-row" @click="openDrawer(account)">
                                                <td>
                                                    <span class="acct-code">{{ account.code }}</span>
                                                    {{ account.name }}
                                                </td>
                                                <td class="text-end">
                                                    {{ account.balance_formatted }}
                                                    <i class="ri-arrow-right-s-line row-arrow ms-1"></i>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="subtotal-row">
                                                <th>Total Liabilities</th>
                                                <th class="text-end">{{ totals.liabilities }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- EQUITY -->
                                <div class="statement-section">
                                    <div class="section-label equity">
                                        <i class="ri-building-line"></i>
                                        Equity
                                    </div>
                                    <table class="table statement-table mb-0">
                                        <tbody>
                                            <tr v-if="equityAccounts.length === 0">
                                                <td colspan="2" class="text-muted fst-italic py-2 ps-3">No equity accounts posted.</td>
                                            </tr>
                                            <tr v-for="account in equityAccounts" :key="account.id" class="bs-account-row" @click="openDrawer(account)">
                                                <td>
                                                    <span class="acct-code">{{ account.code }}</span>
                                                    {{ account.name }}
                                                </td>
                                                <td class="text-end">
                                                    {{ account.balance_formatted }}
                                                    <i class="ri-arrow-right-s-line row-arrow ms-1"></i>
                                                </td>
                                            </tr>
                                            <tr class="earnings-row">
                                                <td>
                                                    <span class="acct-code">—</span>
                                                    Current Period Earnings
                                                    <span class="earnings-note">net income for selected period</span>
                                                </td>
                                                <td class="text-end">{{ totals.current_period_earnings }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="subtotal-row">
                                                <th>Total Equity</th>
                                                <th class="text-end">{{ totals.total_equity }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- VERIFICATION ROW -->
                                <div class="verification-row" :class="totals.is_balanced ? 'balanced' : 'unbalanced'">
                                    <div class="verification-left">
                                        <i :class="totals.is_balanced ? 'ri-checkbox-circle-fill' : 'ri-alert-fill'"></i>
                                        <div>
                                            <div class="verification-label">Total Assets</div>
                                            <div class="verification-value">{{ totals.assets }}</div>
                                        </div>
                                        <span class="verification-eq">=</span>
                                        <div>
                                            <div class="verification-label">Liabilities + Equity</div>
                                            <div class="verification-value">{{ totals.liabilities_and_equity }}</div>
                                        </div>
                                    </div>
                                    <span class="verification-status">
                                        {{ totals.is_balanced ? 'Books are balanced' : 'Difference detected — check postings' }}
                                    </span>
                                </div>

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

export default {
    layout: [MainLayout, AccountingLayout],
    components: { DrawerDateRangePicker },
    props: {
        stats: Object,
        dataReady: Boolean,
        summaryCards: Array,
        assetAccounts: { type: Array, default: () => [] },
        liabilityAccounts: { type: Array, default: () => [] },
        equityAccounts: { type: Array, default: () => [] },
        totals: Object,
        filters: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            dateFrom: this.filters?.date_from || '',
            dateTo: this.filters?.date_to || '',
            exportOpen: false,
            drawer: {
                open:     false,
                loading:  false,
                account:  null,
                dateFrom: '',
                dateTo:   '',
                search:   '',
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
    },
    methods: {
        openDrawer(account) {
            this.drawer._init    = true;
            this.drawer.account  = { id: account.id, code: account.code, name: account.name };
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
        exportUrl(format) {
            const params = new URLSearchParams({ option: format });
            if (this.filters?.date_from) params.set('date_from', this.filters.date_from);
            if (this.filters?.date_to)   params.set('date_to',   this.filters.date_to);
            return '/accounting/balance-sheet?' + params.toString();
        },
        applyFilter() {
            router.get('/accounting/balance-sheet', {
                date_from: this.dateFrom || undefined,
                date_to: this.dateTo || undefined,
            }, { preserveScroll: true });
        },
        clearFilter() {
            this.dateFrom = '';
            this.dateTo = '';
            router.get('/accounting/balance-sheet', {}, { preserveScroll: true });
        },
    },
};
</script>

<style scoped>
.balance-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.8rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}
.balance-chip.balanced   { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.balance-chip.unbalanced { background: #fff3cd; color: #92400e; border: 1px solid #fcd34d; }

.statement-body { display: flex; flex-direction: column; gap: 0.75rem; }

.statement-section {
    border: 1px solid #e8f0ed;
    border-radius: 14px;
    overflow: hidden;
}

.section-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.9rem;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.section-label.assets      { background: #eff6ff; color: #1e4d8c; border-bottom: 1px solid #bfdbfe; }
.section-label.liabilities { background: #fff7ed; color: #92400e; border-bottom: 1px solid #fed7aa; }
.section-label.equity      { background: #f5f3ff; color: #5b21b6; border-bottom: 1px solid #ddd6fe; }

.statement-table tbody td {
    padding: 0.55rem 0.9rem;
    font-size: 0.86rem;
    color: #2d3748;
    border-color: #f3f8f6;
}
.statement-table tfoot .subtotal-row th {
    background: #f4faf8;
    padding: 0.6rem 0.9rem;
    font-size: 0.82rem;
    color: #335c52;
    border-top: 1px solid #dceee8;
}

.acct-code {
    display: inline-block;
    font-size: 0.72rem;
    color: #94a3b8;
    font-family: monospace;
    margin-right: 0.4rem;
}

.earnings-row td { background: #fafffe; font-style: italic; color: #3d6b5f; }
.earnings-note {
    display: inline-block;
    font-size: 0.7rem;
    color: #94a3b8;
    margin-left: 0.4rem;
    font-style: normal;
}

.verification-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 14px;
    flex-wrap: wrap;
}
.verification-row.balanced   { background: #f0fdf4; border: 2px solid #86efac; }
.verification-row.unbalanced { background: #fef2f2; border: 2px solid #fca5a5; }

.verification-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.verification-left > i { font-size: 1.4rem; }
.verification-row.balanced   .verification-left > i { color: #16a34a; }
.verification-row.unbalanced .verification-left > i { color: #dc2626; }

.verification-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
.verification-value { font-size: 1rem; font-weight: 800; color: #1a2e29; }
.verification-eq    { font-size: 1.4rem; font-weight: 800; color: #9ca3af; }
.verification-status { font-size: 0.82rem; font-weight: 700; }
.verification-row.balanced   .verification-status { color: #166534; }
.verification-row.unbalanced .verification-status { color: #991b1b; }

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

/* ── Clickable account rows ──────────────────────── */
.bs-account-row { cursor: pointer; transition: background 0.12s; }
.bs-account-row:hover { background: #f0faf6; }
.bs-account-row .row-arrow {
    font-size: 0.9rem; color: #3d8d7a;
    opacity: 0; transition: opacity 0.15s, transform 0.15s;
    vertical-align: middle;
}
.bs-account-row:hover .row-arrow { opacity: 1; transform: translateX(3px); }

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
