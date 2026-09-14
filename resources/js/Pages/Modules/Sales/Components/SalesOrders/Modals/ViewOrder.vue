<template>
    <!-- Modal chrome comes from _library-modal.scss; nothing here restyles it. -->
    <div v-if="order" class="modal-overlay active" @click.self="$emit('close')">
        <div class="modal-container so-view-modal">
            <div class="modal-header">
                <div class="modal-header-icon"><i class="ri-shopping-bag-line"></i></div>
                <div>
                    <h5 class="modal-title">{{ order.so_number }}</h5>
                    <p class="modal-subtitle">
                        {{ order.customer?.name || 'Walk-in customer' }} &middot; {{ order.order_date }}
                    </p>
                </div>
                <button class="close-btn ms-auto" @click="$emit('close')" aria-label="Close">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm ">
                            <div class="card-body">
                                <h6 class="card-title text-muted small mb-2">Order
                                    Information</h6>
                                <p class="mb-1"><strong>Order Date:</strong> {{
                                    order.order_date }}</p>
                                <p class="mb-1"><strong>Added By:</strong> {{
                                    order.added_by?.fullname || '-' }}</p>
                                <p class="mb-1"><strong>Sales Rep:</strong> {{
                                    order.sales_rep?.fullname || '-' }}</p>
                                <p class="mb-0" :class="{ 'mb-1': order.status?.slug === 'cancelled' }"><strong>Transferred To:</strong> {{
                                    order.transferred_to || '-' }}</p>
                                <p v-if="order.status?.slug === 'cancelled' && order.cancellation_remarks" class="mb-0">
                                    <strong>Void Reason:</strong> {{ order.cancellation_remarks }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card items-card">
                            <div class="info-card-header">
                                <i class="ri-shopping-bag-line"></i>
                                <h6>Items</h6>
                            </div>
                            <div class="info-card-body">
                                <div v-if="order.items && order.items.length > 0">
                                    <table class="table table-sm table-borderless mb-0">
                                        <thead>
                                            <tr>
                                                <th class="fw-semibold">Product Name</th>
                                                <th class="fw-semibold">Batch Code</th>
                                                <th class="fw-semibold">Quantity</th>
                                                <th class="fw-semibold">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="item in order.items" :key="item.id">
                                                <td>{{ getProduct(item.product_id).name || 'Unknown Product' }}</td>
                                                <td>{{ item.batch_code || '-' }}</td>
                                                <td>
                                                    <span class="badge bg-primary">{{ item.quantity }} {{ item.unit }}</span>
                                                </td>
                                                <td>₱{{ item.price }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="text-muted mb-0">No items found</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12" v-if="order.invoices && order.invoices.some(inv => inv.receipts && inv.receipts.length > 0)">
                        <div class="info-card">
                            <div class="info-card-header">
                                <i class="ri-receipt-line"></i>
                                <h6>Payment History</h6>
                            </div>
                            <div class="info-card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <thead>
                                        <tr>
                                            <th class="fw-semibold">OR Number</th>
                                            <th class="fw-semibold">Date</th>
                                            <th class="fw-semibold">Sales Rep</th>
                                            <th class="fw-semibold">Amount Paid</th>
                                            <th class="fw-semibold">Mode</th>
                                            <th class="fw-semibold">Type</th>
                                            <th class="fw-semibold">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="inv in order.invoices" :key="inv.id">
                                            <tr v-for="receipt in inv.receipts" :key="receipt.id">
                                                <td class="fw-semibold">{{ receipt.receipt_number }}</td>
                                                <td>{{ receipt.receipt_date }}</td>
                                                <td>{{ receipt.sales_rep?.fullname || order.sales_rep?.fullname || '-' }}</td>
                                                <td>₱{{ receipt.amount_paid }}</td>
                                                <td>{{ receipt.payment_mode || '-' }}</td>
                                                <td>
                                                    <span class="badge" :class="getReceiptTypeBadge(receipt.receipt_type)">
                                                        {{ getReceiptTypeLabel(receipt.receipt_type) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span v-if="receipt.status" class="status-badge" :class="statusTone(receipt.status)">
                                                        {{ receipt.status.name }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="acct-btn-secondary" @click="$emit('close')">Close</button>
            </div>
        </div>
    </div>
</template>

<script>
import { statusTone } from '@/Shared/utils/statusTone.js';

export default {
    name: 'ViewOrder',
    props: {
        /** The order to show; null keeps the modal closed. */
        order: { type: Object, default: null },
        /** Needed to name the products on each line. */
        dropdowns: { type: Object, default: () => ({ products: [] }) },
    },
    emits: ['close'],
    mounted() {
        window.addEventListener('keydown', this.onKey);
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.onKey);
    },
    methods: {
        // Same tone map as every other Sales screen, so a status looks the same
        // here as it did in the row this modal replaced.
        statusTone,
        getProduct(productId) {
            return (this.dropdowns?.products ?? []).find((p) => p.value === productId) ?? {};
        },
        getReceiptTypeBadge(type) {
            if (type === 'updated') return 'bg-info text-dark';
            if (type === 'refund') return 'bg-warning text-dark';
            return 'bg-primary';
        },
        getReceiptTypeLabel(type) {
            if (type === 'updated') return 'Adjusted Payment';
            if (type === 'refund') return 'Return Refund';
            return 'Payment';
        },
        onKey(e) {
            if (e.key === 'Escape' && this.order) this.$emit('close');
        },
        formatCurrency(value) {
            return `PHP ${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        },
    },
};
</script>

<style scoped>
/* Wider than a form modal: this one holds three tables. The flex column and
   scrolling body are the app's sticky-footer structure, so a long order scrolls
   its contents instead of pushing Close out of reach. */
.so-view-modal {
    max-width: 900px;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}
.so-view-modal .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}
.so-view-modal .modal-footer {
    flex-shrink: 0;
}
</style>
