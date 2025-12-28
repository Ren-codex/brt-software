<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" style="max-width: 900px;">
            <div class="modal-header">
                <h2>Prepare Remittance</h2>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>

            <div class="modal-body">
                <div class="mb-3 d-flex align-items-center gap-2">
                    <input type="text" v-model="keyword" @input="debouncedFetch" placeholder="Search receipt" class="form-control" />
                    <b-button size="sm" variant="outline-primary" @click="toggleSelectAll">{{ allSelected ? 'Unselect All' : 'Select All' }}</b-button>
                </div>

                <div class="table-responsive" style="max-height: 220px; overflow:auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px"><input type="checkbox" :checked="allSelected" @change="toggleSelectAll" /></th>
                                <th>#</th>
                                <th>Receipt No.</th>
                                <th>Customer</th>
                                <th class="text-end">Amount</th>
                                <th>Payment</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(order, idx) in filteredOrders" :key="order.id">
                                <td><input type="checkbox" :value="order.id" v-model="selectedIds" /></td>
                                <td>{{ idx + 1 }}</td>
                                <td>{{ order.receipt_number || '-' }}</td>
                                <td>{{ order.customer?.name || '-' }}</td>
                                <td class="text-end">{{ formatAmount(order.amount_paid) }}</td>
                                <td>{{ order.payment_mode }}</td>
                                <td>{{ order.created_at }}</td>
                            </tr>
                            <tr v-if="orders.length === 0">
                                <td colspan="7" class="text-center text-muted">No pending sales orders found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <h6 class="text-primary"><i class="ri-money-dollar-circle-line"></i> Summary</h6>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <div class="p-2 bg-light rounded">
                                <small class="text-muted">Cash</small>
                                <div class="fw-bold">{{ formatAmount(totals.cash) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 bg-light rounded">
                                <small class="text-muted">Credit Card</small>
                                <div class="fw-bold">{{ formatAmount(totals.credit_card) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 bg-light rounded">
                                <small class="text-muted">Debit Card</small>
                                <div class="fw-bold">{{ formatAmount(totals.debit_card) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 bg-light rounded">
                                <small class="text-muted">Bank Transfer</small>
                                <div class="fw-bold">{{ formatAmount(totals.bank_transfer) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="my-4 text-end">
                        TOTAL<h2 class="mb-0"><strong> {{ formatAmount(totals.overall) }}</strong></h2>
                    </div>
                </div>

                <div class="form-actions mt-3 d-flex justify-content-end gap-2">
                    <b-button variant="outline-secondary" @click="hide">Cancel</b-button>
                    <b-button variant="primary" :disabled="selectedIds.length === 0 || submitting" @click="submit">
                        <i v-if="!submitting" class="ri-save-line me-1"></i>
                        <i v-else class="ri-loader-4-line spinner me-1"></i>
                        Prepare Remittance
                    </b-button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import _ from 'lodash';
export default {
    props: ['dropdowns'],
    data() {
        return {
            showModal: false,
            orders: [],
            filteredOrders: [],
            selectedIds: [],
            keyword: '',
            submitting: false,
            debouncedFetch: null
        }; 
    },
    computed: {
        allSelected() {
            return this.filteredOrders.length > 0 && this.selectedIds.length === this.filteredOrders.length;
        },
        totals() {
            const t = { cash: 0, credit_card: 0, debit_card: 0, bank_transfer: 0, overall: 0 };
            const selected = this.orders.filter(o => this.selectedIds.includes(o.id));
            selected.forEach(o => {
                const amt = parseFloat(o.amount_paid) || 0;
                const mode = (o.payment_mode || '').toLowerCase();
                if (mode === 'cash') t.cash += amt;
                else if (mode === 'credit card' || mode === 'credit_card' || mode === 'creditcard') t.credit_card += amt;
                else if (mode === 'debit card' || mode === 'debit_card' || mode === 'debitcard') t.debit_card += amt;
                else if (mode === 'bank transfer' || mode === 'bank_transfer' || mode === 'banktransfer') t.bank_transfer += amt;
                else t.overall += amt; // if unknown mode, still add to overall below
                t.overall += amt;
            });
            return t;
        }
    },
    created() {
        this.debouncedFetch = _.debounce(this.applyFilter, 400);
    },
    methods: {
        show() {
            this.showModal = true;
            this.selectedIds = [];
            this.keyword = '';
            this.fetchPending();
        },
        hide() {
            this.showModal = false;
        },
        fetchPending() {
            axios.get('/receipts', {
                params: {
                    status: 'pending',
                    option: 'lists',
                    count: 100
                }
            })
            .then(res => {
                if (res && res.data) {
                    // Assume data is array
                    this.orders = res.data.data || res.data;
                    // Reset selectedIds to only those still present
                    this.selectedIds = this.selectedIds.filter(id => this.orders.find(o => o.id === id));
                    // apply client-side filter (displayed list)
                    this.applyFilter();
                }
            })
            .catch(err => console.error(err));
        },
        applyFilter() {
            if (!this.keyword) {
                this.filteredOrders = this.orders;
            } else {
                const kw = this.keyword.toLowerCase();
                this.filteredOrders = this.orders.filter(o => ((o.so_number || o.receipt_number || '') + ' ' + (o.customer?.name || '')).toLowerCase().includes(kw));
            }
        },

        toggleSelectAll() {
            if (this.allSelected) {
                this.selectedIds = [];
            } else {
                this.selectedIds = this.filteredOrders.map(o => o.id);
            }
        },
        formatAmount(v) {
            if (!v) return '₱0.00';
            return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        submit() {
            if (this.selectedIds.length === 0) return;
            this.submitting = true;
            axios.post('/remittances', {
                sales_order_ids: this.selectedIds,
                option: 'bulk_create'
            })
            .then(resp => {
                this.submitting = false;
                this.$emit('created', true);
                this.hide();
            })
            .catch(err => {
                this.submitting = false;
                console.error(err);
            });
        }
    }
};
</script>

<style scoped>
.modal-overlay{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);z-index:50}
.modal-container{background:#fff;border-radius:8px;overflow:hidden;width:100%;}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #eee}
.modal-body{padding:16px}
.close-btn{background:transparent;border:0}
.form-actions .btn{min-width:140px}
</style>