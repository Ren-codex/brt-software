<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container modal-lg" @click.stop>
            <div class="modal-header">
                <div class="modal-header-icon"><i class="ri-wallet-3-line"></i></div>
                <div>
                    <h2>{{ editable ? 'Edit Expense' : 'Record Expense' }}</h2>
                    <p class="modal-header-kicker">Petty Cash Disbursement</p>
                </div>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label class="form-label">Petty Cash Fund <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-wallet-3-line input-icon"></i>
                                <select v-model="form.fund_id" class="form-control" :class="{ 'input-error': hasError('fund_id') }">
                                    <option value="">Select fund</option>
                                    <option v-for="f in dropdowns.funds" :key="f.id" :value="f.id">{{ f.name }}</option>
                                </select>
                            </div>
                            <span class="error-message" v-if="hasError('fund_id')">{{ getError('fund_id') }}</span>
                        </div>
                        <div class="form-group form-group-half">
                            <label class="form-label">Expense Type <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-price-tag-3-line input-icon"></i>
                                <select v-model="form.expense_type" class="form-control" :class="{ 'input-error': hasError('expense_type') }">
                                    <option value="">Select type</option>
                                    <option value="operational">Operational</option>
                                    <option value="utilities">Utilities</option>
                                    <option value="supplies">Supplies</option>
                                    <option value="transportation">Transportation</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="others">Others</option>
                                </select>
                            </div>
                            <span class="error-message" v-if="hasError('expense_type')">{{ getError('expense_type') }}</span>
                        </div>
                    </div>

                    <!-- Fund impact panel — visible as soon as a fund is selected -->
                    <div v-if="selectedFundData" class="fund-split-card" :class="splitCardClass" style="margin-bottom:1rem;">
                        <div class="fund-split-body">
                            <div class="fund-split-left">
                                <span class="split-label">Available Balance</span>
                                <span class="split-value">{{ fmt(selectedFundData.balance) }}</span>
                                <span class="split-fund-name">{{ selectedFundData.name }}</span>
                            </div>
                            <div class="fund-split-right" :class="splitRightClass">
                                <span class="split-label">After This Expense</span>
                                <span class="split-value" :class="afterExpenseClass">{{ afterExpenseDisplay }}</span>
                                <span v-if="fundPanelStatus" class="split-status" :class="fundPanelStatusClass">
                                    <i :class="fundPanelStatusIcon"></i> {{ fundPanelStatus }}
                                </span>
                            </div>
                        </div>

                        <!-- Weekly budget footer (only if fund has a weekly budget) -->
                        <div v-if="selectedFundData.weekly_budget > 0" class="fund-split-footer">
                            <div class="split-budget-row">
                                <span class="split-budget-label"><i class="ri-bar-chart-box-line me-1"></i>Weekly Budget</span>
                                <span class="split-budget-meta">
                                    {{ fmt(projectedWeeklySpent) }} of {{ fmt(selectedFundData.weekly_budget) }}
                                    <span v-if="typedAmount > 0 && !insufficientBalance" class="split-budget-delta">
                                        (+{{ fmt(typedAmount) }})
                                    </span>
                                </span>
                            </div>
                            <div class="split-progress-track">
                                <div class="split-progress-fill" :style="budgetBarStyle"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <span class="input-prefix">₱</span>
                                <input type="number" v-model="form.amount" class="form-control form-control-prefixed"
                                    :class="{ 'input-error': hasError('amount') }"
                                    placeholder="0.00" min="0" step="0.01">
                            </div>
                            <span class="error-message" v-if="hasError('amount')">{{ getError('amount') }}</span>
                        </div>
                        <div class="form-group form-group-half">
                            <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                            <div class="input-wrapper">
                                <i class="ri-calendar-line input-icon"></i>
                                <input type="date" v-model="form.expense_date" class="form-control"
                                    :class="{ 'input-error': hasError('expense_date') }">
                            </div>
                            <span class="error-message" v-if="hasError('expense_date')">{{ getError('expense_date') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <div class="input-wrapper">
                            <i class="ri-chat-3-line input-icon"></i>
                            <input type="text" v-model="form.description" class="form-control" placeholder="What was this expense for?">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Receipt / Document
                            <span class="text-muted small ms-1">(JPG, PNG, PDF — max 5 MB)</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="ri-attachment-2 input-icon"></i>
                            <input type="file" ref="receiptInput" class="form-control" accept=".jpg,.jpeg,.png,.pdf" @change="onReceiptChange">
                        </div>
                        <div v-if="existingReceiptUrl" class="mt-1 small">
                            <a :href="existingReceiptUrl" target="_blank" class="receipt-link">
                                <i class="ri-file-line me-1"></i>View existing receipt
                            </a>
                        </div>
                        <span class="error-message" v-if="hasError('receipt')">{{ getError('receipt') }}</span>
                    </div>

                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>{{ editable ? 'Expense updated successfully!' : 'Expense recorded successfully!' }}</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide">
                    <i class="ri-close-line"></i> Cancel
                </button>
                <button type="button" class="btn btn-save" :disabled="form.processing" @click="submit">
                    <i class="ri-save-line" v-if="!form.processing"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ form.processing ? 'Saving...' : (editable ? 'Update Expense' : 'Record Expense') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
    props: ['dropdowns'],
    data() {
        return {
            form: useForm({
                fund_id:      '',
                expense_type: '',
                amount:       '',
                expense_date: '',
                description:  '',
                receipt:      null,
            }),
            localErrors:        {},
            showModal:          false,
            editable:           false,
            saveSuccess:        false,
            existingReceiptUrl: null,
        };
    },
    computed: {
        selectedFundData() {
            if (!this.form.fund_id) return null;
            return this.dropdowns.funds.find(f => f.id == this.form.fund_id) || null;
        },
        typedAmount() {
            const v = parseFloat(this.form.amount);
            return isNaN(v) || v <= 0 ? 0 : v;
        },
        afterBalance() {
            if (!this.selectedFundData) return null;
            return parseFloat(this.selectedFundData.balance || 0) - this.typedAmount;
        },
        insufficientBalance() {
            if (!this.selectedFundData || this.typedAmount <= 0) return false;
            return this.typedAmount > parseFloat(this.selectedFundData.balance || 0);
        },
        overWeeklyBudget() {
            if (!this.selectedFundData || !this.selectedFundData.weekly_budget) return false;
            if (this.typedAmount <= 0) return false;
            const remaining = this.selectedFundData.weekly_remaining;
            return remaining !== null && this.typedAmount > remaining;
        },
        afterExpenseDisplay() {
            if (this.typedAmount <= 0 || !this.selectedFundData) return '—';
            return this.fmt(this.afterBalance);
        },
        afterExpenseClass() {
            if (this.typedAmount <= 0) return 'fund-panel-value-neutral';
            if (this.insufficientBalance || this.overWeeklyBudget) return 'fund-panel-value-warn';
            return 'fund-panel-value-ok';
        },
        splitCardClass() {
            if (this.insufficientBalance || this.overWeeklyBudget) return 'split-card-warn';
            if (this.typedAmount > 0) return 'split-card-ok';
            return '';
        },
        splitRightClass() {
            if (this.insufficientBalance || this.overWeeklyBudget) return 'split-right-warn';
            if (this.typedAmount > 0) return 'split-right-ok';
            return '';
        },
        fundPanelStatus() {
            if (this.typedAmount <= 0) return null;
            if (this.insufficientBalance) {
                const short = (this.typedAmount - parseFloat(this.selectedFundData.balance || 0))
                    .toLocaleString('en-PH', { minimumFractionDigits: 2 });
                return `Out-of-pocket by ₱${short} — include in replenishment`;
            }
            if (this.overWeeklyBudget) {
                const remaining = this.selectedFundData.weekly_remaining ?? 0;
                const over = (this.typedAmount - remaining).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                return `Over weekly budget by ₱${over}`;
            }
            return 'Sufficient balance';
        },
        fundPanelStatusClass() {
            if (this.insufficientBalance) return 'status-warn';
            if (this.overWeeklyBudget) return 'status-warn';
            return 'status-ok';
        },
        fundPanelStatusIcon() {
            if (this.insufficientBalance) return 'ri-error-warning-fill';
            if (this.overWeeklyBudget) return 'ri-alert-fill';
            return 'ri-checkbox-circle-fill';
        },
        projectedWeeklySpent() {
            if (!this.selectedFundData) return 0;
            return this.selectedFundData.weekly_spent + this.typedAmount;
        },
        budgetBarStyle() {
            if (!this.selectedFundData || !this.selectedFundData.weekly_budget) return {};
            const pct = Math.min((this.projectedWeeklySpent / this.selectedFundData.weekly_budget) * 100, 100);
            const color = pct >= 100 ? '#ef4444' : pct >= 75 ? '#f59e0b' : '#22c55e';
            return { width: pct + '%', background: color };
        },
    },
    methods: {
        fmt(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        },
        hasError(field) {
            return !!(this.localErrors[field] || this.form.errors[field]);
        },
        getError(field) {
            return this.localErrors[field] || this.form.errors[field];
        },
        validate() {
            const errors = {};
            if (!this.form.fund_id)      errors.fund_id      = 'Please select a petty cash fund.';
            if (!this.form.expense_type) errors.expense_type = 'Please select an expense type.';
            if (!this.form.amount || parseFloat(this.form.amount) <= 0)
                errors.amount = 'Amount must be greater than ₱0.';
            if (!this.form.expense_date) errors.expense_date = 'Please enter the expense date.';
            return errors;
        },
        show() {
            this.form.reset();
            this.form.expense_date = new Date().toISOString().split('T')[0];
            this.localErrors        = {};
            this.saveSuccess        = false;
            this.editable           = false;
            this.existingReceiptUrl = null;
            this.showModal          = true;
        },
        edit(data) {
            this.form.clearErrors();
            this.form.id           = data.id;
            this.form.fund_id      = data.fund_id || '';
            this.form.expense_type = data.expense_type;
            this.form.amount       = data.amount;
            this.form.expense_date = data.expense_date;
            this.form.description  = data.description;
            this.form.receipt      = null;
            this.localErrors        = {};
            this.editable           = true;
            this.saveSuccess        = false;
            this.existingReceiptUrl = data.receipt_path ? `/storage/${data.receipt_path}` : null;
            this.showModal          = true;
        },
        onReceiptChange(e) {
            this.form.receipt = e.target.files[0] || null;
        },
        submit() {
            this.localErrors = this.validate();
            if (Object.keys(this.localErrors).length > 0) return;

            if (this.editable) {
                this.form.put(`/expenses/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.saveSuccess = true;
                        setTimeout(() => { this.$emit('update', true); this.hide(); }, 1200);
                    },
                });
            } else {
                this.form.post('/expenses', {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.saveSuccess = true;
                        setTimeout(() => { this.$emit('add', true); this.hide(); }, 1200);
                    },
                });
            }
        },
        hide() {
            this.form.reset();
            this.form.clearErrors();
            this.localErrors        = {};
            this.saveSuccess        = false;
            this.editable           = false;
            this.existingReceiptUrl = null;
            this.showModal          = false;
            this.$emit('close');
        },
    },
}
</script>

<style scoped>
/* ── Receipt link ──────────────────────────────────────────── */
.receipt-link { color: #3d8d7a; font-weight: 600; text-decoration: none; }
.receipt-link:hover { text-decoration: underline; }

/* ── ₱ prefix on amount input ─────────────────────────────── */
.input-prefix {
    position: absolute;
    left: 2.4rem;
    top: 50%;
    transform: translateY(-50%);
    font-weight: 600;
    color: #3d8d7a;
    pointer-events: none;
    z-index: 1;
    font-size: 0.95rem;
}
.form-control-prefixed { padding-left: 3.2rem !important; }

/* ── Two-tone split card ───────────────────────────────────── */
.fund-split-card {
    display: flex;
    flex-direction: column;
    border-radius: 12px;
    overflow: hidden;
    border: 1.5px solid #a8d5c5;
    transition: border-color 0.25s;
}
.split-card-ok     { border-color: #6ee7b7; }
.split-card-warn   { border-color: #fcd34d; }
.split-card-danger { border-color: #fca5a5; }

.fund-split-body {
    display: flex;
    min-height: 80px;
}

/* Left pane — always teal */
.fund-split-left {
    flex: 1;
    padding: 0.9rem 1.1rem;
    background: linear-gradient(150deg, #1e5c49 0%, #2d8c6e 100%);
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.1rem;
}

/* Right pane — color shifts with status */
.fund-split-right {
    flex: 1;
    padding: 0.9rem 1.1rem;
    background: #f4faf8;
    border-left: 1.5px solid #a8d5c5;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.1rem;
    transition: background 0.25s, border-color 0.25s;
}
.split-right-ok     { background: #f0fdf4; border-left-color: #6ee7b7; }
.split-right-warn   { background: #fffbeb; border-left-color: #fcd34d; }
.split-right-danger { background: #fff5f5; border-left-color: #fca5a5; }

/* Labels and values */
.split-label {
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}
.fund-split-left  .split-label { color: rgba(255,255,255,0.65); }
.fund-split-right .split-label { color: #6b8c85; }

.split-value {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.1;
    transition: color 0.2s;
}
.fund-split-left  .split-value { color: #ffffff; }
.fund-split-right .split-value { color: #1a4d3d; }

.fund-panel-value-neutral { color: #9db9b0 !important; }
.fund-panel-value-ok      { color: #15803d !important; }
.fund-panel-value-warn    { color: #b45309 !important; }
.fund-panel-value-danger  { color: #dc2626 !important; }

.split-fund-name {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.5);
    margin-top: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.split-status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 600;
    margin-top: 0.2rem;
}
.split-status i { font-size: 0.78rem; }
.status-ok     { color: #15803d; }
.status-warn   { color: #b45309; }
.status-danger { color: #dc2626; }

/* Footer — weekly budget bar */
.fund-split-footer {
    padding: 0.55rem 1.1rem 0.65rem;
    background: #edf6f2;
    border-top: 1.5px solid #a8d5c5;
}
.split-budget-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.3rem;
}
.split-budget-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #4d8c7a;
}
.split-budget-meta {
    font-size: 0.77rem;
    font-weight: 600;
    color: #3d6b5e;
}
.split-budget-delta {
    color: #9db9b0;
    font-weight: 500;
    font-size: 0.71rem;
    margin-left: 0.2rem;
}
.split-progress-track {
    height: 5px;
    border-radius: 999px;
    background: #c4dfd5;
    overflow: hidden;
}
.split-progress-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease, background 0.3s;
}
</style>
