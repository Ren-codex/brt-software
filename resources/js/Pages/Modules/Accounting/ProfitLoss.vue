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
                        Accounting tables are not ready yet. Run the accounting migrations to populate the income statement.
                    </div>

                    <!-- Main panel -->
                    <div v-else class="library-card">
                        <div class="library-card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="header-icon"><i class="ri-line-chart-line"></i></div>
                                    <div>
                                        <h4 class="header-title mb-0">Income Statement</h4>
                                        <p class="header-subtitle mb-0">Revenue, cost of sales, and operating expenses for the selected period.</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="net-chip" :class="totals.net_income_raw >= 0 ? 'profit' : 'loss'">
                                        <i :class="totals.net_income_raw >= 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line'"></i>
                                        {{ totals.net_income_raw >= 0 ? 'Profit' : 'Loss' }}
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

                                <!-- REVENUE -->
                                <div class="statement-section">
                                    <div class="section-label revenue">
                                        <i class="ri-funds-line"></i>
                                        Revenue
                                    </div>
                                    <table class="table statement-table mb-0">
                                        <tbody>
                                            <tr v-if="revenueAccounts.length === 0">
                                                <td colspan="2" class="text-muted fst-italic py-2 ps-3">No revenue accounts posted.</td>
                                            </tr>
                                            <tr v-for="account in revenueAccounts" :key="account.id">
                                                <td>
                                                    <span class="acct-code">{{ account.code }}</span>
                                                    {{ account.name }}
                                                </td>
                                                <td class="text-end">{{ account.balance_formatted }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="subtotal-row">
                                                <th>Total Revenue</th>
                                                <th class="text-end">{{ totals.revenue }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- COST OF SALES -->
                                <div class="statement-section">
                                    <div class="section-label cogs">
                                        <i class="ri-shopping-basket-line"></i>
                                        Cost of Sales
                                    </div>
                                    <table class="table statement-table mb-0">
                                        <tbody>
                                            <tr v-if="costOfSalesAccounts.length === 0">
                                                <td colspan="2" class="text-muted fst-italic py-2 ps-3">No cost-of-sales accounts posted.</td>
                                            </tr>
                                            <tr v-for="account in costOfSalesAccounts" :key="account.id">
                                                <td>
                                                    <span class="acct-code">{{ account.code }}</span>
                                                    {{ account.name }}
                                                </td>
                                                <td class="text-end">{{ account.balance_formatted }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="subtotal-row">
                                                <th>Total Cost of Sales</th>
                                                <th class="text-end">{{ totals.cost_of_sales }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- GROSS PROFIT -->
                                <div class="result-row gross-profit">
                                    <span>Gross Profit</span>
                                    <strong :class="totals.gross_profit_raw >= 0 ? 'text-positive' : 'text-negative'">
                                        {{ totals.gross_profit }}
                                    </strong>
                                </div>

                                <!-- OPERATING EXPENSES -->
                                <div class="statement-section">
                                    <div class="section-label opex">
                                        <i class="ri-wallet-line"></i>
                                        Operating Expenses
                                    </div>
                                    <table class="table statement-table mb-0">
                                        <tbody>
                                            <tr v-if="operatingExpenseAccounts.length === 0">
                                                <td colspan="2" class="text-muted fst-italic py-2 ps-3">No operating expense accounts posted.</td>
                                            </tr>
                                            <tr v-for="account in operatingExpenseAccounts" :key="account.id">
                                                <td>
                                                    <span class="acct-code">{{ account.code }}</span>
                                                    {{ account.name }}
                                                </td>
                                                <td class="text-end">{{ account.balance_formatted }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="subtotal-row">
                                                <th>Total Operating Expenses</th>
                                                <th class="text-end">{{ totals.operating_expenses }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- NET INCOME -->
                                <div class="result-row net-income" :class="totals.net_income_raw >= 0 ? 'positive' : 'negative'">
                                    <span>Net Income</span>
                                    <strong>{{ totals.net_income }}</strong>
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
        revenueAccounts: { type: Array, default: () => [] },
        costOfSalesAccounts: { type: Array, default: () => [] },
        operatingExpenseAccounts: { type: Array, default: () => [] },
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
        applyFilter() {
            router.get('/accounting/profit-loss', {
                date_from: this.dateFrom || undefined,
                date_to: this.dateTo || undefined,
            }, { preserveScroll: true });
        },
        clearFilter() {
            this.dateFrom = '';
            this.dateTo = '';
            router.get('/accounting/profit-loss', {}, { preserveScroll: true });
        },
        exportUrl(format) {
            const params = new URLSearchParams({ option: format });
            if (this.filters?.date_from) params.set('date_from', this.filters.date_from);
            if (this.filters?.date_to)   params.set('date_to',   this.filters.date_to);
            return '/accounting/profit-loss?' + params.toString();
        },
    },
};
</script>

<style scoped>
.net-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.8rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}
.net-chip.profit { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.net-chip.loss   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

.statement-body { display: flex; flex-direction: column; gap: 0; }

.statement-section {
    border: 1px solid #e8f0ed;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 0.75rem;
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
.section-label.revenue { background: #f0fdf4; color: #166534; border-bottom: 1px solid #d1fae5; }
.section-label.cogs    { background: #fff7ed; color: #92400e; border-bottom: 1px solid #fed7aa; }
.section-label.opex    { background: #fefce8; color: #854d0e; border-bottom: 1px solid #fef08a; }

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

.result-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    border-radius: 14px;
    margin-bottom: 0.75rem;
}
.result-row.gross-profit { background: #f0fdf4; border: 1px solid #d1fae5; color: #155e2e; }
.result-row.net-income.positive { background: linear-gradient(135deg, #e8fdf2 0%, #f0fdf6 100%); border: 2px solid #86efac; color: #14532d; }
.result-row.net-income.negative { background: linear-gradient(135deg, #fef2f2 0%, #fff5f5 100%); border: 2px solid #fca5a5; color: #7f1d1d; }
.result-row span { font-size: 0.92rem; font-weight: 700; }
.result-row strong { font-size: 1rem; font-weight: 800; }

.text-positive { color: #166534; }
.text-negative { color: #991b1b; }

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
