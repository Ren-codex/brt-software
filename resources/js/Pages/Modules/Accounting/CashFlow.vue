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
            Accounting tables are not ready yet. Run the accounting migrations to populate cash flow data.
        </div>

        <!-- Main panel -->
        <div v-else class="library-card">
            <div class="library-card-header">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
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
            </div>

            <div class="library-card-body">
                <div class="cf-body">

                    <!-- Activity sections -->
                    <div v-for="section in sections" :key="section.id" class="cf-section">
                        <div class="cf-section-label" :class="section.id">
                            <i :class="section.icon"></i>
                            {{ section.title }}
                            <span class="cf-section-desc">{{ section.description }}</span>
                        </div>

                        <table class="table cf-table mb-0">
                            <tbody>
                                <tr v-if="section.rows.length === 0">
                                    <td colspan="3" class="text-muted fst-italic py-2 ps-3" style="font-size:0.83rem">
                                        No {{ section.title.toLowerCase() }} entries for this period.
                                    </td>
                                </tr>
                                <tr v-for="(row, i) in section.rows" :key="i">
                                    <td class="cf-row-label">
                                        {{ row.label }}
                                        <span v-if="row.note" class="cf-row-note">{{ row.note }}</span>
                                    </td>
                                    <td class="cf-row-entries text-muted">
                                        <span v-if="row.entries">{{ row.entries }} {{ row.entries === 1 ? 'entry' : 'entries' }}</span>
                                    </td>
                                    <td class="cf-row-amount text-end" :class="row.direction">
                                        <span v-if="row.direction === 'outflow'" class="cf-paren">(</span>{{ row.amount }}<span v-if="row.direction === 'outflow'" class="cf-paren">)</span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="cf-subtotal">
                                    <th colspan="2" class="text-end">Net {{ section.title }}</th>
                                    <th class="text-end" :class="section.net_raw >= 0 ? 'text-inflow' : 'text-outflow'">
                                        <span v-if="section.net_raw < 0">(</span>{{ section.net }}<span v-if="section.net_raw < 0">)</span>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Net Change in Cash -->
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
        };
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
        exportUrl(format) {
            const params = new URLSearchParams({ option: format });
            if (this.filters?.date_from) params.set('date_from', this.filters.date_from);
            if (this.filters?.date_to)   params.set('date_to',   this.filters.date_to);
            return '/accounting/cash-flow?' + params.toString();
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
.net-chip.positive { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.net-chip.negative { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

.cf-body { display: flex; flex-direction: column; gap: 0.75rem; }

.cf-section {
    border: 1px solid #e8f0ed;
    border-radius: 14px;
    overflow: hidden;
}

.cf-section-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.9rem;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.cf-section-label.operating { background: #f0fdf4; color: #166534; border-bottom: 1px solid #d1fae5; }
.cf-section-label.investing  { background: #eff6ff; color: #1e4d8c; border-bottom: 1px solid #bfdbfe; }
.cf-section-label.financing  { background: #f5f3ff; color: #5b21b6; border-bottom: 1px solid #ddd6fe; }

.cf-section-desc {
    font-size: 0.68rem;
    font-weight: 400;
    letter-spacing: 0;
    text-transform: none;
    opacity: 0.75;
    margin-left: 0.25rem;
}

.cf-table tbody td { padding: 0.55rem 0.9rem; font-size: 0.86rem; color: #2d3748; border-color: #f3f8f6; }
.cf-table tfoot .cf-subtotal th {
    background: #f4faf8;
    padding: 0.6rem 0.9rem;
    font-size: 0.82rem;
    color: #335c52;
    border-top: 1px solid #dceee8;
}

.cf-row-label { width: 55%; }
.cf-row-entries { width: 20%; font-size: 0.75rem !important; }
.cf-row-amount { width: 25%; font-weight: 600; }
.cf-row-note {
    display: block;
    font-size: 0.72rem;
    color: #94a3b8;
    font-weight: 400;
    margin-top: 1px;
}
.cf-paren { font-size: 0.85em; color: inherit; }

.inflow  { color: #166534; }
.outflow { color: #991b1b; }
.text-inflow  { color: #166534; }
.text-outflow { color: #991b1b; }

/* Net Change box */
.cf-net-change {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 14px;
    flex-wrap: wrap;
}
.cf-net-change.positive { background: #f0fdf4; border: 2px solid #86efac; }
.cf-net-change.negative { background: #fef2f2; border: 2px solid #fca5a5; }

.cf-net-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.cf-net-left > i { font-size: 1.6rem; }
.cf-net-change.positive .cf-net-left > i { color: #16a34a; }
.cf-net-change.negative .cf-net-left > i { color: #dc2626; }

.cf-net-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
.cf-net-value { font-size: 1.15rem; font-weight: 800; color: #1a2e29; }

.cf-net-breakdown {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.cf-net-item { display: flex; flex-direction: column; align-items: center; }
.cf-net-item-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: #9ca3af; }
.cf-net-item-value { font-size: 0.82rem; font-weight: 700; }
.cf-net-sep { font-size: 1rem; color: #9ca3af; font-weight: 700; }

/* Export dropdown */
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
