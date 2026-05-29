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
                        Accounting tables are not ready yet. Run the accounting migrations to populate trial balance data.
                    </div>

                    <!-- Main panel -->
                    <div v-else class="library-card">
                        <div class="library-card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="header-icon"><i class="ri-scales-3-line"></i></div>
                                    <div>
                                        <h4 class="header-title mb-0">Trial Balance</h4>
                                        <p class="header-subtitle mb-0">All accounts with debit and credit activity for the selected period.</p>
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
                        <div class="library-card-body p-0">
                            <div class="table-responsive">
                                <table class="table report-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Account</th>
                                            <th>Type</th>
                                            <th class="text-end">Debit</th>
                                            <th class="text-end">Credit</th>
                                            <th class="text-end">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="(groupRows, groupType) in groupedRows" :key="groupType">
                                            <tr class="group-header-row">
                                                <td colspan="6">
                                                    <span class="group-type-label" :class="groupType">{{ formatLabel(groupType) }}</span>
                                                </td>
                                            </tr>
                                            <tr v-for="row in groupRows" :key="row.id">
                                                <td class="text-muted font-monospace small">{{ row.code }}</td>
                                                <td>{{ row.name }}</td>
                                                <td>
                                                    <span class="type-chip" :class="row.type">{{ formatLabel(row.type) }}</span>
                                                </td>
                                                <td class="text-end">{{ row.debit_total_formatted }}</td>
                                                <td class="text-end">{{ row.credit_total_formatted }}</td>
                                                <td class="text-end fw-semibold">{{ row.balance_formatted }}</td>
                                            </tr>
                                            <tr class="group-subtotal-row">
                                                <td colspan="3" class="text-end">
                                                    <small>{{ formatLabel(groupType) }} subtotal</small>
                                                </td>
                                                <td class="text-end fw-bold">{{ groupSubtotals[groupType].debits }}</td>
                                                <td class="text-end fw-bold">{{ groupSubtotals[groupType].credits }}</td>
                                                <td class="text-end fw-bold">{{ groupSubtotals[groupType].balance }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3">Grand Total</th>
                                            <th class="text-end">{{ totals.debits }}</th>
                                            <th class="text-end">{{ totals.credits }}</th>
                                            <th class="text-end">
                                                <span :class="totals.is_balanced ? 'text-balanced' : 'text-unbalanced'">
                                                    {{ totals.difference }}
                                                </span>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
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
        rows: Array,
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
    computed: {
        groupedRows() {
            const order = ['asset', 'liability', 'equity', 'revenue', 'expense'];
            const result = {};
            for (const type of order) {
                const items = (this.rows || []).filter(r => r.type === type);
                if (items.length > 0) result[type] = items;
            }
            return result;
        },
        groupSubtotals() {
            const result = {};
            for (const [type, items] of Object.entries(this.groupedRows)) {
                result[type] = {
                    debits:  this.fmt(items.reduce((s, r) => s + (r.debit_total || 0), 0)),
                    credits: this.fmt(items.reduce((s, r) => s + (r.credit_total || 0), 0)),
                    balance: this.fmt(items.reduce((s, r) => s + (r.balance || 0), 0)),
                };
            }
            return result;
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
        formatLabel(v) {
            return String(v || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        },
        fmt(value) {
            return 'P ' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    },
};
</script>

<style scoped>
.balance-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.8rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}
.balance-chip.balanced   { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.balance-chip.unbalanced { background: #fff3cd; color: #92400e; border: 1px solid #fcd34d; }

.report-table thead th,
.report-table tfoot th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0.6rem 0.85rem;
}
.report-table tbody td { font-size: 0.84rem; padding: 0.55rem 0.85rem; vertical-align: middle; }

.group-header-row td {
    background: #f4f9f7;
    padding: 0.45rem 0.85rem;
    border-top: 2px solid #daeee6;
}
.group-type-label {
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 2px 10px;
    border-radius: 999px;
}
.group-type-label.asset     { color: #1e4d8c; background: #dbeafe; }
.group-type-label.liability { color: #7c2d12; background: #fee2e2; }
.group-type-label.equity    { color: #5b21b6; background: #ede9fe; }
.group-type-label.revenue   { color: #166534; background: #dcfce7; }
.group-type-label.expense   { color: #92400e; background: #fef3c7; }

.group-subtotal-row td {
    background: #f9fcfb;
    border-top: 1px dashed #c9e0d8;
    font-size: 0.8rem;
    color: #4a6b62;
    padding: 0.4rem 0.85rem;
}

.type-chip {
    display: inline-flex;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 700;
}
.type-chip.asset     { color: #1e4d8c; background: #dbeafe; }
.type-chip.liability { color: #7c2d12; background: #fee2e2; }
.type-chip.equity    { color: #5b21b6; background: #ede9fe; }
.type-chip.revenue   { color: #166534; background: #dcfce7; }
.type-chip.expense   { color: #92400e; background: #fef3c7; }

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
</style>
