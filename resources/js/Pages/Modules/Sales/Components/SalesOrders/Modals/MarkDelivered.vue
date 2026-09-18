<template>
    <div v-show="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <div class="modal-title-wrap">
                    <div class="modal-header-icon">
                        <i class="ri-truck-line"></i>
                    </div>
                    <div>
                        <span class="modal-kicker">Delivery</span>
                        <h2>Mark {{ order?.so_number }} Delivered</h2>
                    </div>
                </div>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="modal-body">
                <p class="delivery-intro">
                    Enter what the customer actually kept. Anything less goes back to stock and the
                    invoice drops to match, so the driver collects the right amount.
                </p>

                <div v-if="form.errors.accepted_quantities" class="error-alert">
                    <i class="ri-error-warning-line me-1"></i> {{ form.errors.accepted_quantities }}
                </div>
                <div v-if="form.errors.delivered_at" class="error-alert">
                    <i class="ri-error-warning-line me-1"></i> {{ form.errors.delivered_at }}
                </div>

                <table class="delivery-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Ordered</th>
                            <th class="text-center">Accepted</th>
                            <th>Reason if short</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id">
                            <td>
                                <strong>{{ item.product_name || 'Item' }}</strong>
                                <small class="batch-note">{{ item.batch_code }}</small>
                            </td>
                            <td class="text-center">{{ item.quantity }}</td>
                            <td class="text-center">
                                <input
                                    type="number"
                                    class="form-control qty-input"
                                    :id="`accepted_${item.id}`"
                                    min="0"
                                    :max="item.quantity"
                                    v-model.number="form.accepted_quantities[item.id]"
                                />
                            </td>
                            <td>
                                <input
                                    v-if="isShort(item)"
                                    type="text"
                                    class="form-control"
                                    :id="`reason_${item.id}`"
                                    placeholder="Why was it refused?"
                                    v-model="form.refusal_reasons[item.id]"
                                />
                                <span v-else class="all-taken">All taken</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="refusedCount > 0" class="refusal-note">
                    <i class="ri-arrow-go-back-line me-1"></i>
                    {{ refusedCount }} going back to stock. This order's invoice becomes
                    <strong>{{ formatCurrency(acceptedTotal) }}</strong>.
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" @click="hide">Cancel</button>
                <button class="btn btn-primary" @click="submit" :disabled="form.processing">
                    <i class="ri-loader-4-line spinner" v-if="form.processing"></i>
                    <i class="ri-check-line" v-else></i>
                    {{ form.processing ? 'Recording...' : 'Record Delivery' }}
                </button>
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
            order: null,
            items: [],
            form: useForm({
                action: 'mark-delivered',
                accepted_quantities: {},
                refusal_reasons: {},
            }),
        };
    },
    computed: {
        acceptedTotal() {
            return this.items.reduce((total, item) => {
                const accepted = Number(this.form.accepted_quantities[item.id] ?? item.quantity);
                const unitPrice = Number(item.price || 0) - Number(item.discount_per_unit || 0);
                return total + (unitPrice * accepted);
            }, 0);
        },
        refusedCount() {
            return this.items.reduce((total, item) => {
                const accepted = Number(this.form.accepted_quantities[item.id] ?? item.quantity);
                return total + Math.max(0, Number(item.quantity) - accepted);
            }, 0);
        },
    },
    methods: {
        show(order) {
            this.order = order;
            this.items = Array.isArray(order?.items) ? order.items : [];
            this.form.clearErrors();
            // Prefilled with the full quantity: accepting everything, the common
            // case, stays a single click.
            this.form.accepted_quantities = {};
            this.form.refusal_reasons = {};
            this.items.forEach((item) => {
                this.form.accepted_quantities[item.id] = Number(item.quantity);
            });
            this.showModal = true;
        },
        isShort(item) {
            return Number(this.form.accepted_quantities[item.id] ?? item.quantity) < Number(item.quantity);
        },
        submit() {
            this.form.put(`/sales-orders/${this.order.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    this.$emit('delivered', true);
                    this.hide();
                },
            });
        },
        formatCurrency(value) {
            return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value) || 0);
        },
        hide() {
            this.showModal = false;
            this.order = null;
            this.items = [];
        },
    },
};
</script>

<style scoped>
.delivery-intro {
    color: #6b8c85;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.delivery-table {
    width: 100%;
    border-collapse: collapse;
}

.delivery-table th {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b8c85;
    border-bottom: 1px solid #c4d9d2;
    padding: 0.5rem 0.6rem;
    text-align: left;
}

.delivery-table td {
    padding: 0.7rem 0.6rem;
    border-bottom: 1px solid #edf6f2;
    vertical-align: middle;
}

.batch-note {
    display: block;
    color: #6b8c85;
    font-size: 0.75rem;
}

.qty-input {
    width: 84px;
    margin: 0 auto;
    text-align: center;
}

.all-taken {
    color: #6b8c85;
    font-size: 0.8rem;
}

.refusal-note {
    margin-top: 1rem;
    padding: 0.7rem 0.9rem;
    border-radius: 10px;
    background: rgba(61, 141, 122, 0.08);
    color: #16322e;
    font-size: 0.9rem;
}

.error-alert {
    margin-bottom: 0.8rem;
    padding: 0.6rem 0.8rem;
    border-radius: 8px;
    background: #fdecea;
    color: #9b1c1c;
    font-size: 0.85rem;
}
</style>
