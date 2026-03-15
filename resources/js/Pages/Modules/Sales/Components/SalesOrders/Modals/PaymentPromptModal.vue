<template>
    <div v-if="show" class="modal-overlay active payment-review-modal" @click.self="$emit('cancel')">
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header bg-primary text-white">
                <h4 class="mb-0 text-white">
                    <i class="ri-money-dollar-circle-line me-2"></i>
                    Proceed Payment
                </h4>
                <button class="close-btn text-white" @click="$emit('cancel')">
                    <i class="ri-close-line fs-20"></i>
                </button>
            </div>
            <div class="modal-body p-4 payment-prompt-body">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 text-primary">
                            <i class="ri-file-list-line me-2"></i>
                            Invoice Summary
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Sales Order #:</strong> {{ salesOrder?.so_number || '-' }}</p>
                                <p class="mb-1"><strong>Order Date:</strong> {{ salesOrder?.order_date || '-' }}</p>
                                <p class="mb-1"><strong>Customer:</strong> {{ salesOrder?.customer?.name || '-' }}</p>
                                <p class="mb-1"><strong>Location:</strong> {{ getLocationName(salesOrder?.location_id) }}</p>
                                <p class="mb-0"><strong>Payment Mode:</strong> {{ salesOrder?.payment_mode || '-' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Sales Rep:</strong> {{ getSalesRepName(salesOrder?.sales_rep_id) }}</p>
                                <p class="mb-1"><strong>Driver:</strong> {{ getDriverName(salesOrder?.driver_id) }}</p>
                                <p class="mb-1"><strong>Invoice #:</strong> {{ invoice?.invoice_number || '-' }}</p>
                                <p class="mb-1"><strong>Invoice Date:</strong> {{ invoice?.invoice_date || '-' }}</p>
                                <p class="mb-0"><strong>Amount Due:</strong> {{ formatCurrency(invoice?.balance_due || 0) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mt-3" v-if="salesOrder?.items?.length">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 text-primary">
                            <i class="ri-shopping-bag-line me-2"></i>
                            Order Items
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-2">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center">Batch</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Discount</th>
                                        <th class="text-end">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in salesOrder.items" :key="idx">
                                        <td>{{ getPromptItemName(item) }}</td>
                                        <td class="text-center">{{ item.batch_code || '-' }}</td>
                                        <td class="text-center">{{ item.quantity || 0 }}</td>
                                        <td class="text-end">{{ formatCurrency(item.price || 0) }}</td>
                                        <td class="text-end">{{ formatCurrency((item.discount_per_unit || 0) * (item.quantity || 0)) }}</td>
                                        <td class="text-end">{{ formatCurrency(calculateItemTotal(item) - calculateDiscountedTotal(item)) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end">
                            <div class="prompt-totals">
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal:</span>
                                    <strong>{{ formatCurrency(promptSubtotal) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Discount:</span>
                                    <strong>{{ formatCurrency(promptDiscount) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between border-top pt-1 mt-1">
                                    <span>Net Total:</span>
                                    <strong>{{ formatCurrency(promptNetTotal) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-muted mt-3 mb-0">
                    This order is <strong>Cash</strong>. Do you want to record payment now?
                </p>
                <span class="error-message" v-if="errorMessage">{{ errorMessage }}</span>
            </div>
            <div class="modal-footer bg-light border-0 p-4">
                <button type="button" class="btn btn-outline-secondary me-3" @click="$emit('cancel')" :disabled="processing">
                    <i class="ri-close-line me-2"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" @click="$emit('proceed')" :disabled="processing || !invoice?.id">
                    <i class="ri-loader-4-line spinner" v-if="processing"></i>
                    <i class="ri-check-line me-2" v-else></i>
                    {{ processing ? 'Processing...' : 'Record Payment & Continue' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    emits: ['cancel', 'proceed'],
    props: {
        show: { type: Boolean, default: false },
        processing: { type: Boolean, default: false },
        errorMessage: { type: String, default: null },
        salesOrder: { type: Object, default: null },
        invoice: { type: Object, default: null },
        dropdowns: { type: Object, required: true },
    },
    computed: {
        promptSubtotal() {
            if (!this.salesOrder?.items) return 0;
            return this.salesOrder.items.reduce((total, item) => total + this.calculateItemTotal(item), 0);
        },
        promptDiscount() {
            if (!this.salesOrder?.items) return 0;
            return this.salesOrder.items.reduce((total, item) => total + this.calculateDiscountedTotal(item), 0);
        },
        promptNetTotal() {
            const backendTotal = parseFloat(this.salesOrder?.total_amount);
            if (!isNaN(backendTotal)) return backendTotal;
            return this.promptSubtotal - this.promptDiscount;
        },
    },
    methods: {
        getProduct(productId) {
            return this.dropdowns?.products?.find((product) => product.value === productId) || null;
        },
        getSalesRepName(employeeId) {
            if (!employeeId) return '-';
            const rep = this.dropdowns?.sales_reps?.find((employee) => employee.value === employeeId);
            return rep ? rep.name : '-';
        },
        getDriverName(employeeId) {
            if (!employeeId) return '-';
            const driver = this.dropdowns?.drivers?.find((employee) => employee.value === employeeId);
            return driver ? driver.name : '-';
        },
        getLocationName(locationId) {
            if (!locationId) return '-';
            const location = this.dropdowns?.locations?.find((entry) => entry.value === locationId);
            return location ? location.name : '-';
        },
        getPromptItemName(item) {
            if (!item?.product_id) return '-';
            const product = this.getProduct(item.product_id);
            return product?.name || `Product #${item.product_id}`;
        },
        formatCurrency(value) {
            if (!value) return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(0);
            const num = Number(value);
            if (isNaN(num)) {
                return new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(0);
            }
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(num);
        },
        calculateItemTotal(item) {
            const price = parseFloat(item.price) || 0;
            const quantity = parseFloat(item.quantity) || 0;
            return price * quantity;
        },
        calculateDiscountedTotal(item) {
            const discount = parseFloat(item.discount_per_unit) || 0;
            const quantity = parseFloat(item.quantity) || 0;
            return discount * quantity;
        },
    },
};
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    z-index: 1060;
}

.modal-container {
    width: 100%;
    position: relative;
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
}

.payment-review-modal .modal-container {
    max-width: 900px;
    width: 95%;
}

.payment-review-modal .modal-header {
    border-radius: 20px 20px 0 0;
    background: #C4DAD2 !important;
    border-bottom: 1px solid #e9ecef;
}

.payment-review-modal .modal-header h4 {
    color: #16423C !important;
    font-weight: 700;
}

.payment-review-modal .close-btn {
    background: rgba(255, 255, 255, 0.25);
    color: #16423C !important;
}

.payment-review-modal .close-btn:hover {
    background: rgba(255, 255, 255, 0.35);
}

.payment-review-modal .card {
    border-radius: 12px;
}

.payment-review-modal .card-header {
    border-radius: 12px 12px 0 0 !important;
}

.payment-prompt-body {
    height: 75vh;
    overflow-y: auto;
}

.prompt-totals {
    min-width: 260px;
}

.spinner {
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
