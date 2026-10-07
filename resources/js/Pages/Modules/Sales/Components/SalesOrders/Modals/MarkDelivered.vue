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

                <div class="delivery-date-row">
                    <label class="delivery-date-label" for="delivered_at">Date delivered</label>
                    <input
                        id="delivered_at"
                        type="date"
                        class="form-control delivery-date-input"
                        v-model="form.delivered_at"
                        :max="today"
                        :min="order?.order_date_raw"
                    />
                    <small class="delivery-date-hint">
                        The day the goods arrived, not the day you record it. Taken from the order's delivery date.
                    </small>
                </div>

                <div v-if="form.errors.delivered_at" class="error-alert">
                    <i class="ri-error-warning-line me-1"></i> {{ form.errors.delivered_at }}
                </div>

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

                <div class="collect-box">
                    <!-- On a COD order this is not optional: the goods only
                         leave the truck if the money does. Anything the
                         customer is not paying for comes off the accepted
                         quantities above and goes back to the warehouse. -->
                    <label class="collect-toggle">
                        <input
                            type="checkbox"
                            id="collected_now"
                            v-model="collectedNow"
                            :disabled="isCod"
                        />
                        <span v-if="isCod">Payment collected at the door &mdash; required for COD</span>
                        <span v-else>The driver collected payment at the door</span>
                    </label>
                    <p v-if="isCod" class="collect-rule">
                        COD is paid on handover. If the customer is taking less than was loaded,
                        reduce the accepted quantities &mdash; the rest returns to the warehouse and
                        comes off what they owe.
                    </p>

                    <div v-if="collectedNow" class="collect-fields">
                        <div class="collect-field">
                            <label for="collected_amount">Amount</label>
                            <input
                                id="collected_amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="collectableTotal"
                                class="form-control"
                                v-model.number="form.collected_amount"
                            />
                            <small>Owed after this delivery: {{ formatCurrency(collectableTotal) }}</small>
                        </div>
                        <div class="collect-field">
                            <label for="collected_mode">Paid by</label>
                            <select id="collected_mode" class="form-control" v-model="form.collected_mode">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                            </select>
                        </div>
                        <div class="collect-field" v-if="form.collected_mode !== 'Cash'">
                            <label for="collected_reference">
                                {{ form.collected_mode === 'Check' ? 'Check number' : 'Reference number' }}
                            </label>
                            <input id="collected_reference" type="text" class="form-control" v-model="form.collected_reference" />
                        </div>
                        <div class="collect-field" v-if="form.collected_mode === 'Check'">
                            <label for="collected_check_date">Check date</label>
                            <input id="collected_check_date" type="date" class="form-control" v-model="form.collected_check_date" />
                        </div>
                        <p class="collect-note" v-if="form.collected_mode !== 'Cash'">
                            Recorded now, but it only counts against the invoice once someone matches it in the bank.
                        </p>
                    </div>
                </div>

                <div v-if="form.errors.collected_amount" class="error-alert">
                    <i class="ri-error-warning-line me-1"></i> {{ form.errors.collected_amount }}
                </div>

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
            collectedNow: false,
            form: useForm({
                action: 'mark-delivered',
                delivered_at: null,
                collected_amount: null,
                collected_mode: 'Cash',
                collected_reference: null,
                collected_check_date: null,
                accepted_quantities: {},
                refusal_reasons: {},
            }),
        };
    },
    computed: {
        today() {
            return new Date().toISOString().slice(0, 10);
        },
        /** COD is paid on handover, so the collection is not optional. */
        isCod() {
            return String(this.order?.payment_mode || '').trim().toLowerCase() === 'cod';
        },
        /**
         * What the customer still owes, from the invoice rather than recomputed
         * from item prices: a part payment may already have been recorded.
         */
        balanceDue() {
            const invoice = this.order?.invoices?.[0];
            return invoice ? Number(invoice.balance_due) || 0 : 0;
        },
        /** The most this delivery can collect: what is kept, capped by what is owed. */
        collectableTotal() {
            const accepted = this.items.length ? this.acceptedTotal : this.balanceDue;
            return Math.min(accepted, this.balanceDue || accepted);
        },
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
    watch: {
        // Default to the whole amount owed, which is the common case; the
        // number stays editable for a part payment.
        collectedNow(on) {
            if (on && !this.form.collected_amount) {
                this.form.collected_amount = Number(this.collectableTotal.toFixed(2));
            }
        },
    },
    methods: {
        show(order) {
            this.order = order;
            this.items = Array.isArray(order?.items) ? order.items : [];
            this.form.clearErrors();
            this.form.delivered_at = this.defaultDeliveredAt(order);
            // COD cannot be delivered unpaid, so it opens with the collection
            // already on rather than asking a question with only one answer.
            this.collectedNow = String(order?.payment_mode || '').trim().toLowerCase() === 'cod';
            this.form.collected_amount = null;
            this.form.collected_mode = 'Cash';
            this.form.collected_reference = null;
            this.form.collected_check_date = null;
            // Prefilled with the full quantity: accepting everything, the common
            // case, stays a single click.
            this.form.accepted_quantities = {};
            this.form.refusal_reasons = {};
            this.items.forEach((item) => {
                this.form.accepted_quantities[item.id] = Number(item.quantity);
            });
            // Set here rather than leaving it to the collectedNow watcher: that
            // only fires when the flag changes, so opening a COD order straight
            // after another one left the amount blank. Last, because
            // collectableTotal reads the accepted quantities set just above.
            if (this.collectedNow) {
                this.form.collected_amount = Number(this.collectableTotal.toFixed(2));
            }
            this.showModal = true;
        },
        /**
         * The date planned when the order was encoded is usually the day it
         * actually arrived, so it is the default. A plan still in the future
         * cannot be a delivery that happened, and nothing can predate the
         * order, so either way it falls back to today.
         */
        defaultDeliveredAt(order) {
            const planned = order?.delivery_date_raw;
            if (!planned) return this.today;
            if (planned > this.today) return this.today;
            if (order?.order_date_raw && planned < order.order_date_raw) return this.today;
            return planned;
        },
        isShort(item) {
            return Number(this.form.accepted_quantities[item.id] ?? item.quantity) < Number(item.quantity);
        },
        submit() {
            // Nothing collected unless the box is ticked, whatever was typed.
            if (!this.collectedNow) {
                this.form.collected_amount = null;
            }
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
.delivery-date-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-bottom: 1rem;
}

.delivery-date-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #16322e;
    margin: 0;
}

.delivery-date-input {
    width: auto;
    min-width: 165px;
}

.delivery-date-hint {
    color: #6b8c85;
    font-size: 0.78rem;
}

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

.collect-box {
    margin-top: 1rem;
    padding: 0.85rem 1rem;
    border: 1px solid #c4d9d2;
    border-radius: 10px;
    background: #f7fbf9;
}

.collect-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: #16322e;
    margin: 0;
    cursor: pointer;
}

.collect-fields {
    display: flex;
    flex-wrap: wrap;
    gap: 0.9rem;
    margin-top: 0.8rem;
}

.collect-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 150px;
}

.collect-field label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b8c85;
}

.collect-field small {
    color: #6b8c85;
    font-size: 0.72rem;
}

.collect-note {
    flex-basis: 100%;
    margin: 0;
    color: #b0702a;
    font-size: 0.8rem;
}

/* Why the collection cannot be switched off on a COD order. */
.collect-rule {
    margin: 0.45rem 0 0;
    color: #6b8c85;
    font-size: 0.78rem;
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
