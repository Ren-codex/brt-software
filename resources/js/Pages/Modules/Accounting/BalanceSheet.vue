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
                        Accounting tables are not ready yet. Run the accounting migrations to populate the balance sheet.
                    </div>

                    <!-- Main panel -->
                    <div v-else class="library-card">
                        <div class="library-card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
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
                                            <tr v-for="account in assetAccounts" :key="account.id">
                                                <td>
                                                    <span class="acct-code">{{ account.code }}</span>
                                                    {{ account.name }}
                                                </td>
                                                <td class="text-end">{{ account.balance_formatted }}</td>
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
                                            <tr v-for="account in liabilityAccounts" :key="account.id">
                                                <td>
                                                    <span class="acct-code">{{ account.code }}</span>
                                                    {{ account.name }}
                                                </td>
                                                <td class="text-end">{{ account.balance_formatted }}</td>
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
                                            <tr v-for="account in equityAccounts" :key="account.id">
                                                <td>
                                                    <span class="acct-code">{{ account.code }}</span>
                                                    {{ account.name }}
                                                </td>
                                                <td class="text-end">{{ account.balance_formatted }}</td>
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

    </div>
</template>

<script>
import { router } from "@inertiajs/vue3";
import MainLayout from "@/Shared/Layouts/Main.vue";
import AccountingLayout from "@/Pages/Modules/Accounting/AccountingLayout.vue";

export default {
    layout: [MainLayout, AccountingLayout],
    components: {},
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
        };
    },
    methods: {
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
</style>
