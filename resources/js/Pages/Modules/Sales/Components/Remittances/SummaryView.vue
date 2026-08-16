<template>
    <div class="library-card">
        <div class="library-card-header">
            <div class="d-flex align-items-center gap-3">
                <div class="header-icon">
                    <i class="ri-bar-chart-grouped-line fs-20"></i>
                </div>
                <div>
                    <h4 class="header-title mb-1">Pending Collections Summary</h4>
                    <p class="header-subtitle mb-0">Pending receipts and pending AR invoices, grouped by employee</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="date" class="date-input" v-model="filter.from" @change="fetch">
                <span class="date-sep">to</span>
                <input type="date" class="date-input" v-model="filter.to" @change="fetch">
                <button class="create-btn" @click="$emit('back')">
                    <i class="ri-arrow-left-line"></i>
                </button>
            </div>
        </div>

        <div class="library-card-body">
            <div class="employee-search">
                <i class="ri-search-line"></i>
                <input
                    v-model="employeeSearch"
                    type="text"
                    placeholder="Search employee..."
                    class="employee-search-input"
                />
            </div>

            <div v-if="loading" class="empty-state">
                <i class="ri-loader-4-line spin"></i>
                <p>Loading summary...</p>
            </div>

            <div v-else-if="!filteredRows.length" class="empty-state">
                <i class="ri-inbox-line"></i>
                <p>{{ employeeSearch ? 'No employees match your search.' : 'No pending receipts or AR invoices for the selected period.' }}</p>
            </div>

            <div v-else class="rep-list">
                <div v-for="rep in filteredRows" :key="rep.rep_id" class="rep-block">
                    <!-- Rep header row -->
                    <div
                        class="rep-header"
                        @click="toggleRep(rep.rep_id)"
                        :class="{ expanded: expandedReps[rep.rep_id] }"
                    >
                        <div class="d-flex align-items-center gap-3">
                            <div class="rep-avatar">
                                {{ initials(rep.rep_name) }}
                            </div>
                            <div>
                                <div class="rep-name">{{ rep.rep_name }}</div>
                                <div class="rep-meta">
                                    {{ rep.pending_receipt_count }} pending receipt{{ rep.pending_receipt_count !== 1 ? 's' : '' }}
                                    &middot;
                                    {{ rep.pending_ar_count }} pending AR invoice{{ rep.pending_ar_count !== 1 ? 's' : '' }}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="rep-total-group">
                                <div class="rep-total">{{ formatCurrency(rep.pending_receipt_total) }}</div>
                                <div class="rep-total-label">Pending Receipts</div>
                            </div>
                            <div class="rep-total-group">
                                <div class="rep-total">{{ formatCurrency(rep.pending_ar_total) }}</div>
                                <div class="rep-total-label">Pending AR</div>
                            </div>
                            <i :class="['ri-arrow-down-s-line chevron', { rotated: expandedReps[rep.rep_id] }]"></i>
                        </div>
                    </div>

                    <!-- Detail tables -->
                    <div v-show="expandedReps[rep.rep_id]" class="rep-detail">
                        <div class="detail-block">
                            <div class="detail-title">
                                <i class="ri-receipt-line"></i>
                                <h5>Pending Receipts</h5>
                            </div>
                            <table class="detail-table">
                                <thead>
                                    <tr>
                                        <th>Receipt #</th>
                                        <th>Date</th>
                                        <th>SO #</th>
                                        <th>Customer</th>
                                        <th class="text-right">Amount Paid</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in rep.pending_receipts" :key="`receipt-${item.id}`">
                                        <td><span class="doc-number">{{ item.receipt_number || '-' }}</span></td>
                                        <td>{{ formatDate(item.receipt_date) }}</td>
                                        <td>{{ item.so_number || '-' }}</td>
                                        <td>{{ item.customer_name }}</td>
                                        <td class="text-right amount">{{ formatCurrency(item.amount_paid) }}</td>
                                    </tr>
                                    <tr v-if="!rep.pending_receipts.length">
                                        <td colspan="5" class="empty-row">No pending receipts</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="detail-block">
                            <div class="detail-title">
                                <i class="ri-file-list-3-line"></i>
                                <h5>Pending AR Invoices</h5>
                            </div>
                            <table class="detail-table">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Date</th>
                                        <th>Due Date</th>
                                        <th>SO #</th>
                                        <th>Customer</th>
                                        <th class="text-right">Balance Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in rep.pending_ar_invoices" :key="`ar-${item.id}`">
                                        <td><span class="doc-number">{{ item.invoice_number || '-' }}</span></td>
                                        <td>{{ formatDate(item.invoice_date) }}</td>
                                        <td>{{ formatDate(item.due_date) }}</td>
                                        <td>{{ item.so_number || '-' }}</td>
                                        <td>{{ item.customer_name }}</td>
                                        <td class="text-right amount">{{ formatCurrency(item.balance_due) }}</td>
                                    </tr>
                                    <tr v-if="!rep.pending_ar_invoices.length">
                                        <td colspan="6" class="empty-row">No pending AR invoices</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SummaryView',
    emits: ['back'],
    data() {
        const now = new Date();
        const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
        const lastOfMonth  = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);

        return {
            loading: false,
            rows: [],
            expandedReps: {},
            employeeSearch: '',
            filter: {
                from: firstOfMonth,
                to:   lastOfMonth,
            },
        };
    },
    computed: {
        filteredRows() {
            const term = this.employeeSearch.trim().toLowerCase();
            if (!term) return this.rows;
            return this.rows.filter(rep => (rep.rep_name || '').toLowerCase().includes(term));
        },
    },
    created() {
        this.fetch();
    },
    methods: {
        fetch() {
            this.loading = true;
            axios.get('/remittances', {
                params: {
                    option: 'summary',
                    from: this.filter.from,
                    to:   this.filter.to,
                },
            })
            .then(res => {
                this.rows = res.data.data ?? [];
                // auto-expand first rep
                if (this.rows.length && !Object.keys(this.expandedReps).length) {
                    this.expandedReps = { [this.rows[0].rep_id]: true };
                }
            })
            .catch(err => console.error(err))
            .finally(() => { this.loading = false; });
        },
        toggleRep(id) {
            this.expandedReps = {
                ...this.expandedReps,
                [id]: !this.expandedReps[id],
            };
        },
        formatCurrency(value) {
            if (!value && value !== 0) return '—';
            return '₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDate(value) {
            if (!value) return '—';
            return new Date(value + 'T00:00:00').toLocaleDateString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric',
            });
        },
        initials(name) {
            if (!name) return '?';
            return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
        },
    },
};
</script>

<style scoped>
.date-input {
    padding: 0.3rem 0.6rem;
    border: 1px solid #c4d9d2;
    border-radius: 8px;
    font-size: 0.82rem;
    color: #16322e;
    background: #fff;
}
.date-sep { font-size: 0.8rem; color: #6b8c85; }

.employee-search {
    position: relative;
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    max-width: 280px;
}
.employee-search i {
    position: absolute;
    left: 0.65rem;
    color: #8ea298;
    font-size: 0.95rem;
}
.employee-search-input {
    width: 100%;
    padding: 0.5rem 0.75rem 0.5rem 2rem;
    border: 1px solid #d7e5de;
    border-radius: 10px;
    font-size: 0.85rem;
    background: white;
    transition: all 0.15s ease;
}
.employee-search-input:focus {
    outline: none;
    border-color: #3d8d7a;
    box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.12);
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b8c85;
}
.empty-state i { font-size: 2rem; display: block; margin-bottom: 0.5rem; }

.rep-list { display: flex; flex-direction: column; gap: 10px; }

.rep-block {
    border: 1px solid #d8e9e5;
    border-radius: 12px;
    overflow: hidden;
}

.rep-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.85rem 1.1rem;
    background: #f4fbf8;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.rep-header:hover { background: #eaf5f1; }
.rep-header.expanded { background: #e3f2ed; }

.rep-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2fa485, #1a7e67);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.rep-name { font-size: 0.9rem; font-weight: 700; color: #16322e; }
.rep-meta { font-size: 0.75rem; color: #6b8c85; }

.rep-total-group { text-align: right; min-width: 110px; }
.rep-total { font-size: 0.95rem; font-weight: 700; color: #16423c; }
.rep-total-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6b8c85; font-weight: 600; }

.chevron { font-size: 1.1rem; color: #6b8c85; transition: transform 0.2s; }
.chevron.rotated { transform: rotate(180deg); }

.rep-detail {
    border-top: 1px solid #d8e9e5;
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    background: #fff;
}

.detail-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.6rem;
}
.detail-title i { color: #3d8d7a; font-size: 1rem; }
.detail-title h5 { font-size: 0.85rem; font-weight: 700; color: #20413a; margin: 0; }

.detail-table {
    width: 100%;
    border-collapse: collapse;
}
.detail-table th {
    text-align: left;
    padding: 0.55rem 0.5rem;
    font-size: 0.66rem;
    font-weight: 700;
    color: #648b74;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e0ece7;
}
.detail-table td {
    padding: 0.55rem 0.5rem;
    font-size: 0.8rem;
    color: #20413a;
    border-bottom: 1px solid #edf3f1;
}
.detail-table tr:last-child td { border-bottom: none; }
.detail-table tbody tr:hover td { background: #f4faf8; }

.text-right { text-align: right; }
.amount { font-weight: 700; color: #2f7666; }
.doc-number { font-weight: 700; color: #2f7666; }

.empty-row {
    text-align: center;
    color: #8ea298;
    padding: 1.25rem !important;
}

@keyframes spin { to { transform: rotate(360deg); } }
.spin { display: inline-block; animation: spin 0.9s linear infinite; }
</style>
