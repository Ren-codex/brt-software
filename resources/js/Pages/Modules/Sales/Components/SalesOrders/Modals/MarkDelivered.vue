<template>
    <div v-show="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
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
                    <p v-if="isAlreadyPaid" class="collect-paid-note">
                        <i class="ri-checkbox-circle-fill"></i>
                        This order is already paid in full &mdash; just record the delivery.
                    </p>
                    <template v-else>
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

                    <div v-if="collectedNow" class="collect-payment-section">
                        <div class="payment-card">
                            <div class="split-control">
                                <span class="split-title">
                                    Split Payment
                                    <i class="ri-question-line" title="Enable if payment was made using multiple methods."></i>
                                </span>
                                <div class="split-switch-row">
                                    <label class="switch">
                                        <input type="checkbox" v-model="isSplitPayment" @change="onToggleSplit">
                                        <span class="slider"></span>
                                    </label>
                                    <span class="split-desc">Enable if payment was made using multiple methods.</span>
                                </div>
                            </div>

                            <div class="payment-table">
                                <div class="payment-table-header">
                                    <span class="col-num">#</span>
                                    <span class="col-mode">Mode of Payment</span>
                                    <span class="col-amount">Amount</span>
                                    <span class="col-action">Action</span>
                                </div>
                                <div class="payment-table-row" v-for="(split, index) in splits" :key="index">
                                    <span class="col-num">{{ index + 1 }}</span>
                                    <div class="col-mode">
                                        <div class="mode-select-wrap">
                                            <i class="mode-icon" :class="modeIcon(split.payment_mode)"></i>
                                            <select class="field-select" v-model="split.payment_mode">
                                                <option v-for="mode in paymentModes" :key="mode" :value="mode">{{ mode }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-amount">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="field-input"
                                            v-model.number="split.amount"
                                            :disabled="!isSplitPayment"
                                        >
                                    </div>
                                    <div class="col-action">
                                        <button
                                            type="button"
                                            class="row-delete-btn"
                                            :disabled="!isSplitPayment || splits.length <= 1"
                                            @click="removeSplit(index)"
                                        >
                                            <i class="ri-delete-bin-6-line"></i>
                                        </button>
                                    </div>

                                    <div v-if="needsBankDetails(split)" class="split-bank-details">
                                        <div v-if="split.payment_mode === 'Bank Transfer' || (split.payment_mode === 'Check' && bankAccounts.length)" class="split-bank-field">
                                            <label class="split-bank-label">
                                                {{ split.payment_mode === 'Check' ? 'Deposit to bank' : 'Bank account' }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <select class="field-select" v-model="split.bank_account_id">
                                                <option :value="null">{{ split.payment_mode === 'Check' ? 'Select bank' : 'Select bank account' }}</option>
                                                <option v-for="bank in bankAccounts" :key="bank.id" :value="bank.id">
                                                    {{ bank.bank_name }} — {{ bank.account_name }}
                                                </option>
                                            </select>
                                            <small v-if="bankAccountsError" class="split-bank-warning">{{ bankAccountsError }}</small>
                                        </div>
                                        <div v-if="split.payment_mode === 'Check'" class="split-bank-field">
                                            <label class="split-bank-label">
                                                Check date <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="date"
                                                class="field-input"
                                                v-model="split.check_date"
                                            >
                                            <small class="split-check-hint">The date written on the check — when it can be cashed.</small>
                                        </div>
                                        <div class="split-bank-field">
                                            <label class="split-bank-label">
                                                {{ split.payment_mode === 'Check' ? 'Check number' : 'Reference number' }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                class="field-input"
                                                v-model.trim="split.reference_number"
                                                :placeholder="split.payment_mode === 'Check' ? 'e.g. 000123' : 'e.g. TRN-8891'"
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="add-split-btn" v-if="isSplitPayment" @click="addSplit">
                                <i class="ri-add-circle-fill"></i> Add another payment method
                            </button>

                            <div class="invalid-feedback d-block" v-if="splitError">{{ splitError }}</div>

                            <div class="payment-summary-bar">
                                <span class="summary-icon"><i class="ri-calculator-line"></i></span>
                                <div class="summary-block">
                                    <p class="summary-label">Total Payment</p>
                                    <p class="summary-value">{{ formatCurrency(splitTotal) }}</p>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="summary-block">
                                    <p class="summary-label">Remaining Balance</p>
                                    <p class="summary-value" :class="{ 'is-negative': remainingBalance < 0 }">{{ formatCurrency(remainingBalance) }}</p>
                                </div>
                                <div class="summary-status" :class="statusClass">
                                    <i :class="statusIcon"></i>
                                    <span>{{ summaryStatusText }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    </template>
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
                    {{ form.processing ? 'Saving...' : (order?.delivered_at ? 'Record Collection' : 'Mark as Delivered') }}
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
            isSplitPayment: false,
            splits: [],
            bankAccounts: [],
            bankAccountsError: '',
            paymentModes: ['Cash', 'Bank Transfer', 'Check'],
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
        today() {
            return new Date().toISOString().slice(0, 10);
        },
        isCod() {
            return String(this.order?.payment_mode || '').trim().toLowerCase() === 'cod';
        },
        balanceDue() {
            const invoice = this.order?.invoices?.[0];
            return invoice ? Number(invoice.balance_due) || 0 : 0;
        },
        isAlreadyPaid() {
            // Settled before it reached the door — a transfer or a counter
            // payment — so there is nothing to collect now, only a delivery to
            // record. No invoice at all is a different case, not this one.
            const invoice = this.order?.invoices?.[0];
            return !!invoice && (Number(invoice.balance_due) || 0) <= 0;
        },
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
        splitTotal() {
            return (this.splits || []).reduce((sum, s) => sum + (Number(s.amount) || 0), 0);
        },
        remainingBalance() {
            return this.round2(this.collectableTotal - this.splitTotal);
        },
        statusClass() {
            if (this.remainingBalance === 0) return 'is-complete';
            if (this.remainingBalance < 0) return 'is-over';
            return 'is-pending';
        },
        statusIcon() {
            if (this.remainingBalance === 0) return 'ri-checkbox-circle-fill';
            if (this.remainingBalance < 0) return 'ri-error-warning-fill';
            return 'ri-time-line';
        },
        summaryStatusText() {
            if (this.remainingBalance === 0) return 'Payment covers the full balance.';
            if (this.remainingBalance < 0) return `Exceeds balance by ${this.formatCurrency(Math.abs(this.remainingBalance))}.`;
            return `${this.formatCurrency(this.remainingBalance)} remaining after this payment.`;
        },
        splitError() {
            return this.form.errors.collected_amount
                || this.form.errors.splits
                || this.form.errors.amount_paid
                || null;
        },
    },
    watch: {
        collectedNow(on) {
            if (on && !this.splits.length) {
                this.splits = [this.newSplit('Cash', this.round2(this.collectableTotal))];
                this.isSplitPayment = false;
            }
        },
    },
    methods: {
        show(order) {
            this.order = order;
            this.items = Array.isArray(order?.items) ? order.items : [];
            this.form.clearErrors();
            this.form.delivered_at = this.defaultDeliveredAt(order);
            this.collectedNow = !this.isAlreadyPaid
                && String(order?.payment_mode || '').trim().toLowerCase() === 'cod';
            this.isSplitPayment = false;
            this.form.accepted_quantities = {};
            this.form.refusal_reasons = {};
            this.items.forEach((item) => {
                this.form.accepted_quantities[item.id] = Number(item.quantity);
            });
            this.splits = this.collectedNow
                ? [this.newSplit('Cash', this.round2(this.collectableTotal))]
                : [];
            this.loadBankAccounts();
            this.showModal = true;
        },
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
        newSplit(mode = 'Cash', amount = 0) {
            return { payment_mode: mode, amount, bank_account_id: null, reference_number: '', check_date: '' };
        },
        needsBankDetails(split) {
            return ['Bank Transfer', 'Check'].includes(split.payment_mode);
        },
        onToggleSplit() {
            this.form.clearErrors();
            if (!this.isSplitPayment) {
                const first = this.splits[0] || {};
                this.splits = [{
                    ...this.newSplit(first.payment_mode || 'Cash', this.round2(this.collectableTotal)),
                    bank_account_id: first.bank_account_id ?? null,
                    reference_number: first.reference_number || '',
                    check_date: first.check_date || '',
                }];
            }
        },
        addSplit() {
            this.splits.push(this.newSplit());
        },
        removeSplit(index) {
            if (this.splits.length <= 1) return;
            this.splits.splice(index, 1);
        },
        modeIcon(mode) {
            const map = {
                'Cash': 'ri-money-peso-circle-line',
                'GCash': 'ri-smartphone-line',
                'Bank Transfer': 'ri-bank-line',
                'Check': 'ri-file-list-3-line',
            };
            return map[mode] || 'ri-bank-card-line';
        },
        async loadBankAccounts() {
            try {
                const res = await axios.get('/bank-accounts/payment-options');
                this.bankAccounts = res.data || [];
                this.bankAccountsError = '';
            } catch (e) {
                this.bankAccounts = [];
                this.bankAccountsError = e.response?.status === 403
                    ? 'You do not have permission to accept a bank transfer. Cash and check are still available.'
                    : 'Could not load bank accounts.';
            }
        },
        round2(value) {
            return Math.round((Number(value) || 0) * 100) / 100;
        },
        submit() {
            if (!this.collectedNow) {
                this.form.collected_amount = null;
                this.form.collected_mode = 'Cash';
                this.form.collected_reference = null;
                this.form.collected_check_date = null;
                this.form.collected_splits = [];
            } else {
                const lines = (this.splits || [])
                    .map(s => ({
                        payment_mode: s.payment_mode,
                        amount: Number(s.amount) || 0,
                        bank_account_id: this.needsBankDetails(s) ? (s.bank_account_id || null) : null,
                        reference_number: this.needsBankDetails(s) ? (s.reference_number || '') : null,
                        check_date: s.payment_mode === 'Check' ? (s.check_date || '') : null,
                    }))
                    .filter(s => s.amount > 0);

                if (!lines.length) {
                    this.form.errors.collected_amount = 'Add at least one payment method with an amount.';
                    return;
                }

                const missingBank = lines.find(l => l.payment_mode === 'Bank Transfer' && !l.bank_account_id);
                if (missingBank) {
                    this.form.errors.collected_amount = 'Choose which bank account the transfer landed in.';
                    return;
                }

                const missingRef = lines.find(l => ['Bank Transfer', 'Check'].includes(l.payment_mode) && !l.reference_number);
                if (missingRef) {
                    this.form.errors.collected_amount = missingRef.payment_mode === 'Check'
                        ? 'Enter the check number for the check payment.'
                        : 'Enter the reference number for the bank transfer.';
                    return;
                }

                const missingCheckDate = lines.find(l => l.payment_mode === 'Check' && !l.check_date);
                if (missingCheckDate) {
                    this.form.errors.collected_amount = 'Enter the date written on the check.';
                    return;
                }

                // Always send the lines as splits — even a single one — so the
                // bank account, reference and check date travel with it. The old
                // single-line shortcut had no field for the bank account and
                // dropped it, so a lone transfer reached the server with no bank.
                const total = lines.reduce((s, l) => s + l.amount, 0);
                this.form.collected_amount = total;
                this.form.collected_mode = lines.length === 1 ? lines[0].payment_mode : 'Split';
                this.form.collected_reference = null;
                this.form.collected_check_date = null;
                this.form.collected_splits = lines.map(l => ({
                    payment_mode: l.payment_mode,
                    amount: l.amount,
                    bank_account_id: l.bank_account_id,
                    reference_number: l.reference_number,
                    check_date: l.check_date,
                }));
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
            this.splits = [];
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

.collect-paid-note {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: #166534;
}
.collect-paid-note i { font-size: 1.1rem; }

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

.collect-rule {
    margin: 0.45rem 0 0;
    color: var(--brt-muted);
    font-size: 0.78rem;
}

.collect-payment-section {
    margin-top: 0.8rem;
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

/* Payment card */
.payment-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.1rem;
    background: #fff;
}

.split-control {
    margin-bottom: 1rem;
}

.split-title {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.82rem;
}

.split-switch-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-top: 0.4rem;
}

.split-desc {
    color: #7f8c8d;
    font-size: 0.75rem;
    max-width: 220px;
    text-align: left;
}

/* Toggle switch */
.switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
    flex-shrink: 0;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 22px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--brt-primary);
}

input:checked + .slider:before {
    transform: translateX(18px);
}

/* Payment table */
.payment-table {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.payment-table-header,
.payment-table-row {
    display: grid;
    grid-template-columns: 40px 1fr 180px 70px;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.85rem;
}

.payment-table-header {
    background: #f9fafb;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.payment-table-row {
    border-top: 1px solid var(--brt-c-eef2f1);
}

.split-bank-details {
    grid-column: 2 / -1;
    display: flex;
    gap: 0.75rem;
    margin-top: 0.1rem;
}

.split-bank-field {
    display: flex;
    flex-direction: column;
    flex: 1 1 0;
    min-width: 0;
}

.split-bank-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 0.2rem;
}

.split-bank-warning {
    color: #92400e;
    font-size: 0.7rem;
    margin-top: 0.2rem;
}

.split-check-hint {
    display: block;
    color: var(--brt-muted);
    font-size: 0.7rem;
    margin-top: 0.2rem;
}

.col-num {
    color: #94a3b8;
    font-weight: 600;
    text-align: center;
}

.col-action {
    display: flex;
    justify-content: center;
}

.field-input,
.field-select {
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 1px solid #e9ecef;
    border-radius: 9px;
    font-size: 0.85rem;
    background-color: #fff;
    transition: all 0.2s ease;
}

.field-input:focus,
.field-select:focus {
    outline: none;
    border-color: var(--brt-focus);
    box-shadow: 0 0 0 3px rgba(var(--brt-focus-rgb), 0.1);
}

.field-input:disabled {
    background: #f5f6f7;
    color: #7f8c8d;
}

.mode-select-wrap {
    position: relative;
}

.mode-icon {
    position: absolute;
    left: 0.7rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--brt-primary);
    font-size: 0.95rem;
    pointer-events: none;
}

.mode-select-wrap .field-select {
    padding-left: 2.15rem;
}

.row-delete-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #f3c6c6;
    background: #fff5f5;
    color: #e74c3c;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.row-delete-btn:hover:not(:disabled) {
    background: #fde8e8;
}

.row-delete-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.add-split-btn {
    width: 100%;
    margin-top: 0.75rem;
    padding: 0.6rem;
    border: 1px dashed var(--brt-border);
    border-radius: 10px;
    background: transparent;
    color: #267A4C;
    font-weight: 700;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: all 0.2s ease;
}

.add-split-btn:hover {
    background: var(--brt-c-f0f7f4);
}

/* Summary bar */
.payment-summary-bar {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    margin-top: 1rem;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    background: var(--brt-c-f3faf7);
}

.summary-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: rgba(var(--brt-primary-rgb), 0.12);
    color: var(--brt-primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.summary-block {
    display: flex;
    flex-direction: column;
}

.summary-label {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.72rem;
}

.summary-value {
    margin: 0;
    color: var(--brt-ink);
    font-weight: 800;
    font-size: 0.95rem;
}

.summary-value.is-negative {
    color: #dc2626;
}

.summary-divider {
    width: 1px;
    align-self: stretch;
    background: var(--brt-c-dbe7e2);
}

.summary-status {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 700;
    font-size: 0.82rem;
}

.summary-status.is-complete { color: #267A4C; }
.summary-status.is-pending { color: #d97706; }
.summary-status.is-over { color: #dc2626; }

@media (max-width: 768px) {
    .payment-table-header,
    .payment-table-row {
        grid-template-columns: 30px 1fr 130px 50px;
    }
}
</style>
