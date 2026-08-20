<template>
    <div
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="modal-header-icon">
                        <i class="ri-secure-payment-line"></i>
                    </span>
                    <div>
                        <h4 class="mb-0">Record Invoice Payment</h4>
                        <p class="modal-subtitle mb-0">Apply payment to this invoice and generate a receipt.</p>
                    </div>
                </div>
                <button type="button" class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <!-- Invoice Overview -->
                <p class="section-label"><i class="ri-file-text-line"></i> {{ invoice?.invoice_number || 'N/A' }}</p>
                <div class="overview-card">
                    <div class="overview-items">
                        <!-- <div class="overview-item">
                            <i class="ri-hashtag overview-icon"></i>
                            <div>
                                <p class="overview-label">Invoice #</p>
                                <p class="overview-value">{{ invoice?.invoice_number || 'N/A' }}</p>
                            </div>
                        </div> -->
                        <div class="overview-item">
                            <i class="ri-user-line overview-icon"></i>
                            <div>
                                <p class="overview-label">Customer</p>
                                <p class="overview-value">{{ invoice?.sales_order?.customer?.name || 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="overview-item">
                            <i class="ri-calendar-line overview-icon"></i>
                            <div>
                                <p class="overview-label">Due Date</p>
                                <p class="overview-value">{{ invoice?.due_date_formatted || invoice?.invoice_date || 'N/A' }}</p>
                                <p class="due-badge" :class="dueInfo.cls" v-if="dueInfo">{{ dueInfo.text }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="balance-box">
                        <i class="ri-wallet-3-line balance-decor"></i>
                        <p class="balance-label">Outstanding Balance</p>
                        <p class="balance-value">{{ numberFormat(form.balance_due) }}</p>
                    </div>
                </div>

                <!-- Payment Information -->
                <p class="section-label mt-4"><i class="ri-bank-card-2-line"></i> Payment Information</p>
                <div class="payment-card">
                    <div class="payment-top-row">
                        <div class="field-group">
                            <label class="form-label" for="payment_date">Payment Date</label>
                            <input
                                type="date"
                                id="payment_date"
                                class="field-input"
                                v-model="form.payment_date"
                                @input="handleInput('payment_date')"
                                :class="{ 'is-invalid': form.errors.payment_date }"
                            />
                            <div class="invalid-feedback" v-if="form.errors.payment_date">{{ form.errors.payment_date }}</div>
                        </div>

                        <div class="split-control">
                            <span class="split-title">
                                Split Payment
                                <i class="ri-question-line" title="Enable if payment will be made using multiple methods."></i>
                            </span>
                            <div class="split-switch-row">
                                <label class="switch">
                                    <input type="checkbox" v-model="isSplitPayment" @change="onToggleSplit">
                                    <span class="slider"></span>
                                </label>
                                <span class="split-desc">Enable if payment will be made using multiple methods.</span>
                            </div>
                        </div>
                    </div>

                    <div class="payment-table">
                        <div class="payment-table-header">
                            <span class="col-num">#</span>
                            <span class="col-mode">Mode of Payment</span>
                            <span class="col-amount">Amount</span>
                            <span class="col-action">Action</span>
                        </div>
                        <div class="payment-table-row" v-for="(split, index) in form.splits" :key="index">
                            <span class="col-num">{{ index + 1 }}</span>
                            <div class="col-mode">
                                <div class="mode-select-wrap">
                                    <i class="mode-icon" :class="modeIcon(split.payment_mode)"></i>
                                    <select class="field-select" v-model="split.payment_mode">
                                        <option v-for="mode in payment_modes" :key="mode" :value="mode">{{ mode }}</option>
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
                                    :disabled="!isSplitPayment && !allowsPartialPayment"
                                    @input="handleInput('splits')"
                                >
                            </div>
                            <div class="col-action">
                                <button
                                    type="button"
                                    class="row-delete-btn"
                                    :disabled="!isSplitPayment || form.splits.length <= 1"
                                    @click="removeSplit(index)"
                                >
                                    <i class="ri-delete-bin-6-line"></i>
                                </button>
                            </div>

                            <!-- Where the money actually landed. Only transfers
                                 and checks have anything to reconcile, so only
                                 they ask. -->
                            <div v-if="needsBankDetails(split)" class="split-bank-details">
                                <div v-if="split.payment_mode === 'Bank Transfer'" class="split-bank-field">
                                    <label class="split-bank-label">Bank account <span class="text-danger">*</span></label>
                                    <select class="field-select" v-model="split.bank_account_id" @change="handleInput('splits')">
                                        <option :value="null">Select bank account</option>
                                        <option v-for="bank in bankAccounts" :key="bank.id" :value="bank.id">
                                            {{ bank.bank_name }} — {{ bank.account_name }}
                                        </option>
                                    </select>
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
                                        @input="handleInput('splits')"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="add-split-btn" v-if="isSplitPayment" @click="addSplit">
                        <i class="ri-add-circle-fill"></i> Add another payment method
                    </button>

                    <small v-if="!allowsPartialPayment" class="cash-note">
                        <i class="ri-information-line"></i>
                        Cash sales require payment in full ({{ numberFormat(form.balance_due) }}).
                    </small>

                    <div class="invalid-feedback d-block" v-if="form.errors.splits">{{ form.errors.splits }}</div>

                    <div class="payment-summary-bar">
                        <span class="summary-icon"><i class="ri-calculator-line"></i></span>
                        <div class="summary-block">
                            <p class="summary-label">Total Payment</p>
                            <p class="summary-value">{{ numberFormat(splitTotal) }}</p>
                        </div>
                        <div class="summary-divider"></div>
                        <div class="summary-block">
                            <p class="summary-label">Remaining Balance</p>
                            <p class="summary-value" :class="{ 'is-negative': remainingBalance < 0 }">{{ numberFormat(remainingBalance) }}</p>
                        </div>
                        <div class="summary-status" :class="statusClass">
                            <i :class="statusIcon"></i>
                            <span>{{ summaryStatusText }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="footer-btn secondary" @click="hide">
                    <i class="ri-close-line"></i> Cancel
                </button>
                <button type="button" class="footer-btn primary" @click="submit">
                    <i class="ri-check-line"></i> Record Payment & Generate Receipt
                </button>
            </div>
        </div>
    </div>

    <div v-if="showSuccessModal" class="modal-overlay active" @click.self="closeSuccessModal">
        <div class="modal-container modal-md" @click.stop>
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="modal-header-icon">
                        <i class="ri-checkbox-circle-line"></i>
                    </span>
                    <h4 class="mb-0">Payment Successful</h4>
                </div>
                <button type="button" class="close-btn" @click="closeSuccessModal">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="payment-success-card">
                    <div class="payment-success-icon">
                        <svg class="success-checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle class="success-checkmark-circle" cx="26" cy="26" r="25" fill="none" />
                            <path class="success-checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                        </svg>
                    </div>
                    <h5>Payment recorded successfully</h5>
                    <p>The {{ successPayment.paymentModeLabel }} payment for this invoice has been recorded.</p>
                    <div class="payment-success-breakdown">
                        <div class="payment-success-row">
                            <span>Amount Paid</span>
                            <strong>{{ numberFormat(successPayment.amountPaid) }}</strong>
                        </div>
                        <div class="payment-success-row">
                            <span>Payment Mode</span>
                            <strong>{{ successPayment.paymentModeLabel }}</strong>
                        </div>
                        <div class="payment-success-row payment-success-row-highlight">
                            <span>Remaining Balance</span>
                            <strong>{{ numberFormat(successPayment.remainingBalance) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    type="button"
                    class="success-footer-btn success-footer-btn-print"
                    :disabled="!successPayment.receiptId"
                    @click="printReceipt"
                >
                    <i class="ri-printer-line me-2"></i>
                    Print Receipt
                </button>
            </div>
        </div>
    </div>
</template>
<script>

import { useForm } from '@inertiajs/vue3';

export default {
    props: [ ],
    computed: {
        normalizedPaymentMode() {
            return String(this.invoice?.sales_order?.payment_mode || '').trim().toLowerCase();
        },
        allowsPartialPayment() {
            return ['credit', 'credit sales'].includes(this.normalizedPaymentMode);
        },
        splitTotal() {
            return (this.form.splits || []).reduce((sum, s) => sum + (Number(s.amount) || 0), 0);
        },
        remainingBalance() {
            return this.round2(Number(this.form.balance_due || 0) - this.splitTotal);
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
            if (this.remainingBalance < 0) return `Exceeds balance by ${this.numberFormat(Math.abs(this.remainingBalance))}.`;
            return `${this.numberFormat(this.remainingBalance)} remaining after this payment.`;
        },
        dueInfo() {
            const dueDateValue = this.invoice?.due_date ?? this.invoice?.sales_order?.due_date;
            const balance = Number(this.invoice?.balance_due || 0);
            if (!dueDateValue || balance <= 0) return null;

            const dueDate = new Date(dueDateValue);
            if (Number.isNaN(dueDate.getTime())) return null;

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            dueDate.setHours(0, 0, 0, 0);

            const diffDays = Math.ceil((dueDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

            if (diffDays < 0) {
                const days = Math.abs(diffDays);
                return { text: `Overdue by ${days} day${days === 1 ? '' : 's'}`, cls: 'is-overdue' };
            }
            if (diffDays === 0) return { text: 'Due today', cls: 'is-soon' };
            if (diffDays <= 2) return { text: `Due in ${diffDays} day${diffDays === 1 ? '' : 's'}`, cls: 'is-soon' };
            return { text: `Due in ${diffDays} days`, cls: 'is-later' };
        },
    },
    data(){
        return {
            currentUrl: window.location.origin,
            isSplitPayment: false,
            form: useForm({
                id: null,
                action: 'payment',
                balance_due: 0.00,
                payment_date: new Date().toISOString().slice(0, 10),  // current date,
                billing_account: null,
                option: 'payment',
                splits: [],
            }),

            payment_modes: ['Cash', 'GCash', 'Bank Transfer', 'Check'],
            bankAccounts: [],
            title: null,
            showModal: false,
            invoice: null,
            showSuccessModal: false,
            successPayment: {
                amountPaid: 0,
                paymentModeLabel: '',
                remainingBalance: 0,
                receiptId: null,
            },
        }
    },

    methods: {
        show(data, title, route){
            this.showModal = true;
            this.invoice = data;
            this.isSplitPayment = false;
            this.form.id = data.id;
            this.form.balance_due = data.balance_due;
            this.form.splits = [this.newSplit('Cash', this.round2(data.balance_due))];
            this.title = title;
            this.route = route;
            this.loadBankAccounts();
        },

        newSplit(mode = 'Cash', amount = 0) {
            return { payment_mode: mode, amount, bank_account_id: null, reference_number: '' };
        },

        needsBankDetails(split) {
            return ['Bank Transfer', 'Check'].includes(split.payment_mode);
        },

        async loadBankAccounts() {
            if (this.bankAccounts.length) return;
            try {
                const res = await axios.get('/accounting/bank-accounts/list');
                this.bankAccounts = res.data || [];
            } catch {
                this.bankAccounts = [];
            }
        },

        onToggleSplit() {
            this.form.clearErrors();
            if (!this.isSplitPayment) {
                const first = this.form.splits[0] || {};
                const amount = this.allowsPartialPayment ? this.splitTotal : this.form.balance_due;
                this.form.splits = [{
                    ...this.newSplit(first.payment_mode || 'Cash', this.round2(amount)),
                    bank_account_id: first.bank_account_id ?? null,
                    reference_number: first.reference_number || '',
                }];
            }
        },

        addSplit() {
            this.form.splits.push(this.newSplit());
        },

        removeSplit(index) {
            if (this.form.splits.length <= 1) return;
            this.form.splits.splice(index, 1);
        },

        modeIcon(mode) {
            const map = {
                'Cash': 'ri-money-dollar-circle-line',
                'GCash': 'ri-smartphone-line',
                'Bank Transfer': 'ri-bank-line',
                'Check': 'ri-file-list-3-line',
            };
            return map[mode] || 'ri-bank-card-line';
        },

        submit(){
            this.form.clearErrors();

            const splits = (this.form.splits || [])
                .map(s => ({
                    payment_mode: s.payment_mode,
                    amount: Number(s.amount) || 0,
                    // Only a transfer names an account; a check clears through
                    // whichever bank it is deposited to, so it carries a number only.
                    bank_account_id: s.payment_mode === 'Bank Transfer' ? (s.bank_account_id || null) : null,
                    reference_number: this.needsBankDetails(s) ? (s.reference_number || '') : null,
                }))
                .filter(s => s.amount > 0);

            if (!splits.length) {
                this.form.errors.splits = 'Add at least one payment method with an amount.';
                return;
            }

            const missingBank = splits.find(s => s.payment_mode === 'Bank Transfer' && !s.bank_account_id);
            if (missingBank) {
                this.form.errors.splits = 'Choose which bank account the transfer landed in.';
                return;
            }

            const missingReference = splits.find(s => ['Bank Transfer', 'Check'].includes(s.payment_mode) && !s.reference_number);
            if (missingReference) {
                this.form.errors.splits = missingReference.payment_mode === 'Check'
                    ? 'Enter the check number for the check payment.'
                    : 'Enter the reference number for the bank transfer.';
                return;
            }

            const total = splits.reduce((sum, s) => sum + s.amount, 0);

            if (!this.allowsPartialPayment && Math.abs(total - this.form.balance_due) > 0.009) {
                this.form.errors.splits = 'Cash sales require the payment to cover the full outstanding balance.';
                return;
            }

            if (total > this.form.balance_due + 0.009) {
                this.form.errors.splits = 'Total payment cannot exceed the outstanding balance.';
                return;
            }

            this.form.splits = splits;

            this.form.put(`${this.route}/${this.form.id}`,{
                preserveScroll: true,
                onSuccess: (response) => {
                    const receiptId = response?.props?.flash?.receipt_id || this.$page?.props?.flash?.receipt_id || null;
                    const updatedInvoice = response?.props?.flash?.data || this.$page?.props?.flash?.data || null;
                    const remainingBalance = updatedInvoice
                        ? Number(updatedInvoice.balance_due)
                        : Math.max(this.round2(this.form.balance_due - total), 0);

                    this.successPayment = {
                        amountPaid: total,
                        paymentModeLabel: splits.length > 1 ? 'Split Payment' : (splits[0]?.payment_mode || 'Cash'),
                        remainingBalance,
                        receiptId,
                    };

                    this.$emit('approve', true);
                    this.form.splits = [];
                    this.form.reset();
                    this.hide();
                    this.showSuccessModal = true;
                },
            });

        },

        round2(value) {
            return Math.round((Number(value) || 0) * 100) / 100;
        },

        handleInput(field) {
            this.form.errors[field] = false;
        },
        hide(){
            this.showModal = false;
            this.invoice = null;
        },

        closeSuccessModal() {
            this.showSuccessModal = false;
        },

        printReceipt() {
            if (this.successPayment.receiptId) {
                window.open(`/receipts/${this.successPayment.receiptId}?option=print&type=receipt`, '_blank');
            }
            this.showSuccessModal = false;
        },

        numberFormat(value) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2
            }).format(value || 0);
        }
    }
}
</script>
<style scoped>
.modal-subtitle {
    font-size: 0.78rem;
    color: #6b8c85;
    font-weight: 500;
}

.section-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0 0 0.6rem;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #267A4C;
}

/* Invoice Overview */
.overview-card {
    display: flex;
    align-items: stretch;
    gap: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.1rem;
    background: #fff;
    margin-bottom: 0.25rem;
}

.overview-items {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.overview-item {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
}

.overview-icon {
    color: #94a3b8;
    font-size: 1.1rem;
    margin-top: 0.15rem;
}

.overview-label {
    margin: 0 0 0.15rem;
    color: #7f8c8d;
    font-size: 0.75rem;
}

.overview-value {
    margin: 0;
    color: #16322e;
    font-weight: 700;
    font-size: 0.92rem;
}

.due-badge {
    display: inline-block;
    margin: 0.3rem 0 0;
    font-size: 0.72rem;
    font-weight: 700;
}

.due-badge.is-overdue { color: #dc2626; }
.due-badge.is-soon { color: #d97706; }
.due-badge.is-later { color: #7f8c8d; }

.balance-box {
    position: relative;
    overflow: hidden;
    flex: 0 0 230px;
    border-radius: 12px;
    padding: 1rem 1.1rem;
    background: linear-gradient(135deg, #e4f3ec 0%, #f3faf7 100%);
}

.balance-decor {
    position: absolute;
    right: -0.4rem;
    bottom: -0.6rem;
    font-size: 3rem;
    color: rgba(61, 141, 122, 0.15);
}

.balance-label {
    margin: 0 0 0.35rem;
    color: #267A4C;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.balance-value {
    margin: 0;
    color: #16322e;
    font-weight: 800;
    font-size: 1.35rem;
}

/* Payment Information */
.payment-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.1rem;
    background: #fff;
}

.payment-top-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 1.1rem;
}

.field-group {
    flex: 1;
    max-width: 260px;
}

.form-label {
    display: block;
    margin-bottom: 0.35rem;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.8rem;
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
    border-color: #2e8b57;
    box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
}

.field-input:disabled {
    background: #f5f6f7;
    color: #7f8c8d;
}

.field-input.is-invalid {
    border-color: #e74c3c;
}

.invalid-feedback {
    color: #e74c3c;
    font-size: 0.75rem;
    margin-top: 0.3rem;
}

.split-control {
    text-align: right;
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
    justify-content: flex-end;
    gap: 0.6rem;
    margin-top: 0.4rem;
}

.split-desc {
    color: #7f8c8d;
    font-size: 0.75rem;
    max-width: 220px;
    text-align: left;
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
    border-top: 1px solid #eef2f1;
}

/* Bank details sit on their own row beneath the split they belong to,
   spanning the grid so the fields have room to breathe. */
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

.col-num {
    color: #94a3b8;
    font-weight: 600;
    text-align: center;
}

.col-action {
    display: flex;
    justify-content: center;
}

.mode-select-wrap {
    position: relative;
}

.mode-icon {
    position: absolute;
    left: 0.7rem;
    top: 50%;
    transform: translateY(-50%);
    color: #3D8D7A;
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
    border: 1px dashed #c4d9d2;
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
    background: #f0f7f4;
}

.cash-note {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.6rem;
    color: #7f8c8d;
    font-size: 0.78rem;
}

/* Summary bar */
.payment-summary-bar {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    margin-top: 1rem;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    background: #f3faf7;
}

.summary-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: rgba(61, 141, 122, 0.12);
    color: #3d8d7a;
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
    color: #16322e;
    font-weight: 800;
    font-size: 0.95rem;
}

.summary-value.is-negative {
    color: #dc2626;
}

.summary-divider {
    width: 1px;
    align-self: stretch;
    background: #dbe7e2;
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

/* Footer */
.footer-btn {
    min-width: 132px;
    min-height: 42px;
    padding: 0.55rem 1.1rem;
    border-radius: 10px;
    border: 1px solid transparent;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: all 0.2s ease;
}

.footer-btn.secondary {
    background: #fff;
    border-color: #d8dee7;
    color: #64748b;
}

.footer-btn.secondary:hover {
    background: #f8f9fa;
}

.footer-btn.primary {
    background: #3D8D7A;
    color: #fff;
    box-shadow: 0 4px 12px rgba(61, 141, 122, 0.3);
}

.footer-btn.primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(61, 141, 122, 0.4);
}

/* Payment success modal */
.payment-success-card {
    padding: 0.9rem 0.7rem;
    text-align: center;
}

.payment-success-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 0.7rem;
    display: grid;
    place-items: center;
}

.success-checkmark {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: block;
    stroke-width: 3;
    stroke: #3d8d7a;
    stroke-miterlimit: 10;
    box-shadow: inset 0 0 0 #3d8d7a;
    animation: successCheckmarkFill 0.4s ease-in-out 0.4s forwards, successCheckmarkScale 0.3s ease-in-out 0.9s both;
}

.success-checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 3;
    stroke-miterlimit: 10;
    stroke: #3d8d7a;
    fill: none;
    animation: successCheckmarkStroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.success-checkmark-check {
    transform-origin: 50% 50%;
    stroke: #fff;
    stroke-width: 4;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    animation: successCheckmarkStroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
}

@keyframes successCheckmarkStroke {
    100% { stroke-dashoffset: 0; }
}

@keyframes successCheckmarkScale {
    0%, 100% { transform: none; }
    50% { transform: scale3d(1.1, 1.1, 1); }
}

@keyframes successCheckmarkFill {
    100% { box-shadow: inset 0 0 0 30px #3d8d7a; }
}

.payment-success-card h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #16322e;
}

.payment-success-card p {
    margin: 0.3rem 0 0;
    color: #6b8c85;
    font-size: 0.8rem;
}

.payment-success-breakdown {
    display: grid;
    gap: 0.5rem;
    margin-top: 0.9rem;
    text-align: left;
}

.payment-success-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.7rem;
    padding: 0.65rem 0.75rem;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
}

.payment-success-row span {
    color: #7f8c8d;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.payment-success-row strong {
    color: #16322e;
    font-size: 0.86rem;
    font-weight: 700;
}

.payment-success-row-highlight {
    background: linear-gradient(135deg, #e6f7ee 0%, #d8f2e3 100%);
    border-color: #b9e6ca;
}

.success-footer-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 130px;
    padding: 0.55rem 1.1rem;
    border-radius: 10px;
    border: 1px solid transparent;
    font-weight: 700;
    font-size: 0.82rem;
    transition: all 0.2s ease;
}

.success-footer-btn-close {
    background: #3b82f6;
    color: #fff;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
}

.success-footer-btn-close:hover {
    background: #2563eb;
}

.success-footer-btn-print {
    background: #3D8D7A;
    color: #fff;
    box-shadow: 0 4px 12px rgba(61, 141, 122, 0.3);
}

.success-footer-btn-print:hover:not(:disabled) {
    background: #2f6f5f;
}

.success-footer-btn-print:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .overview-card {
        flex-direction: column;
    }

    .overview-items {
        grid-template-columns: 1fr;
    }

    .balance-box {
        flex: 1;
    }

    .payment-top-row {
        flex-direction: column;
    }

    .field-group {
        max-width: 100%;
    }

    .split-control {
        text-align: left;
        width: 100%;
    }

    .split-switch-row {
        justify-content: flex-start;
    }

    .payment-table-header,
    .payment-table-row {
        grid-template-columns: 30px 1fr 130px 50px;
    }
}
</style>
