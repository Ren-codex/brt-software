<template>
    <div>
        <div class="col-lg-12 mb-4">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="header-icon">
                            <i class="ri-history-line fs-24"></i>
                        </div>
                        <div>
                            <h4 class="header-title mb-1">Returns History</h4>
                            <p class="header-subtitle mb-0">Approved return breakdown — restocked vs. written off</p>
                        </div>
                    </div>
                </div>

                <div class="library-card-body">
                    <!-- Filters -->
                    <div class="search-section mb-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">From</label>
                                <input type="date" v-model="filter.from" class="form-control form-control-sm" @change="fetch()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">To</label>
                                <input type="date" v-model="filter.to" class="form-control form-control-sm" @change="fetch()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Condition</label>
                                <select v-model="filter.condition" class="form-select form-select-sm" @change="fetch()">
                                    <option :value="null">All Conditions</option>
                                    <option value="restockable">Restockable</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="loss">Loss</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Boxes -->
                    <div class="row g-3 mb-4" v-if="summary">
                        <div class="col-md-4">
                            <div class="summary-card restockable">
                                <div class="summary-icon"><i class="ri-refresh-line"></i></div>
                                <div class="summary-body">
                                    <div class="summary-label">Restocked</div>
                                    <div class="summary-value">{{ formatCurrency(summary.restockable?.value || 0) }}</div>
                                    <div class="summary-sub">{{ summary.restockable?.qty || 0 }} units returned to stock</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-card damaged">
                                <div class="summary-icon"><i class="ri-error-warning-line"></i></div>
                                <div class="summary-body">
                                    <div class="summary-label">Damaged / Written Off</div>
                                    <div class="summary-value">{{ formatCurrency(summary.damaged?.value || 0) }}</div>
                                    <div class="summary-sub">{{ summary.damaged?.qty || 0 }} units written off</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-card loss">
                                <div class="summary-icon"><i class="ri-close-circle-line"></i></div>
                                <div class="summary-body">
                                    <div class="summary-label">Loss</div>
                                    <div class="summary-value">{{ formatCurrency(summary.loss?.value || 0) }}</div>
                                    <div class="summary-sub">{{ summary.loss?.qty || 0 }} units recorded as loss</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table sales-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:3%">#</th>
                                    <th>SO Number</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Condition</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total Value</th>
                                    <th class="text-center">Date</th>
                                    <th class="text-center">Approved By</th>
                                </tr>
                            </thead>
                            <tbody class="fs-12">
                                <tr v-for="(row, i) in rows" :key="row.id">
                                    <td>{{ i + 1 }}</td>
                                    <td class="fw-semibold">{{ row.so_number }}</td>
                                    <td>{{ row.customer || '-' }}</td>
                                    <td>{{ row.product || '-' }}</td>
                                    <td class="text-center">{{ row.quantity }}</td>
                                    <td class="text-center">
                                        <span class="condition-badge" :class="row.condition">
                                            {{ conditionLabel(row.condition) }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ formatCurrency(row.unit_price) }}</td>
                                    <td class="text-end fw-semibold">{{ formatCurrency(row.total_value) }}</td>
                                    <td class="text-center">{{ row.returned_at }}</td>
                                    <td class="text-center">{{ row.approved_by || '-' }}</td>
                                </tr>
                                <tr v-if="rows.length === 0">
                                    <td colspan="10">
                                        <div class="sales-empty-state">
                                            <i class="ri-history-line"></i>
                                            <p>No return history found.</p>
                                            <small>Approved returns will appear here.</small>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: ['isExternal'],
    data() {
        const today = new Date();
        const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
        const todayStr = today.toISOString().split('T')[0];
        return {
            filter: {
                from: firstOfMonth,
                to: todayStr,
                condition: null,
            },
            rows: [],
            summary: null,
        };
    },
    created() {
        this.fetch();
    },
    methods: {
        fetch() {
            const url = this.isExternal ? '/sales-orders-external' : '/sales-orders';
            axios.get(url, {
                params: {
                    option: 'return-history',
                    from: this.filter.from,
                    to: this.filter.to,
                    condition: this.filter.condition,
                }
            }).then(res => {
                this.rows = res.data.rows || [];
                this.summary = res.data.summary || null;
            }).catch(err => console.error(err));
        },
        formatCurrency(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        conditionLabel(cond) {
            const map = { restockable: 'Restockable', damaged: 'Damaged', loss: 'Loss' };
            return map[cond] || cond;
        },
    }
}
</script>

<style scoped>
.summary-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.summary-card.restockable { border-left: 4px solid #3D8D7A; }
.summary-card.damaged     { border-left: 4px solid #e67e22; }
.summary-card.loss        { border-left: 4px solid #e74c3c; }

.summary-icon {
    font-size: 1.5rem;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    flex-shrink: 0;
}
.restockable .summary-icon { background: rgba(61,141,122,0.1); color: #3D8D7A; }
.damaged     .summary-icon { background: rgba(230,126,34,0.1); color: #e67e22; }
.loss        .summary-icon { background: rgba(231,76,60,0.1);  color: #e74c3c; }

.summary-label { font-size: 0.75rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.summary-value { font-size: 1.25rem; font-weight: 700; color: #2b3459; }
.summary-sub   { font-size: 0.75rem; color: #6c757d; }

.condition-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.condition-badge.restockable { background: rgba(61,141,122,0.12); color: #3D8D7A; }
.condition-badge.damaged     { background: rgba(230,126,34,0.12); color: #e67e22; }
.condition-badge.loss        { background: rgba(231,76,60,0.12);  color: #e74c3c; }
</style>
