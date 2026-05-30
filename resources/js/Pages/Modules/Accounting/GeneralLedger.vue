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
                        <label class="acct-filter-label">Date From</label>
                        <input v-model="dateFrom" type="date" class="acct-filter-input" />
                    </div>
                    <div class="acct-filter-field">
                        <label class="acct-filter-label">Date To</label>
                        <input v-model="dateTo" type="date" class="acct-filter-input" />
                    </div>
                    <div class="acct-filter-actions">
                        <button type="button" class="acct-btn-secondary" @click="clearFilter">Clear</button>
                        <button type="button" class="acct-btn-primary" @click="applyFilter">Apply</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Not ready -->
        <div v-if="!dataReady" class="acct-empty-notice">
            <i class="ri-information-line"></i>
            Accounting tables are not ready yet. Run the accounting migrations to populate live ledger data.
        </div>

        <!-- Journal Lines Panel -->
        <div v-else class="library-card">
            <div class="library-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon"><i class="ri-list-check-3"></i></div>
                    <div>
                        <h4 class="header-title mb-0">Journal Lines</h4>
                        <p class="header-subtitle mb-0">All posted transaction lines for the selected period.</p>
                    </div>
                </div>
            </div>
            <div class="library-card-body">

                <!-- Stats bar -->
                <div class="ll-stats-bar">
                    <div class="ll-stat">
                        <span class="ll-stat-label">Total Debits</span>
                        <span class="ll-stat-value debit-color">{{ llStats.total_debits }}</span>
                    </div>
                    <div class="ll-stat-divider"></div>
                    <div class="ll-stat">
                        <span class="ll-stat-label">Total Credits</span>
                        <span class="ll-stat-value credit-color">{{ llStats.total_credits }}</span>
                    </div>
                    <div class="ll-stat-divider"></div>
                    <div class="ll-stat">
                        <span class="ll-stat-label">Net Balance</span>
                        <span class="ll-stat-value" :class="llStats.is_balanced ? 'balanced-color' : 'unbalanced-color'">
                            {{ llStats.is_balanced ? '✓ Balanced' : '✗ Unbalanced' }}
                        </span>
                    </div>
                    <div class="ll-stat-divider"></div>
                    <div class="ll-stat">
                        <span class="ll-stat-label">Journal Entries</span>
                        <span class="ll-stat-value">{{ llStats.entry_count }}</span>
                        <span class="ll-stat-sub">{{ llStats.line_count }} lines</span>
                    </div>
                </div>

                <!-- Filter row -->
                <div class="ll-filter-row">
                    <input
                        v-model="llSearch"
                        type="text"
                        class="ll-search-input"
                        placeholder="Search description, account, journal #…"
                        @keyup.enter="fetchLedgerLines"
                    />
                    <select v-model="llSourceFilter" class="ll-source-select">
                        <option value="all">All Sources</option>
                        <option value="ar">AR</option>
                        <option value="ap">AP</option>
                        <option value="payroll">Payroll</option>
                        <option value="inventory">Inventory</option>
                        <option value="manual">Manual</option>
                    </select>
                    <select v-model="llPerPage" class="ll-source-select" @change="fetchLedgerLines()">
                        <option :value="10">10 / page</option>
                        <option :value="25">25 / page</option>
                        <option :value="50">50 / page</option>
                        <option :value="100">100 / page</option>
                    </select>
                    <button class="ll-search-btn" :disabled="llLoading" @click="fetchLedgerLines">
                        <i v-if="llLoading" class="ri-loader-4-line spin me-1"></i>
                        {{ llLoading ? 'Loading…' : 'Search' }}
                    </button>
                    <button class="ll-clear-btn" @click="clearLedgerFilter">Clear</button>
                </div>

                <!-- Table -->
                <div v-if="llLines.length === 0 && !llLoading" class="empty-state">
                    <i class="ri-file-list-3-line"></i>
                    <p class="mb-0">No journal lines found for this period.</p>
                </div>
                <div v-else class="table-responsive">
                    <table class="table ll-table mb-0">
                        <thead>
                            <tr>
                                <th>Entry ID</th>
                                <th>Date</th>
                                <th>Post Date</th>
                                <th>Account</th>
                                <th>Reference</th>
                                <th>Description</th>
                                <th class="text-center">Source</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th>Posted By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in llLines" :key="line.id">
                                <td class="font-monospace entry-id">{{ line.journal_number }}</td>
                                <td class="text-nowrap">{{ line.entry_date }}</td>
                                <td class="text-nowrap text-muted">{{ line.post_date }}</td>
                                <td>
                                    <div class="account-cell">
                                        <span class="acc-code font-monospace">{{ line.account_code }}</span>
                                        <span class="acc-name">{{ line.account_name }}</span>
                                        <span class="type-chip" :class="line.account_type">{{ formatLabel(line.account_type) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span v-if="line.reference" class="ref-chip">{{ line.reference }}</span>
                                    <span v-else class="text-muted">—</span>
                                </td>
                                <td class="desc-cell text-muted">{{ line.description }}</td>
                                <td class="text-center">
                                    <span class="source-badge" :class="'src-' + line.source_label.toLowerCase()">
                                        {{ line.source_label }}
                                    </span>
                                </td>
                                <td class="text-end fw-semibold debit-color">{{ line.debit || '—' }}</td>
                                <td class="text-end fw-semibold credit-color">{{ line.credit || '—' }}</td>
                                <td class="text-muted small">{{ line.posted_by }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="ll-totals-row">
                                <td colspan="7" class="fw-bold">Period Totals</td>
                                <td class="text-end fw-bold debit-color">{{ llStats.total_debits }}</td>
                                <td class="text-end fw-bold credit-color">{{ llStats.total_credits }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="px-3 pb-3">
                    <Pagination
                        v-if="llMeta && llMeta.last_page > 1"
                        :lists="llLines.length"
                        :links="llLinks"
                        :pagination="llMeta"
                        @fetch="fetchLedgerLines"
                    />
                </div>

            </div>
        </div>

    </div>
</template>

<script>
import axios from "axios";
import { router } from "@inertiajs/vue3";
import MainLayout from "@/Shared/Layouts/Main.vue";
import AccountingLayout from "@/Pages/Modules/Accounting/AccountingLayout.vue";
import Pagination from "@/Shared/Components/Pagination.vue";

export default {
    layout: [MainLayout, AccountingLayout],
    components: { Pagination },
    props: {
        stats:         { type: Object, default: () => ({}) },
        dataReady:     { type: Boolean, default: false },
        summaryCards:  { type: Array,  default: () => [] },
        ledgerLines:   { type: Array,  default: () => [] },
        ledgerStats:   {
            type: Object,
            default: () => ({ total_debits: '—', total_credits: '—', is_balanced: true, entry_count: 0, line_count: 0 }),
        },
        ledgerMeta:    { type: Object, default: () => ({}) },
        ledgerLinks:   { type: Object, default: () => ({}) },
        accountBalances: { type: Array, default: () => [] },
        filters:       { type: Object, default: () => ({}) },
    },
    data() {
        return {
            dateFrom:       this.filters?.date_from || '',
            dateTo:         this.filters?.date_to   || '',
            llLines:        this.ledgerLines,
            llStats:        this.ledgerStats,
            llMeta:         this.ledgerMeta  || {},
            llLinks:        this.ledgerLinks || {},
            llPerPage:      10,
            llSearch:       this.filters?.search        || '',
            llSourceFilter: this.filters?.source_filter || 'all',
            llLoading:      false,
        };
    },
    created() {
        this.fetchLedgerLines();
    },
    methods: {
        applyFilter() {
            router.get('/accounting/general-ledger', {
                date_from: this.dateFrom || undefined,
                date_to:   this.dateTo   || undefined,
            }, { preserveScroll: true });
        },
        clearFilter() {
            this.dateFrom = '';
            this.dateTo   = '';
            router.get('/accounting/general-ledger', {}, { preserveScroll: true });
        },
        formatLabel(value) {
            return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        },
        async fetchLedgerLines(pageUrl) {
            this.llLoading = true;
            try {
                const url = (typeof pageUrl === 'string' && pageUrl)
                    ? pageUrl
                    : '/accounting/general-ledger';
                const params = { option: 'journal_lines', per_page: this.llPerPage };
                if (this.dateFrom)                 params.date_from     = this.dateFrom;
                if (this.dateTo)                   params.date_to       = this.dateTo;
                if (this.llSearch.trim())           params.search        = this.llSearch.trim();
                if (this.llSourceFilter !== 'all') params.source_filter = this.llSourceFilter;
                const { data } = await axios.get(url, { params });
                this.llLines = data.data  || [];
                this.llStats = data.stats || this.llStats;
                this.llMeta  = data.meta  || {};
                this.llLinks = data.links || {};
            } catch {
                // keep existing data on error
            } finally {
                this.llLoading = false;
            }
        },
        clearLedgerFilter() {
            this.llSearch       = '';
            this.llSourceFilter = 'all';
            this.fetchLedgerLines();
        },
    },
};
</script>

<style scoped>
.type-chip {
    display: inline-flex;
    align-items: center;
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 0.62rem;
    font-weight: 700;
}
.type-chip.asset     { color: #1e4d8c; background: #dbeafe; }
.type-chip.liability { color: #7c2d12; background: #fee2e2; }
.type-chip.equity    { color: #5b21b6; background: #ede9fe; }
.type-chip.revenue   { color: #166534; background: #dcfce7; }
.type-chip.expense   { color: #92400e; background: #fef3c7; }

.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.empty-state { padding: 1.5rem; text-align: center; color: #648b74; }
.empty-state i { font-size: 2rem; color: #3d8d7a; display: block; margin-bottom: 0.5rem; }

.desc-cell { max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Journal Lines stats bar */
.ll-stats-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    background: #f0faf6;
    border: 1px solid #c8e3da;
    border-radius: 12px;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.ll-stat          { display: flex; flex-direction: column; gap: 0.1rem; flex: 1; min-width: 120px; }
.ll-stat-divider  { width: 1px; height: 36px; background: #c8e3da; flex-shrink: 0; }
.ll-stat-label    { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #648b74; }
.ll-stat-value    { font-size: 0.95rem; font-weight: 700; color: #20413a; }
.ll-stat-sub      { font-size: 0.65rem; color: #7a9e93; }

.debit-color      { color: #1e4d8c !important; }
.credit-color     { color: #7c2d12 !important; }
.balanced-color   { color: #166534 !important; }
.unbalanced-color { color: #991b1b !important; }

.ll-filter-row { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1rem; flex-wrap: wrap; }

.ll-search-input {
    flex: 1;
    min-width: 200px;
    border: 1px solid #d1e4dc;
    border-radius: 8px;
    padding: 0.42rem 0.75rem;
    font-size: 0.84rem;
    color: #20413a;
    background: #f7fbfa;
    outline: none;
}
.ll-search-input:focus { border-color: #3d8d7a; background: #fff; }

.ll-source-select {
    border: 1px solid #d1e4dc;
    border-radius: 8px;
    padding: 0.42rem 0.65rem;
    font-size: 0.84rem;
    color: #20413a;
    background: #f7fbfa;
    outline: none;
    cursor: pointer;
}

.ll-search-btn {
    padding: 0.42rem 1rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 700;
    background: #3d8d7a;
    color: #fff;
    border: none;
    cursor: pointer;
}
.ll-search-btn:hover    { background: #347a6a; }
.ll-search-btn:disabled { opacity: 0.6; cursor: not-allowed; }

.ll-clear-btn {
    padding: 0.42rem 0.9rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 700;
    background: #f0f5f3;
    color: #527267;
    border: 1px solid #d1e4dc;
    cursor: pointer;
}
.ll-clear-btn:hover { background: #e4eeeb; }

.ll-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.63rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
    padding: 0.4rem 0.5rem;
}
.ll-table tbody td { font-size: 0.75rem; vertical-align: middle; padding: 0.35rem 0.5rem; border-color: #edf5f2; }
.ll-table tbody tr:hover { background: #f7fbf9; }

.entry-id { font-size: 0.72rem; font-weight: 700; color: #3d8d7a; }

.account-cell { display: flex; flex-direction: column; gap: 1px; }
.acc-code     { font-size: 0.62rem; color: #7a9e93; }
.acc-name     { font-size: 0.74rem; font-weight: 600; color: #20413a; }

.ref-chip {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 6px;
    background: #e8f4ee;
    color: #2f6b5c;
    font-size: 0.72rem;
    font-weight: 700;
    font-family: monospace;
    border: 1px solid #c4e0d6;
    white-space: nowrap;
}

.source-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    white-space: nowrap;
}
.src-ar        { background: #dbeafe; color: #1e3a8a; }
.src-ap        { background: #fef3c7; color: #92400e; }
.src-payroll   { background: #ede9fe; color: #5b21b6; }
.src-inventory { background: #dcfce7; color: #166534; }
.src-manual    { background: #f3f4f6; color: #4b5563; }

.ll-totals-row td { background: #e4f0eb; border-top: 2px solid #b8d9cc !important; font-size: 0.75rem; padding: 0.4rem 0.5rem; }
</style>
