<template>
    <div v-show="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <!-- Wide enough for the items table and the payment lines to sit on
             one row each; still the full screen width on a phone. -->
        <div class="modal-container" style="width: min(860px, 96vw); max-width: 96vw;" @click.stop>
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

                    <!-- One tender or several. A customer paying part cash and
                         part cheque at the door is ordinary; the server has
                         always taken the lines, only this screen could not
                         send them. -->
                    <label v-if="collectedNow" class="split-toggle">
                        <input type="checkbox" v-model="isSplit" @change="onToggleSplit">
                        <span>Paid two ways or more</span>
                    </label>

                    <div v-if="collectedNow && !isSplit" class="collect-fields">
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

                    <div v-if="collectedNow && isSplit" class="split-lines">
                        <div v-for="(line, index) in form.collected_splits" :key="index" class="split-line">
                            <select class="form-control" v-model="line.payment_mode">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                            </select>
                            <input
                                type="number" step="0.01" min="0" class="form-control"
                                placeholder="0.00" v-model.number="line.amount"
                            >
                            <input
                                v-if="line.payment_mode !== 'Cash'"
                                type="text" class="form-control"
                                :placeholder="line.payment_mode === 'Check' ? 'Check number' : 'Reference number'"
                                v-model="line.reference_number"
                            >
                            <input
                                v-if="line.payment_mode === 'Check'"
                                type="date" class="form-control" v-model="line.check_date"
                            >
                            <button
                                type="button" class="split-remove"
                                :disabled="form.collected_splits.length < 2"
                                @click="removeSplit(index)"
                                aria-label="Remove this line"
                            >
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>

                        <button type="button" class="split-add" @click="addSplit">
                            <i class="ri-add-line"></i> Add another
                        </button>

                        <p class="split-total" :class="{ over: splitTotal > collectableTotal }">
                            <span>{{ formatCurrency(splitTotal) }} of {{ formatCurrency(collectableTotal) }}</span>
                            <span v-if="splitTotal > collectableTotal">That is more than is owed.</span>
                            <span v-else-if="isCod && splitTotal < collectableTotal">
                                COD is paid in full on handover — reduce the accepted quantities for anything
                                going back.
                            </span>
                        </p>
                        <p class="collect-note" v-if="form.collected_splits.some(l => l.payment_mode !== 'Cash')">
                            Anything but cash is recorded now and counts against the invoice once someone
                            matches it in the bank.
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
                    {{ form.processing ? 'Saving...' : (order?.delivered_at ? 'Record Collection' : 'Submit for Approval') }}
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
            // One tender, or several lines that add up to what was handed over.
            isSplit: false,
            form: useForm({
                action: 'mark-delivered',
                delivered_at: null,
                collected_amount: null,
                collected_mode: 'Cash',
                collected_reference: null,
                collected_check_date: null,
                collected_splits: [],
                accepted_quantities: {},
                refusal_reasons: {},
            }),
        };
    },
    computed: {
        /** What the lines add up to, against what is owed. */
        splitTotal() {
            return (this.form.collected_splits || [])
                .reduce((sum, line) => sum + (Number(line.amount) || 0), 0);
        },
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
        /** Start the lines from whatever the single field already said. */
        onToggleSplit() {
            if (this.isSplit) {
                this.form.collected_splits = [
                    {
                        payment_mode: this.form.collected_mode || 'Cash',
                        amount: this.form.collected_amount ?? null,
                        reference_number: this.form.collected_reference,
                        check_date: this.form.collected_check_date,
                    },
                    { payment_mode: 'Cash', amount: null, reference_number: null, check_date: null },
                ];
                return;
            }

            // Back to one tender: keep the first line's details rather than
            // emptying the field the user already filled in.
            const first = this.form.collected_splits[0] || {};
            this.form.collected_mode = first.payment_mode || 'Cash';
            this.form.collected_amount = first.amount ?? null;
            this.form.collected_reference = first.reference_number ?? null;
            this.form.collected_check_date = first.check_date ?? null;
            this.form.collected_splits = [];
        },
        addSplit() {
            this.form.collected_splits.push({
                payment_mode: 'Cash', amount: null, reference_number: null, check_date: null,
            });
        },
        removeSplit(index) {
            if (this.form.collected_splits.length < 2) return;
            this.form.collected_splits.splice(index, 1);
        },
        submit() {
            // Nothing collected unless the box is ticked, whatever was typed.
            if (!this.collectedNow) {
                this.form.collected_amount = null;
                this.form.collected_splits = [];
            }

            // The server adds the lines up and names the mode Split; sending
            // the single fields as well would only invite them to disagree.
            if (this.collectedNow && this.isSplit) {
                this.form.collected_splits = this.form.collected_splits
                    .filter(line => Number(line.amount) > 0 && line.payment_mode);
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
            this.isSplit = false;
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
    color: var(--brt-ink);
    margin: 0;
}

.delivery-date-input {
    width: auto;
    min-width: 165px;
}

.delivery-date-hint {
    color: var(--brt-muted);
    font-size: 0.78rem;
}

.delivery-intro {
    color: var(--brt-muted);
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
    color: var(--brt-muted);
    border-bottom: 1px solid var(--brt-border);
    padding: 0.5rem 0.6rem;
    text-align: left;
}

.delivery-table td {
    padding: 0.7rem 0.6rem;
    border-bottom: 1px solid var(--brt-tint-2);
    vertical-align: middle;
}

.batch-note {
    display: block;
    color: var(--brt-muted);
    font-size: 0.75rem;
}

.qty-input {
    width: 84px;
    margin: 0 auto;
    text-align: center;
}

.all-taken {
    color: var(--brt-muted);
    font-size: 0.8rem;
}

.collect-box {
    margin-top: 1rem;
    padding: 0.85rem 1rem;
    border: 1px solid var(--brt-border);
    border-radius: 10px;
    background: #f7fbf9;
}

.collect-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--brt-ink);
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
    color: var(--brt-muted);
}

.collect-field small {
    color: var(--brt-muted);
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
    color: var(--brt-muted);
    font-size: 0.78rem;
}

.refusal-note {
    margin-top: 1rem;
    padding: 0.7rem 0.9rem;
    border-radius: 10px;
    background: rgba(var(--brt-primary-rgb), 0.08);
    color: var(--brt-ink);
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
.split-toggle {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin: 0.65rem 0 0.35rem;
    font-size: 0.82rem;
    cursor: pointer;
}
.split-lines { display: flex; flex-direction: column; gap: 0.45rem; }
.split-line {
    display: grid;
    grid-template-columns: 9rem 8rem 1fr auto;
    gap: 0.4rem;
    align-items: center;
}
.split-line .form-control { font-size: 0.82rem; padding: 0.3rem 0.5rem; }
.split-remove {
    border: 1px solid #e5c9c7;
    background: #fff;
    color: #96231f;
    border-radius: 8px;
    padding: 0.25rem 0.45rem;
    cursor: pointer;
}
.split-remove:disabled { opacity: 0.35; cursor: not-allowed; }
.split-add {
    align-self: flex-start;
    border: 1px dashed #c4d9d2;
    background: #fff;
    color: #3d8d7a;
    border-radius: 8px;
    padding: 0.25rem 0.6rem;
    font-size: 0.78rem;
    cursor: pointer;
}
.split-total {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    color: #6b8c85;
}
.split-total.over { color: #96231f; font-weight: 600; }

</style>
