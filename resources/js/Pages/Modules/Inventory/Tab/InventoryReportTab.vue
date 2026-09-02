<template>
    <div>
        <div class="library-card">
            <div class="library-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon">
                        <i class="ri-clipboard-line"></i>
                    </div>
                    <div>
                        <h4 class="header-title mb-0">Inventory Position</h4>
                        <p class="header-subtitle mb-0">{{ periodCaption }}</p>
                    </div>
                </div>
                <div class="export-wrap">
                    <button type="button" class="acct-btn-secondary" @click="showExport = !showExport">
                        <i class="ri-download-2-line me-1"></i>Export
                        <i class="ri-arrow-down-s-line ms-1"></i>
                    </button>
                    <div v-if="showExport" class="export-menu">
                        <a :href="exportUrl('excel')" class="export-option" @click="showExport = false">
                            <i class="ri-file-excel-2-line"></i>
                            <span>Excel<small>.xlsx spreadsheet</small></span>
                        </a>
                        <a :href="exportUrl('pdf')" class="export-option" @click="showExport = false">
                            <i class="ri-file-pdf-2-line"></i>
                            <span>PDF<small>printable report</small></span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="library-card-body">
                <!-- Stock value is what the goods cost; retail value is what they
                     would fetch if all sold. Kept apart so neither is mistaken
                     for the other. -->
                <div class="stat-grid">
                    <div class="stat-card accent-green">
                        <span class="stat-label">Stock value (at cost)</span>
                        <strong class="stat-value">{{ peso(report.totals.stock_value) }}</strong>
                        <small class="stat-note">unit cost × quantity</small>
                    </div>
                    <div class="stat-card accent-blue">
                        <span class="stat-label">Retail value</span>
                        <strong class="stat-value">{{ peso(report.totals.retail_value) }}</strong>
                        <small class="stat-note">if all sold at retail</small>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Products / Batches</span>
                        <strong class="stat-value">{{ report.totals.products }} / {{ report.totals.batches }}</strong>
                        <small class="stat-note">{{ number(report.totals.quantity) }} units on hand</small>
                    </div>
                    <div class="stat-card accent-amber">
                        <span class="stat-label">Low stock</span>
                        <strong class="stat-value">{{ report.totals.low_stock }}</strong>
                        <small class="stat-note">at or below minimum</small>
                    </div>
                    <div class="stat-card accent-red">
                        <span class="stat-label">Out of stock</span>
                        <strong class="stat-value">{{ report.totals.out_of_stock }}</strong>
                        <small class="stat-note">nothing left in the batch</small>
                    </div>
                    <div class="stat-card accent-orange">
                        <span class="stat-label">Expiring</span>
                        <strong class="stat-value">{{ report.totals.expiring }}</strong>
                        <small class="stat-note">within 30 days, or past</small>
                    </div>
                </div>

                <div class="period-tabs">
                    <button
                        v-for="p in periods"
                        :key="p.value"
                        type="button"
                        class="period-tab"
                        :class="{ active: filter.period === p.value }"
                        @click="selectPeriod(p.value)"
                    >{{ p.label }}</button>

                    <!-- Which month, quarter, week or year. Hidden for Current,
                         which has nothing to choose between. -->
                    <select
                        v-if="filter.period !== 'current'"
                        v-model="filter.period_value"
                        class="period-select"
                        @change="fetch"
                    >
                        <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>

                <div class="search-section">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="search-wrapper">
                                <i class="ri-search-line search-icon"></i>
                                <input type="text" v-model="filter.keyword" @input="debouncedFetch"
                                    placeholder="Search product or batch..." class="search-input">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select v-model="filter.brand" class="search-input" @change="fetch">
                                <option value="">All brands</option>
                                <option v-for="b in brands" :key="b" :value="b">{{ b }}</option>
                            </select>
                        </div>
                        <div class="col-md-5 d-flex align-items-center gap-3">
                            <label class="report-toggle">
                                <input type="checkbox" v-model="filter.low_stock_only" @change="fetch">
                                <span>Low &amp; out of stock only</span>
                            </label>
                            <label class="report-toggle">
                                <input type="checkbox" v-model="filter.include_empty" @change="fetch">
                                <span>Include empty batches</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table sales-table mb-0">
                        <thead>
                            <tr>
                                <th>Batch</th>
                                <th>Product</th>
                                <th>Brand</th>
                                <th>Source</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Retail</th>
                                <th class="text-end">Stock Value</th>
                                <th>Expires</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="fs-12">
                            <tr v-if="loading">
                                <td colspan="10" class="text-center py-4 text-muted">Loading…</td>
                            </tr>
                            <tr v-else-if="!pagedRows.length">
                                <td colspan="10" class="text-center py-4 text-muted">
                                    No stock matches these filters.
                                </td>
                            </tr>
                            <tr v-else v-for="row in pagedRows" :key="row.id">
                                <td class="font-monospace text-muted">{{ row.batch_code }}</td>
                                <td class="fw-semibold">{{ row.product_name }}</td>
                                <td>{{ row.brand }}</td>
                                <td>
                                    <span class="source-chip" :class="row.is_converted ? 'converted' : 'received'">
                                        {{ row.is_converted ? 'Converted' : 'Received' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    {{ number(row.quantity) }}
                                    <small v-if="row.minimum_stock" class="text-muted">/ {{ row.minimum_stock }}</small>
                                </td>
                                <td class="text-end">{{ peso(row.unit_cost) }}</td>
                                <td class="text-end">{{ peso(row.retail_price) }}</td>
                                <td class="text-end fw-semibold">{{ peso(row.stock_value) }}</td>
                                <td>{{ row.expiration_date || '—' }}</td>
                                <td class="text-center">
                                    <span class="status-chip" :class="row.status">{{ statusLabel(row.status) }}</span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="report.rows.length">
                            <tr class="report-total-row">
                                <td colspan="4">Total</td>
                                <td class="text-end">{{ number(report.totals.quantity) }}</td>
                                <td colspan="2"></td>
                                <td class="text-end">{{ peso(report.totals.stock_value) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <ClientPagination v-model="currentPage" :total="report.rows.length" :per-page="pageSize" noun="batches" />

                <div v-if="report.by_brand.length" class="brand-breakdown">
                    <h5 class="brand-breakdown-title">By brand</h5>
                    <div class="brand-grid">
                        <div v-for="b in report.by_brand" :key="b.brand" class="brand-card">
                            <span class="brand-name">{{ b.brand }}</span>
                            <strong class="brand-value">{{ peso(b.stock_value) }}</strong>
                            <small class="brand-note">{{ b.batches }} batches · {{ number(b.quantity) }} units</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import _ from 'lodash';
import ClientPagination from '@/Shared/Components/ClientPagination.vue';

export default {
    name: 'InventoryReportTab',
    components: { ClientPagination },
    emits: ['toast'],
    data() {
        return {
            loading: true,
            brands: [],
            report: { totals: this.emptyTotals(), rows: [], by_brand: [] },
            showExport: false,
            currentPage: 1,
            pageSize: 15,
            periods: [
                { value: 'current', label: 'Current' },
                { value: 'week', label: 'Weekly' },
                { value: 'month', label: 'Monthly' },
                { value: 'quarter', label: 'Quarterly' },
                { value: 'year', label: 'Yearly' },
            ],
            filter: {
                keyword: '',
                brand: '',
                low_stock_only: false,
                include_empty: false,
                period: 'current',
                period_value: '',
            },
        };
    },
    created() {
        this.debouncedFetch = _.debounce(this.fetch, 350);
    },
    mounted() {
        this.fetch();
    },
    computed: {
        totalPages() {
            return Math.max(1, Math.ceil((this.report.rows || []).length / this.pageSize));
        },
        rangeStart() {
            return this.report.rows.length ? (this.currentPage - 1) * this.pageSize + 1 : 0;
        },
        rangeEnd() {
            return Math.min(this.currentPage * this.pageSize, this.report.rows.length);
        },
        /** Only the current page is drawn; the totals row still sums everything. */
        pagedRows() {
            const start = (this.currentPage - 1) * this.pageSize;
            return (this.report.rows || []).slice(start, start + this.pageSize);
        },
        /**
         * The choices behind the granularity — actual month names, quarters,
         * week ranges or years, most recent first, so picking one reads plainly
         * rather than asking for a date format.
         */
        periodOptions() {
            const now = new Date();
            const months = ['January','February','March','April','May','June',
                            'July','August','September','October','November','December'];

            if (this.filter.period === 'year') {
                return Array.from({ length: 5 }, (_, i) => {
                    const y = now.getFullYear() - i;
                    return { value: String(y), label: String(y) };
                });
            }

            if (this.filter.period === 'month') {
                return Array.from({ length: 12 }, (_, i) => {
                    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                    const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
                    return { value, label: `${months[d.getMonth()]} ${d.getFullYear()}` };
                });
            }

            if (this.filter.period === 'quarter') {
                const options = [];
                let q = Math.floor(now.getMonth() / 3) + 1;
                let y = now.getFullYear();
                for (let i = 0; i < 8; i++) {
                    options.push({ value: `${y}-Q${q}`, label: `Q${q} ${y} (${this.quarterMonths(q)})` });
                    q -= 1;
                    if (q === 0) { q = 4; y -= 1; }
                }
                return options;
            }

            // Weekly — label with the dates the week covers, since a week number
            // on its own tells nobody anything.
            return Array.from({ length: 12 }, (_, i) => {
                const monday = this.mondayOf(now, -i);
                const sunday = new Date(monday);
                sunday.setDate(monday.getDate() + 6);
                return {
                    value: `${monday.getFullYear()}-W${String(this.isoWeek(monday)).padStart(2, '0')}`,
                    label: `${this.shortDate(monday)} – ${this.shortDate(sunday)}`,
                };
            });
        },
        /**
         * Says plainly which question the figures answer. 'Current' is the whole
         * position; a period narrows to what arrived in it, which is a different
         * number and must not read as though it were total stock.
         */
        periodCaption() {
            if (this.filter.period === 'current') {
                return 'Stock held right now — a snapshot, not a period.';
            }
            const chosen = this.periodOptions.find((o) => o.value === this.filter.period_value);
            const when = chosen ? chosen.label : 'this period';
            return `Stock received in ${when} and still on hand — not your total stock.`;
        },
    },
    methods: {
        selectPeriod(period) {
            this.filter.period = period;
            // Default to the most recent choice so the dropdown is never blank.
            this.filter.period_value = period === 'current'
                ? ''
                : (this.periodOptions[0] || {}).value || '';
            this.fetch();
        },
        quarterMonths(q) {
            return ['Jan–Mar', 'Apr–Jun', 'Jul–Sep', 'Oct–Dec'][q - 1] || '';
        },
        mondayOf(from, weekOffset) {
            const d = new Date(from);
            d.setDate(d.getDate() + weekOffset * 7);
            const day = (d.getDay() + 6) % 7; // Monday-first
            d.setDate(d.getDate() - day);
            d.setHours(0, 0, 0, 0);
            return d;
        },
        isoWeek(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNum = d.getUTCDay() || 7;
            d.setUTCDate(d.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
            return Math.ceil(((d - yearStart) / 86400000 + 1) / 7);
        },
        shortDate(d) {
            return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
        },
        emptyTotals() {
            return {
                stock_value: 0, retail_value: 0, products: 0, batches: 0,
                quantity: 0, low_stock: 0, out_of_stock: 0, expiring: 0,
            };
        },
        fetch() {
            this.loading = true;
            axios.get('/inventory-report', { params: { ...this.filter, option: 'summary' } })
                .then(({ data }) => {
                    this.report = data;
                    this.currentPage = 1;
                    // Brands come from the rows themselves, so the filter only
                    // ever offers brands that actually have stock.
                    this.brands = [...new Set((data.rows || []).map((r) => r.brand))].sort();
                })
                .catch(() => { this.$emit('toast', 'Could not load the inventory report.'); })
                .finally(() => { this.loading = false; });
        },
        exportUrl(option) {
            const params = new URLSearchParams({ ...this.filter, option });
            return `/inventory-report?${params.toString()}`;
        },
        statusLabel(status) {
            return {
                in_stock: 'In stock',
                low_stock: 'Low stock',
                out_of_stock: 'Out of stock',
                expiring: 'Expiring',
                expired: 'Expired',
            }[status] || status;
        },
        peso(value) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency', currency: 'PHP', minimumFractionDigits: 2,
            }).format(Number(value) || 0);
        },
        number(value) {
            return new Intl.NumberFormat('en-PH').format(Number(value) || 0);
        },
    },
};
</script>

<style scoped>

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

.stat-card {
    border: 1px solid #e4efeb;
    border-left: 4px solid #cbd5e1;
    border-radius: 10px;
    padding: 0.75rem 0.9rem;
    background: #fbfefd;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.stat-card.accent-green  { border-left-color: #3d8d7a; }
.stat-card.accent-blue   { border-left-color: #2563eb; }
.stat-card.accent-amber  { border-left-color: #d97706; }
.stat-card.accent-red    { border-left-color: #dc2626; }
.stat-card.accent-orange { border-left-color: #ea580c; }

.stat-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b8c85;
}

.stat-value { font-size: 1.15rem; color: #16322e; }
.stat-note  { font-size: 0.68rem; color: #94a3b8; }

.export-wrap { position: relative; }

.export-menu {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    min-width: 210px;
    background: #fff;
    border: 1px solid #dcebe5;
    border-radius: 12px;
    box-shadow: 0 10px 26px rgba(22, 50, 46, 0.13);
    padding: 0.35rem;
    z-index: 50;
}

.export-option {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.6rem;
    border-radius: 9px;
    color: #16322e;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
}

.export-option:hover { background: #f2faf7; }
.export-option i { font-size: 1.05rem; color: #3d8d7a; }
.export-option span { display: flex; flex-direction: column; line-height: 1.2; }
.export-option small { font-weight: 500; font-size: 0.68rem; color: #94a3b8; }

.period-select {
    border: 1px solid #dcebe5;
    border-radius: 999px;
    padding: 0.35rem 0.8rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: #16322e;
    background: #fff;
    margin-left: 0.25rem;
}

.period-tabs {
    display: flex;
    gap: 0.35rem;
    margin-bottom: 0.9rem;
    flex-wrap: wrap;
}

.period-tab {
    border: 1px solid #dcebe5;
    background: #fff;
    color: #4a6b63;
    border-radius: 999px;
    padding: 0.35rem 0.9rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
}

.period-tab:hover { background: #f2faf7; }

.period-tab.active {
    background: #3d8d7a;
    border-color: #3d8d7a;
    color: #fff;
}

.report-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: #4a6b63;
    margin: 0;
    cursor: pointer;
}

.source-chip,
.status-chip {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.68rem;
    font-weight: 700;
}

.source-chip.received  { background: #e0f2fe; color: #075985; }
.source-chip.converted { background: #ede9fe; color: #5b21b6; }

.status-chip.in_stock     { background: #dcfce7; color: #166534; }
.status-chip.low_stock    { background: #fef3c7; color: #92400e; }
.status-chip.out_of_stock { background: #fee2e2; color: #7c2d12; }
.status-chip.expiring     { background: #ffedd5; color: #9a3412; }
.status-chip.expired      { background: #fee2e2; color: #7c2d12; }

.report-total-row td {
    background: #f4faf8;
    font-weight: 700;
    border-top: 2px solid #3d8d7a;
}

.brand-breakdown { margin-top: 1.5rem; }

.brand-breakdown-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #16322e;
    margin-bottom: 0.6rem;
}

.brand-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 0.6rem;
}

.brand-card {
    border: 1px solid #e4efeb;
    border-radius: 10px;
    padding: 0.6rem 0.8rem;
    background: #fff;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.brand-name  { font-size: 0.78rem; font-weight: 600; color: #4a6b63; }
.brand-value { font-size: 1rem; color: #16322e; }
.brand-note  { font-size: 0.68rem; color: #94a3b8; }
</style>
