<template>
    <div
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-xl" @click.stop>
            <div class="modal-header">
                <h4 class="mb-0">
                    <i class="ri-file-list-3-line me-2"></i>
                    Invoice Receipts
                </h4>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>

            <div class="modal-body p-4">
                <div class="summary-grid mb-3">
                    <div class="summary-card">
                        <small class="summary-label">Invoice #</small>
                        <strong class="summary-value">{{ invoice?.invoice_number || "-" }}</strong>
                    </div>
                    <div class="summary-card">
                        <small class="summary-label">Sales Order</small>
                        <strong class="summary-value">{{ invoice?.sales_order?.so_number || "-" }}</strong>
                    </div>
                    <div class="summary-card">
                        <small class="summary-label">Customer</small>
                        <strong class="summary-value">{{ invoice?.sales_order?.customer?.name || "-" }}</strong>
                    </div>
                    <div class="summary-card">
                        <small class="summary-label">Total Receipts</small>
                        <strong class="summary-value text-primary">{{ receipts.length }}</strong>
                    </div>
                    <div class="summary-card">
                        <small class="summary-label">Collected</small>
                        <strong class="summary-value text-success">{{ formatCurrency(totalPaid) }}</strong>
                    </div>
                </div>

                <div class="table-wrap">
                    <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 pretty-table">
                        <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th>Receipt No.</th>
                                <th>Date</th>
                                <th class="text-end">Amount Paid</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="receipts.length === 0">
                                <td colspan="6" class="text-center text-muted py-4">No receipts found for this invoice.</td>
                            </tr>
                            <tr v-for="(receipt, index) in receipts" :key="receipt.id">
                                <td class="text-center">{{ index + 1 }}</td>
                                <td>{{ receipt.receipt_number || "-" }}</td>
                                <td>{{ receipt.receipt_date || "-" }}</td>
                                <td class="text-end fw-semibold">{{ formatCurrency(receipt.amount_paid) }}</td>
                                <td class="text-center">
                                    <span
                                        class="badge"
                                        :style="{ backgroundColor: receipt.status?.bg_color || '#6c757d', color: '#fff' }"
                                    >
                                        {{ receipt.status?.name || "Unknown" }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-print"
                                        @click="onPrint(receipt.id)"
                                        title="Print Receipt"
                                    >
                                        <i class="ri-printer-line me-1"></i>
                                        Print
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" @click="hide">Close</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    computed: {
        totalPaid() {
            return this.receipts.reduce((sum, item) => sum + Number(item.amount_paid || 0), 0);
        }
    },
    data() {
        return {
            showModal: false,
            invoice: null,
            receipts: [],
        };
    },
    methods: {
        show(invoice) {
            this.invoice = invoice || null;
            this.receipts = invoice?.receipts || [];
            this.showModal = true;
        },
        hide() {
            this.showModal = false;
            this.invoice = null;
            this.receipts = [];
        },
        formatCurrency(value) {
            const amount = Number(value || 0);
            return `PHP ${amount.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        },
        onPrint(id) {
            if (!id) return;
            window.open(`/receipts/${id}?option=print&type=receipt`);
        }
    }
};
</script>
<style scoped>
.summary-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
}

.summary-card {
    border: 1px solid #d6e5df;
    border-radius: 12px;
    background: #f7faf9;
    padding: 10px 12px;
}

.summary-label {
    color: #5f756d;
    font-size: 12px;
}

.summary-value {
    display: block;
    color: #2a3f37;
    margin-top: 2px;
}

.table-wrap {
    border: 1px solid #dce8e3;
    border-radius: 12px;
    overflow: hidden;
}

.pretty-table thead th {
    background: #edf4f1;
    color: #49655b;
    font-size: 12px;
    font-weight: 700;
}

.pretty-table tbody tr:hover {
    background: #f7fcf9;
}

.btn-print {
    border-color: #3a8674;
    color: #2f705f;
    background: #ffffff;
    margin: 2px 4px;
    min-width: 78px;
}

.btn-print:hover {
    background: #3a8674;
    color: #ffffff;
}

@media (max-width: 991px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
