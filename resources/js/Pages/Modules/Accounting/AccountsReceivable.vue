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
                                    <label class="acct-filter-label">As Of Date</label>
                                    <input v-model="asOf" type="date" class="acct-filter-input" />
                                </div>
                                <div class="acct-filter-field">
                                    <label class="acct-filter-label">Customer</label>
                                    <input v-model="keyword" type="text" class="acct-filter-input acct-filter-input-wide" placeholder="Search customer..." />
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
                        AR invoice tables are not ready yet.
                    </div>

                    <template v-else>
                        <!-- Aging Summary by Customer -->
                        <div class="library-card mb-3">
                            <div class="library-card-header">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="header-icon"><i class="ri-user-received-line"></i></div>
                                        <div>
                                            <h4 class="header-title mb-0">Aging Summary by Customer</h4>
                                            <p class="header-subtitle mb-0">Outstanding balances split by how many days past due.</p>
                                        </div>
                                    </div>
                                    <span class="info-chip">As of {{ filters.as_of }}</span>
                                </div>
                            </div>
                            <div class="library-card-body p-0">
                                <div v-if="agingRows.length === 0" class="empty-state">
                                    <i class="ri-checkbox-circle-line"></i>
                                    <p class="mb-1">No outstanding invoices</p>
                                    <small>All invoices are fully paid or no invoices exist yet.</small>
                                </div>
                                <div v-else class="table-responsive">
                                    <table class="table ar-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th class="text-center">Invoices</th>
                                                <th class="text-end bucket-current">Current</th>
                                                <th class="text-end bucket-mild">1–30 days</th>
                                                <th class="text-end bucket-moderate">31–60 days</th>
                                                <th class="text-end bucket-serious">61–90 days</th>
                                                <th class="text-end bucket-critical">90+ days</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="row in agingRows" :key="row.customer_id" :class="{ 'has-overdue': row.has_overdue }">
                                                <td class="fw-semibold">{{ row.customer_name }}</td>
                                                <td class="text-center">
                                                    <span class="invoice-count-chip">{{ row.invoice_count }}</span>
                                                </td>
                                                <td class="text-end">{{ row.current }}</td>
                                                <td class="text-end">{{ row.days_1_30 }}</td>
                                                <td class="text-end">{{ row.days_31_60 }}</td>
                                                <td class="text-end">{{ row.days_61_90 }}</td>
                                                <td class="text-end">{{ row.days_90_plus }}</td>
                                                <td class="text-end fw-bold">{{ row.total_outstanding }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Invoice Detail -->
                        <div class="library-card">
                            <div class="library-card-header">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="header-icon"><i class="ri-file-list-3-line"></i></div>
                                        <div>
                                            <h4 class="header-title mb-0">Invoice Detail</h4>
                                            <p class="header-subtitle mb-0">All open invoices ordered by most overdue first.</p>
                                        </div>
                                    </div>
                                    <span class="info-chip">{{ invoiceRows.length }} open invoices</span>
                                </div>
                            </div>
                            <div class="library-card-body p-0">
                                <div v-if="invoiceRows.length === 0" class="empty-state">
                                    <i class="ri-checkbox-circle-line"></i>
                                    <p class="mb-1">No open invoices</p>
                                </div>
                                <div v-else class="table-responsive">
                                    <table class="table ar-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Invoice #</th>
                                                <th>Customer</th>
                                                <th>Invoice Date</th>
                                                <th>Due Date</th>
                                                <th class="text-end">Paid</th>
                                                <th class="text-end">Balance Due</th>
                                                <th class="text-center">Aging</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="inv in invoiceRows" :key="inv.id">
                                                <td class="font-monospace">{{ inv.invoice_number }}</td>
                                                <td>{{ inv.customer_name }}</td>
                                                <td>{{ inv.invoice_date }}</td>
                                                <td>{{ inv.due_date || '—' }}</td>
                                                <td class="text-end">{{ inv.amount_paid }}</td>
                                                <td class="text-end fw-bold">{{ inv.balance_due }}</td>
                                                <td class="text-center">
                                                    <span class="aging-chip" :class="'bucket-' + inv.aging_bucket.replace('+', 'plus')">
                                                        {{ agingLabel(inv) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>

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
        agingRows: { type: Array, default: () => [] },
        invoiceRows: { type: Array, default: () => [] },
        filters: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            asOf: this.filters?.as_of || '',
            keyword: this.filters?.keyword || '',
        };
    },
    methods: {
        applyFilter() {
            router.get('/accounting/accounts-receivable', {
                as_of: this.asOf || undefined,
                keyword: this.keyword || undefined,
            }, { preserveScroll: true });
        },
        clearFilter() {
            this.asOf = '';
            this.keyword = '';
            router.get('/accounting/accounts-receivable', {}, { preserveScroll: true });
        },
        agingLabel(inv) {
            if (inv.aging_bucket === 'current') return 'Current';
            return `${inv.days_overdue}d overdue`;
        },
    },
};
</script>

<style scoped>
.info-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    background: #f0f5f3;
    color: #48665c;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1px solid #d1e4dc;
    white-space: nowrap;
}

.ar-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}
.ar-table tbody tr.has-overdue { background: #fffbf5; }
.ar-table tbody td { font-size: 0.85rem; vertical-align: middle; }

.bucket-current  { color: #166534; }
.bucket-mild     { color: #854d0e; }
.bucket-moderate { color: #9a3412; }
.bucket-serious  { color: #7f1d1d; }
.bucket-critical { color: #5b0f0f; }

.invoice-count-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    padding: 2px 6px;
    border-radius: 999px;
    background: #edf5f2;
    color: #3d6b5f;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid #c8e3da;
}

.aging-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
}
.aging-chip.bucket-current { background: #dcfce7; color: #166534; }
.aging-chip.bucket-1-30    { background: #fef3c7; color: #92400e; }
.aging-chip.bucket-31-60   { background: #fed7aa; color: #9a3412; }
.aging-chip.bucket-61-90   { background: #fee2e2; color: #991b1b; }
.aging-chip.bucket-90plus  { background: #fce7f3; color: #831843; }

.empty-state {
    padding: 1.5rem;
    text-align: center;
    color: #648b74;
}
.empty-state i {
    font-size: 2rem;
    color: #3d8d7a;
    display: block;
    margin-bottom: 0.5rem;
}
</style>
