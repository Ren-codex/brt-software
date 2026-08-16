<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" style="max-width: 900px;">
            <div class="modal-header">
                <div>
                    <h2>Prepare Remittance</h2>
                    <p class="modal-subtitle mb-0">{{ stepSubtitle }}</p>
                </div>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>

            <div class="modal-body">
                <div class="success-alert" v-if="saveSuccess">
                    <i class="ri-checkbox-circle-fill"></i>
                    <span>Your information has been saved successfully!</span>
                </div>

                <form @submit.prevent="submit">
                    <!-- Step 1: pending receipts to remit + outstanding AR invoices (reference only) -->
                    <div v-show="currentStep === 1" class="step-content">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="text-primary mb-0"><i class="ri-receipt-line"></i> Receipts to Remit</h6>
                            <button v-if="!isSalesRep" type="button" class="acct-btn-secondary" @click="toggleSelectAll">{{ allSelected ? 'Unselect All' : 'Select All' }}</button>
                        </div>

                        <!-- A Sales Rep must remit every pending receipt of theirs, so the
                             checkbox column is dropped entirely: nothing here is optional. -->
                        <div class="table-responsive" style="max-height: 220px; overflow:auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th v-if="!isSalesRep" style="width:40px"><input type="checkbox" :checked="allSelected"
                                                @change="toggleSelectAll" /></th>
                                        <th>#</th>
                                        <th>Receipt No.</th>
                                        <th>Customer</th>
                                        <th class="text-end">Amount</th>
                                        <th>Payment</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(order, idx) in orders" :key="order.id">
                                        <td v-if="!isSalesRep"><input type="checkbox" :value="order.id" v-model="selectedIds" /></td>
                                        <td>{{ idx + 1 }}</td>
                                        <td>{{ order.receipt_number || '-' }}</td>
                                        <td>{{ getCustomerName(order) }}</td>
                                        <td class="text-end">{{ formatAmount(order.amount_paid) }}</td>
                                        <td>{{ getPaymentMode(order) || '-' }}</td>
                                        <td>{{ formatDate(order.created_at) }}</td>
                                    </tr>
                                    <tr v-if="orders.length === 0">
                                        <td :colspan="isSalesRep ? 6 : 7" class="text-center text-muted">No pending sales orders found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <small class="text-muted">{{ orders.length }} pending receipt(s)</small>
                            <p class="mb-0" v-if="isSalesRep">
                                <span class="text-primary"><b>{{ selectedIds.length }}</b></span> pending receipt(s) to remit
                            </p>
                            <p class="mb-0" v-else>
                                <span class="text-primary"><b>{{ selectedIds.length }}</b></span> Selected
                            </p>
                        </div>
                        <div v-if="form.errors.receipts" class="text-danger mb-2">
                            {{ form.errors.receipts }}
                        </div>

                        <!-- Unpaid AR invoices, informational only — nothing here is selected or submitted -->
                        <h6 class="text-primary mb-2 mt-4"><i class="ri-file-warning-line"></i> Outstanding AR Invoices</h6>
                        <p class="text-muted small mb-2">Outstanding credit-sale invoices, for reference before remitting.</p>
                        <div class="table-responsive" style="max-height: 220px; overflow:auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Invoice No.</th>
                                        <th>Customer</th>
                                        <th class="text-end">Balance Due</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="loadingUnpaidInvoices">
                                        <td colspan="6" class="text-center text-muted">Loading…</td>
                                    </tr>
                                    <template v-else>
                                        <tr v-for="(invoice, idx) in unpaidInvoices" :key="invoice.id">
                                            <td>{{ idx + 1 }}</td>
                                            <td>{{ invoice.invoice_number || '-' }}</td>
                                            <td>{{ invoice.sales_order?.customer?.name || '-' }}</td>
                                            <td class="text-end">{{ formatAmount(invoice.balance_due) }}</td>
                                            <td>{{ invoice.due_date_formatted || '-' }}</td>
                                            <td>{{ invoice.status?.name || '-' }}</td>
                                        </tr>
                                        <tr v-if="unpaidInvoices.length === 0">
                                            <td colspan="6" class="text-center text-muted">No unpaid invoices found.</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">{{ unpaidInvoices.length }} unpaid invoice(s)</small>
                        </div>
                    </div>

                    <!-- Step 2: totals by payment mode, receipt count, and outstanding AR -->
                    <div v-show="currentStep === 2" class="step-content">
                        <div class="remit-total-banner">
                            <div class="remit-total-banner-left">
                                <span class="remit-total-icon"><i class="ri-wallet-3-line"></i></span>
                                <div>
                                    <div class="remit-total-label">Total Remittance Amount</div>
                                    <div class="remit-total-amount">{{ formatAmount(totals.overall) }}</div>
                                </div>
                            </div>
                            <div class="remit-total-banner-right">
                                <i class="ri-file-list-3-line"></i>
                                <span class="remit-total-check"><i class="ri-check-line"></i></span>
                            </div>
                        </div>

                        <div class="remit-cards-row">
                            <div class="remit-card">
                                <div class="remit-card-title">
                                    <span class="remit-card-icon"><i class="ri-file-list-2-line"></i></span>
                                    Receipts Overview
                                </div>
                                <div class="remit-card-body">
                                    <div class="remit-stat">
                                        <div class="remit-stat-label">Total Receipts</div>
                                        <div class="remit-stat-value">{{ selectedIds.length }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="remit-card remit-card-wide">
                                <div class="remit-card-title">
                                    <span class="remit-card-icon"><i class="ri-pie-chart-2-line"></i></span>
                                    Payment Breakdown
                                </div>
                                <div class="remit-card-body remit-card-body-row">
                                    <div class="remit-stat">
                                        <div class="remit-stat-label">Cash Sales</div>
                                        <div class="remit-stat-value">{{ formatAmount(totals.cash_sales) }}</div>
                                    </div>
                                    <div class="remit-stat">
                                        <div class="remit-stat-label">Credit Sales</div>
                                        <div class="remit-stat-value">{{ formatAmount(totals.credit_sales) }}</div>
                                    </div>
                                    <div class="remit-stat">
                                        <div class="remit-stat-label">Bank Transfer</div>
                                        <div class="remit-stat-value">{{ formatAmount(totals.bank_transfer) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="remit-card remit-card-full">
                            <div class="remit-card-title">
                                <span class="remit-card-icon"><i class="ri-user-line"></i></span>
                                Accounts Receivable
                            </div>
                            <div class="remit-ar-row">
                                <div class="remit-ar-tile">
                                    <span class="remit-ar-icon"><i class="ri-file-text-line"></i></span>
                                    <div>
                                        <div class="remit-stat-label">Unpaid Invoices</div>
                                        <div class="remit-stat-value">{{ unpaidInvoices.length }}</div>
                                    </div>
                                </div>
                                <div class="remit-ar-tile">
                                    <span class="remit-ar-icon"><i class="ri-wallet-3-line"></i></span>
                                    <div>
                                        <div class="remit-stat-label">Outstanding Balance</div>
                                        <div class="remit-stat-value">{{ formatAmount(unpaidInvoicesTotal) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="Object.keys(form.errors).length > 0" class="alert alert-danger py-2 mt-3">
                        <ul class="mb-0 ps-3">
                            <li v-for="(error, field) in form.errors" :key="field" class="small">{{ error }}</li>
                        </ul>
                    </div>

                    <div class="form-actions mt-3 d-flex justify-content-end gap-2">
                        <button v-if="currentStep > 1" type="button" class="acct-btn-secondary" @click="prevStep">
                            <i class="ri-arrow-left-line"></i>
                            Back
                        </button>
                        <button v-if="currentStep < 2" type="button" class="btn btn-save" :disabled="selectedIds.length === 0" @click="nextStep">
                            Next
                            <i class="ri-arrow-right-line"></i>
                        </button>
                        <button v-else type="submit" class="btn btn-save" :disabled="selectedIds.length === 0 || submitting">
                            <i class="ri-save-line" v-if="!submitting"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ submitting ? 'Saving...' : 'Save Remittance' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
    data() {
        return {
            showModal: false,
            currentStep: 1,
            orders: [],
            selectedIds: [],
            unpaidInvoices: [],
            loadingUnpaidInvoices: false,
            submitting: false,
            form: useForm({
                receipts: [],
                summary: {},
                total_amount: 0,
            }),
            saveSuccess: false,
        };
    },
    computed: {
        // A Sales Rep only prepares remittances from their own receipts; anyone
        // else (Admin, Manager, etc.) opening this on behalf of the business
        // needs to see every pending receipt, not just ones tied to their own
        // (likely nonexistent) sales_rep_id — see isSalesRep in Remittances/Index.vue.
        // A user who also holds an admin-level sales role (e.g. Super Admin
        // stacked with Sales Rep, as happens with multi-role test accounts)
        // must still see every pending receipt — only a pure Sales Rep is
        // restricted to their own.
        isSalesRep() {
            const roles = this.$page.props.roles ?? [];
            const hasSalesAdmin = (this.$page.props.permissions?.sales?._module ?? []).includes('admin');
            return roles.includes('Sales Rep') && !!this.$page.props.user?.data?.employee_id && !hasSalesAdmin;
        },
        allSelected() {
            return this.orders.length > 0 && this.selectedIds.length === this.orders.length;
        },
        totals() {
            const t = { cash_sales: 0, credit_sales: 0, bank_transfer: 0, overall: 0 };
            const selected = this.orders.filter(o => this.selectedIds.includes(o.id));
            selected.forEach(o => {
                const amt = parseFloat(o.amount_paid) || 0;
                const mode = this.normalizeSalesPaymentMode(o);
                if (mode === 'credit' || mode === 'credit sales') t.credit_sales += amt;
                else if (mode === 'bank transfer' || mode === 'bank_transfer') t.bank_transfer += amt;
                else t.cash_sales += amt;
                t.overall += amt;
            });
            return t;
        },
        unpaidInvoicesTotal() {
            return this.unpaidInvoices.reduce((sum, inv) => sum + (parseFloat(inv.balance_due) || 0), 0);
        },
        stepSubtitle() {
            if (this.currentStep === 1) return 'Select receipts to remit and review outstanding invoices';
            return 'Summary of remittance details';
        }
    },
    methods: {
        show() {
            this.showModal = true;
            this.currentStep = 1;
            this.selectedIds = [];
            this.fetchPending();
            this.fetchUnpaidInvoices();
        },
        hide() {
            this.showModal = false;
        },
        nextStep() {
            if (this.currentStep === 1 && this.selectedIds.length === 0) return;
            this.currentStep = Math.min(2, this.currentStep + 1);
        },
        prevStep() {
            this.currentStep = Math.max(1, this.currentStep - 1);
        },
        fetchUnpaidInvoices() {
            this.loadingUnpaidInvoices = true;
            axios.get('/ar-invoices', {
                params: {
                    option: 'remittance_candidates',
                    count: 100000,
                }
            })
                .then(res => {
                    if (res && res.data) {
                        this.unpaidInvoices = res.data.data || res.data;
                    }
                })
                .catch(err => console.error(err))
                .finally(() => {
                    this.loadingUnpaidInvoices = false;
                });
        },
        fetchPending() {
            axios.get('/receipts', {
                params: {
                    status: "pending",
                    option: 'lists',
                    count: 100000,
                    ...(this.isSalesRep ? { scope_to_rep: 1 } : {}),
                }
            })
                .then(res => {
                    if (res && res.data) {
                        // Assume data is array
                        this.orders = res.data.data || res.data;
                        if (this.isSalesRep) {
                            // A Sales Rep has no picker to skip receipts with — every
                            // pending receipt of theirs is remitted, always.
                            this.selectedIds = this.orders.map(o => o.id);
                        } else {
                            // Reset selectedIds to only those still present
                            this.selectedIds = this.selectedIds.filter(id => this.orders.find(o => o.id === id));
                        }
                    }
                })
                .catch(err => console.error(err));
        },
        getCustomerName(order) {
            return order?.customer?.name || order?.ar_invoice?.sales_order?.customer?.name || '-';
        },
        getPaymentMode(order) {
            const raw = String(order?.payment_mode || order?.ar_invoice?.sales_order?.payment_mode || '').trim().toLowerCase();
            if (raw === 'credit' || raw === 'credit sales') return 'Credit Sales';
            return 'Cash Sales';
        },
        normalizeSalesPaymentMode(order) {
            return String(this.getPaymentMode(order) || '').trim().toLowerCase();
        },
        toggleSelectAll() {
            if (this.allSelected) {
                this.selectedIds = [];
            } else {
                this.selectedIds = this.orders.map(o => o.id);
            }
        },
        formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            if (Number.isNaN(date.getTime())) return '-';

            const yyyy = date.getUTCFullYear();
            const mm = String(date.getUTCMonth() + 1).padStart(2, '0');
            const dd = String(date.getUTCDate()).padStart(2, '0');
            const hours24 = date.getUTCHours();
            const hours12 = hours24 % 12 || 12;
            const minutes = String(date.getUTCMinutes()).padStart(2, '0');
            const seconds = String(date.getUTCSeconds()).padStart(2, '0');
            const period = hours24 >= 12 ? 'PM' : 'AM';

            return `${yyyy}-${mm}-${dd} ${hours12}:${minutes}:${seconds} ${period}`;
        },
        formatAmount(v) {
            if (!v) return '₱0.00';
            return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        submit() {
            if (this.selectedIds.length === 0) return;
            this.submitting = true;
            this.form.receipts = this.selectedIds;
            const { overall, ...summary } = this.totals;
            this.form.summary = summary;
            this.form.total_amount = this.totals.overall;
            this.form.post('/remittances', {
                preserveScroll: true,
                onSuccess: (response) => {
                    this.saveSuccess = true;
                    setTimeout(() => {
                        this.$emit('add', true);
                        this.form.reset();
                        this.hide();
                        this.saveSuccess = false;
                    }, 1500);
                },
                // Without this the Save button stayed disabled forever after a
                // validation error, since submitting was only cleared on success.
                onFinish: () => {
                    this.submitting = false;
                },
                onError: () => {
                    this.submitting = false;
                },
                onFinish: () => {
                    if (!this.saveSuccess) {
                        this.submitting = false;
                    }
                }
            });
        }
    }
};
</script>

<style scoped>
.modal-overlay {
    z-index: 50;
}

.modal-container {
    overflow: hidden;
    width: 100%;
}

.modal-body {
    padding: 16px;
}

.form-actions .btn {
    min-width: 140px;
}

.modal-subtitle {
    font-size: 0.78rem;
    color: #6b8c85;
    font-weight: 500;
}

/* Step 2 summary */
.remit-total-banner {
    display: flex;
    align-items: stretch;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.remit-total-banner-left {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.1rem 1.4rem;
    background: linear-gradient(135deg, #1f6f57 0%, #2c8a6c 100%);
    color: #fff;
    min-width: 0;
}

.remit-total-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.remit-total-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    opacity: 0.85;
}

.remit-total-amount {
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1.25;
}

.remit-total-banner-right {
    width: 120px;
    flex-shrink: 0;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #cfe9df 0%, #eef8f4 100%);
    color: rgba(31, 111, 87, 0.35);
    font-size: 2.4rem;
}

.remit-total-check {
    position: absolute;
    bottom: 14px;
    right: 14px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #1f6f57;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}

.remit-cards-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.remit-card {
    flex: 1;
    min-width: 0;
    background: #fff;
    border: 1px solid #e6ede9;
    border-radius: 12px;
    padding: 1rem 1.1rem;
}

.remit-card-wide {
    flex: 1.4;
}

.remit-card-full {
    width: 100%;
}

.remit-card-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #16322e;
    margin-bottom: 0.9rem;
}

.remit-card-icon {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: rgba(61, 141, 122, 0.12);
    color: #3d8d7a;
    font-size: 0.85rem;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.remit-card-body-row {
    display: flex;
    gap: 1.25rem;
}

.remit-stat {
    flex: 1;
}

.remit-stat-label {
    font-size: 0.78rem;
    color: #6b8c85;
    margin-bottom: 0.2rem;
}

.remit-stat-value {
    font-size: 1.15rem;
    font-weight: 700;
    color: #16322e;
}

.remit-ar-row {
    display: flex;
    gap: 1rem;
}

.remit-ar-tile {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    background: #f4f9f7;
    border-radius: 10px;
    padding: 0.8rem 1rem;
}

.remit-ar-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: rgba(61, 141, 122, 0.12);
    color: #3d8d7a;
    font-size: 1rem;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .modal-container {
        max-height: 85vh;
        overflow-y: auto;
        margin: 0 10px;
    }

    .remit-cards-row,
    .remit-ar-row {
        flex-direction: column;
    }

    .remit-total-banner {
        flex-direction: column;
    }

    .remit-total-banner-right {
        width: 100%;
        height: 48px;
        font-size: 1.6rem;
    }
}
</style>
