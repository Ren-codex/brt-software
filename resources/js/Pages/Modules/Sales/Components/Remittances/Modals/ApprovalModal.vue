<template>
    <div
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-lg">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="modal-header-icon">
                        <i class="ri-checkbox-circle-line"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0">Remittance Verification</h5>
                        <p class="modal-subtitle mb-0" v-if="item">
                            {{ item.remittance_no }} &middot; {{ formatCurrency(item.total_amount) }}
                        </p>
                    </div>
                </div>
                <button class="close-btn" @click="hide">
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="success-alert" v-if="saveSuccess">
                    <i class="ri-checkbox-circle-fill"></i>
                    <span>Verification has been saved successfully!</span>
                </div>

                <form @submit.prevent="submit">
                    <!-- Approve / Disapprove toggle -->
                    <div class="mb-3">
                        <div class="d-flex gap-3" style="font-size:14px">
                            <label class="d-flex align-items-center gap-1">
                                <input type="radio" v-model="form.status" value="Approve"> Verify
                            </label>
                            <label class="d-flex align-items-center gap-1">
                                <input type="radio" v-model="form.status" value="Disapprove"> Disapprove
                            </label>
                        </div>
                        <span class="error-message" v-if="form.errors.status">{{ form.errors.status }}</span>
                    </div>

                    <!-- Cash verification — only shown when approving -->
                    <template v-if="form.status === 'Approve'">
                        <div class="summary-panel mb-3" v-if="item">
                            <div>
                                <div class="summary-label">Remittance Total</div>
                                <div class="summary-value">{{ formatCurrency(item.total_amount) }}</div>
                            </div>
                            <div class="text-end">
                                <div class="summary-label">Submitted by</div>
                                <div class="summary-rep">{{ item.created_by?.fullname || '-' }}</div>
                            </div>
                        </div>

                        <!-- What the rep physically hands over: cash to count and
                             cheques to receive. A transfer is already in the bank,
                             so it is noted apart, never mixed into the cash. -->
                        <div class="remit-handover mb-3" v-if="cashToRemit > 0 || chequeToRemit > 0">
                            <div class="remit-stat cash" v-if="cashToRemit > 0">
                                <div class="remit-stat-label"><i class="ri-money-dollar-circle-line"></i> Cash to remit</div>
                                <div class="remit-stat-value">{{ formatCurrency(cashToRemit) }}</div>
                            </div>
                            <div class="remit-stat cheque" v-if="chequeToRemit > 0">
                                <div class="remit-stat-label"><i class="ri-file-list-3-line"></i> Cheques<span v-if="chequeCount"> ({{ chequeCount }})</span></div>
                                <div class="remit-stat-value">{{ formatCurrency(chequeToRemit) }}</div>
                            </div>
                        </div>
                        <p class="remit-transfer-note" v-if="transferTotal > 0">
                            <i class="ri-bank-line"></i>
                            {{ formatCurrency(transferTotal) }} by bank transfer is already in the bank — just confirm it below, no cash to count.
                        </p>

                        <div class="mb-2">
                            <label class="form-label">Amount Received <span class="text-danger">*</span></label>
                            <div class="table-responsive">
                                <table class="table table-sm received-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Payment</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-end">Amount Received</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in paymentBreakdown" :key="row.mode">
                                            <td>
                                                {{ row.mode }}
                                                <div v-if="row.banks && row.banks.length" class="mode-bank">
                                                    <i class="ri-bank-line"></i> {{ row.banks.join(', ') }}
                                                </div>
                                            </td>
                                            <td class="text-end">{{ row.amount !== null ? formatCurrency(row.amount) : '—' }}</td>
                                            <td class="text-end">
                                                <!-- A transfer is already in the bank, so it is confirmed,
                                                     not counted. Cash and cheques are still keyed in. -->
                                                <template v-if="isElectronic(row.mode)">
                                                    <button
                                                        v-if="!isConfirmed(row)"
                                                        type="button"
                                                        class="confirm-received-btn"
                                                        @click="confirmReceived(row)"
                                                    >
                                                        <i class="ri-checkbox-circle-line"></i> Confirm in bank
                                                    </button>
                                                    <span v-else class="received-confirmed">
                                                        <i class="ri-checkbox-circle-fill"></i> In the bank
                                                        <button type="button" class="undo-confirm" @click="undoConfirm(row)">undo</button>
                                                    </span>
                                                </template>
                                                <input
                                                    v-else
                                                    type="number"
                                                    class="form-control form-control-sm text-end ms-auto"
                                                    placeholder="0.00"
                                                    v-model.number="form.received_breakdown[row.mode]"
                                                    min="0"
                                                    step="0.01"
                                                >
                                            </td>
                                        </tr>
                                        <tr v-if="paymentBreakdown.length === 0">
                                            <td colspan="3" class="text-center text-muted">No receipts found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <span class="error-message" v-if="form.errors.received_amount">{{ form.errors.received_amount }}</span>
                        </div>

                        <!-- Variance display -->
                        <div class="variance-line mb-3" v-if="hasReceivedInput">
                            <span class="variance-label">Variance:</span>
                            <span :class="['variance-badge', variance === 0 ? 'ok' : 'warn']">
                                <template v-if="variance === 0">
                                    ₱0.00 &mdash; Amounts match ✓
                                </template>
                                <template v-else>
                                    {{ variance > 0 ? '+' : '' }}{{ formatCurrency(variance) }} &mdash; Discrepancy
                                </template>
                            </span>
                        </div>

                        <!-- Received via — derived from which payment modes were entered above, not picked separately -->
                        <div class="mb-3" v-if="receivedViaModes.length">
                            <label class="form-label">Received Via</label>
                            <div>
                                <span v-for="mode in receivedViaModes" :key="mode" class="received-via-chip">{{ mode }}</span>
                            </div>
                            <span class="error-message" v-if="form.errors.received_via">{{ form.errors.received_via }}</span>
                        </div>

                        <!-- Reference no. — only when Check is one of the received modes -->
                        <div class="mb-3" v-if="hasCheckMode">
                            <label class="form-label">Reference No. <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                :class="{ 'input-error': form.errors.reference_no }"
                                placeholder="Enter check reference no..."
                                v-model="form.reference_no"
                            >
                            <span class="error-message" v-if="form.errors.reference_no">{{ form.errors.reference_no }}</span>
                        </div>
                    </template>

                    <!-- Remarks / discrepancy reason. When there's a variance, this becomes
                         the required explanation that gets carried into the journal entry. -->
                    <div class="mb-3">
                        <label class="form-label">
                            {{ form.status === 'Approve' && variance !== 0 ? 'Reason for Discrepancy' : 'Remarks' }}
                            <span v-if="form.status === 'Approve' && variance !== 0" class="text-danger">*</span>
                        </label>
                        <textarea
                            class="form-control"
                            :placeholder="remarksPlaceholder"
                            rows="4"
                            v-model="form.remarks"
                            :class="{ 'input-error': form.errors.remarks }"
                        ></textarea>
                        <div class="variance-hint" v-if="form.status === 'Approve' && variance !== 0">
                            Discrepancy of {{ formatCurrency(Math.abs(variance)) }} ({{ variance > 0 ? 'overage' : 'shortage' }}) — please explain. This reason is recorded on the journal entry.
                        </div>
                        <span class="error-message" v-if="form.errors.remarks">{{ form.errors.remarks }}</span>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" @click="hide">
                            <i class="ri-close-line"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-save" :disabled="form.processing || !form.status">
                            <i class="ri-save-line" v-if="!form.processing"></i>
                            <i class="ri-loader-4-line spinner" v-else></i>
                            {{ form.processing ? 'Saving...' : 'Submit' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
    props: ['item'],
    data() {
        return {
            form: useForm({
                status: 'Approve',
                received_amount: null,
                received_breakdown: {},
                received_via: null,
                reference_no: null,
                remarks: null,
            }),
            showModal: false,
            saveSuccess: false,
        };
    },
    computed: {
        // Amount received is captured per payment mode present on the
        // remittance's receipts, since a single remittance can bundle cash,
        // GCash, check, and bank transfer receipts together.
        paymentBreakdown() {
            if (!this.item || !Array.isArray(this.item.receipts)) return [];
            const order = ['Cash', 'GCash', 'Check', 'Bank Transfer'];
            const totals = {};
            const banks = {};
            this.item.receipts.forEach(r => {
                const mode = r.payment_mode || 'Cash';
                totals[mode] = (totals[mode] || 0) + (parseFloat(r.amount_paid) || 0);
                // Where the money landed, for the modes that name a bank.
                if (r.bank_account) {
                    (banks[mode] = banks[mode] || new Set()).add(r.bank_account);
                }
            });
            return Object.keys(totals)
                .sort((a, b) => {
                    const ai = order.indexOf(a);
                    const bi = order.indexOf(b);
                    if (ai === -1 && bi === -1) return a.localeCompare(b);
                    if (ai === -1) return 1;
                    if (bi === -1) return -1;
                    return ai - bi;
                })
                .map(mode => ({ mode, amount: totals[mode], banks: banks[mode] ? Array.from(banks[mode]) : [] }));
        },
        // Physical cash the rep counts out of the drawer.
        cashToRemit() {
            return this.paymentBreakdown
                .filter(r => r.mode.trim().toLowerCase() === 'cash')
                .reduce((sum, r) => sum + (r.amount || 0), 0);
        },
        // Cheques handed over as paper — their own pile, never counted as cash.
        chequeToRemit() {
            return this.paymentBreakdown
                .filter(r => r.mode.trim().toLowerCase() === 'check')
                .reduce((sum, r) => sum + (r.amount || 0), 0);
        },
        chequeCount() {
            if (!this.item || !Array.isArray(this.item.receipts)) return 0;
            return this.item.receipts
                .filter(r => String(r.payment_mode || '').trim().toLowerCase() === 'check').length;
        },
        // Already in the bank — noted apart from the physical handover.
        transferTotal() {
            return this.paymentBreakdown
                .filter(r => this.isElectronic(r.mode))
                .reduce((sum, r) => sum + (r.amount || 0), 0);
        },
        hasReceivedInput() {
            return Object.values(this.form.received_breakdown || {})
                .some(v => v !== null && v !== '' && v !== undefined);
        },
        totalReceived() {
            return Object.values(this.form.received_breakdown || {})
                .reduce((sum, v) => sum + (parseFloat(v) || 0), 0);
        },
        // "Received Via" is no longer picked separately — it's whichever
        // payment modes the verifier actually entered an amount for above.
        receivedViaModes() {
            return Object.entries(this.form.received_breakdown || {})
                .filter(([, v]) => v !== null && v !== '' && v !== undefined)
                .map(([mode]) => mode);
        },
        hasCheckMode() {
            return this.receivedViaModes.some(m => m.toLowerCase() === 'check');
        },
        variance() {
            if (!this.hasReceivedInput) return 0;
            if (!this.item) return 0;
            return Math.round(((this.totalReceived - parseFloat(this.item.total_amount)) + Number.EPSILON) * 100) / 100;
        },
        remarksPlaceholder() {
            if (this.form.status === 'Approve' && this.variance !== 0) {
                return 'Required — explain the variance...';
            }
            return 'Optional notes...';
        },
    },
    methods: {
        show() {
            this.form.reset();
            const breakdown = {};
            this.paymentBreakdown.forEach(row => {
                breakdown[row.mode] = null;
            });
            this.form.received_breakdown = breakdown;
            this.showModal = true;
        },
        // A transfer or GCash is money already sitting in an account, so it is
        // confirmed rather than counted; cash and cheques are keyed in by hand.
        isElectronic(mode) {
            return ['bank transfer', 'gcash'].includes(String(mode || '').trim().toLowerCase());
        },
        isConfirmed(row) {
            const v = this.form.received_breakdown[row.mode];
            return v !== null && v !== '' && v !== undefined;
        },
        confirmReceived(row) {
            // Confirming says the full amount landed; a short transfer is the
            // bank's problem to chase, not something verified here.
            this.form.received_breakdown[row.mode] = row.amount;
        },
        undoConfirm(row) {
            this.form.received_breakdown[row.mode] = null;
        },
        submit() {
            if (this.form.status === 'Approve' && !this.hasReceivedInput) {
                this.form.setError('received_amount', 'Enter the amount received for at least one payment method.');
                return;
            }
            if (this.form.status === 'Approve' && this.variance !== 0 && !this.form.remarks?.trim()) {
                this.form.setError('remarks', 'Remarks are required when there is a variance.');
                return;
            }
            if (this.hasCheckMode && !this.form.reference_no?.trim()) {
                this.form.setError('reference_no', 'Reference no. is required for check payments.');
                return;
            }
            this.form.received_amount = this.totalReceived;
            this.form.received_via = this.receivedViaModes.join(', ');
            // Absolute path: the relative form resolved against the current URL
            // and broke on any route deeper than /remittances.
            this.form.post(`/remittances/${this.item.id}/approve`, {
                onSuccess: () => {
                    this.saveSuccess = true;
                    setTimeout(() => {
                        this.$emit('reload');
                        this.hide();
                    }, 1500);
                },
            });
        },
        hide() {
            this.saveSuccess = false;
            this.showModal = false;
            this.$emit('hide');
            this.form.reset();
            this.form.clearErrors();
        },
        formatCurrency(value) {
            if (!value && value !== 0) return '-';
            return '₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    },
};
</script>

<style scoped>
.modal-overlay { z-index: 50; }
.modal-container { overflow: hidden; width: 100%; padding: 0; }
.modal-body { padding: 1.2rem 1.4rem; }
.modal-title { font-size: 0.95rem; font-weight: 700; color: var(--brt-ink); }
.modal-subtitle { font-size: 0.76rem; color: var(--brt-muted); }
.form-actions .btn { min-width: 120px; }

.summary-panel {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--brt-c-f4fbf8);
    border: 1px solid var(--brt-border);
    border-radius: 10px;
    padding: 0.7rem 1rem;
}
.summary-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--brt-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.summary-value {
    font-size: 1rem;
    font-weight: 700;
    color: var(--brt-c-16423c);
}
.summary-rep {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--brt-c-16422c);
}

.remit-handover {
    display: flex;
    gap: 0.75rem;
}
.remit-stat {
    flex: 1;
    border-radius: 10px;
    padding: 0.7rem 0.9rem;
    border: 1px solid transparent;
}
.remit-stat.cash   { background: #ecfdf3; border-color: #bbf7d0; }
.remit-stat.cheque { background: #fff7ed; border-color: #fed7aa; }
.remit-stat-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--brt-muted);
}
.remit-stat.cash .remit-stat-label   { color: #15803d; }
.remit-stat.cheque .remit-stat-label { color: #c2410c; }
.remit-stat-value {
    margin-top: 2px;
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--brt-ink);
}
.remit-transfer-note {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: -0.4rem 0 0.9rem;
    font-size: 0.78rem;
    color: var(--brt-muted);
}
.remit-transfer-note i { color: #0369a1; }

.received-table { font-size: 0.85rem; }
.received-table th { color: var(--brt-muted); font-weight: 600; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em; border-top: none; }
.received-table td { vertical-align: middle; }
.received-table input { width: 120px; }
.mode-bank {
    font-size: 0.72rem;
    color: var(--brt-muted);
    margin-top: 2px;
}
.mode-bank i { font-size: 0.8rem; }

.confirm-received-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border: 1px solid var(--brt-primary);
    background: rgba(var(--brt-primary-rgb), 0.08);
    color: var(--brt-primary);
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
}
.confirm-received-btn:hover { background: rgba(var(--brt-primary-rgb), 0.16); }

.received-confirmed {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #166534;
    font-size: 0.8rem;
    font-weight: 600;
}
.received-confirmed i { font-size: 1rem; }
.undo-confirm {
    border: none;
    background: transparent;
    color: var(--brt-muted);
    font-size: 0.72rem;
    font-weight: 500;
    text-decoration: underline;
    padding: 0;
}

.received-via-chip {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    margin-right: 6px;
    border-radius: 20px;
    background: rgba(var(--brt-primary-rgb),0.12);
    color: var(--brt-ink);
    font-weight: 600;
    font-size: 0.78rem;
}

.variance-line {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
}
.variance-label { color: var(--brt-muted); }
.variance-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.8rem;
}
.variance-badge.ok { background: #e8f5e9; color: #2e7d32; }
.variance-badge.warn { background: #ffebee; color: #c62828; }

.variance-hint {
    font-size: 0.75rem;
    color: #c62828;
    margin-top: 4px;
}
</style>
