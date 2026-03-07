<template>
    <div
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-xl receipts-modal" @click.stop>
            <div class="modal-header receipts-header">
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

            <div class="modal-footer receipts-footer">
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
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1050;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    padding: 15px;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 980px;
    overflow: hidden;
    transform: translateY(25px) scale(0.95);
    transition: all 0.3s ease;
}

.modal-overlay.active .modal-container {
    transform: translateY(0) scale(1);
}

.receipts-modal {
    max-height: 90vh;
    overflow-y: auto;
}

.receipts-header {
    background: #3a8674;
    color: white;
    padding: 0.875rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.receipts-header h4 {
    color: white;
    font-weight: 700;
}

.close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.1rem;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

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

.receipts-footer {
    background: #f6faf8;
    border-top: 1px solid #dde9e4;
    padding: 12px 16px;
}

.receipts-footer .btn {
    margin-left: 8px;
}

@media (max-width: 991px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
