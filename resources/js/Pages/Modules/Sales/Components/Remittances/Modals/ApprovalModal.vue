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
                                        <tr v-for="row in displayRows" :key="row.mode">
                                            <td>{{ row.mode }}</td>
                                            <td class="text-end">{{ row.amount !== null ? formatCurrency(row.amount) : '—' }}</td>
                                            <td class="text-end">
                                                <div class="d-flex align-items-center justify-content-end gap-1">
                                                    <input
                                                        type="number"
                                                        class="form-control form-control-sm text-end"
                                                        placeholder="0.00"
                                                        v-model.number="form.received_breakdown[row.mode]"
                                                        min="0"
                                                        step="0.01"
                                                    >
                                                    <button
                                                        v-if="customModes.includes(row.mode)"
                                                        type="button"
                                                        class="remove-mode-btn"
                                                        title="Remove"
                                                        @click="removeCustomMode(row.mode)"
                                                    >
                                                        <i class="ri-close-line"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="displayRows.length === 0">
                                            <td colspan="3" class="text-center text-muted">No receipts found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Add a payment mode not already listed above (e.g. money received a different way than originally recorded) -->
                            <div class="add-mode-row d-flex align-items-center gap-2 mt-2">
                                <select class="form-select form-select-sm" style="max-width:170px" v-model="newModeSelect">
                                    <option value="">+ Add payment mode…</option>
                                    <option v-for="opt in availableModeOptions" :key="opt" :value="opt">{{ opt }}</option>
                                    <option value="__other__">Other…</option>
                                </select>
                                <input
                                    v-if="newModeSelect === '__other__'"
                                    type="text"
                                    class="form-control form-control-sm"
                                    style="max-width:170px"
                                    placeholder="Payment mode name"
                                    v-model="newModeCustomLabel"
                                    @keydown.enter.prevent="addPaymentMode"
                                >
                                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="!canAddMode" @click="addPaymentMode">
                                    <i class="ri-add-line"></i> Add
                                </button>
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
            // Payment modes added manually, on top of whatever the remittance's
            // own receipts already contribute to paymentBreakdown.
            customModes: [],
            newModeSelect: '',
            newModeCustomLabel: '',
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
            this.item.receipts.forEach(r => {
                const mode = r.payment_mode || 'Cash';
                totals[mode] = (totals[mode] || 0) + (parseFloat(r.amount_paid) || 0);
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
                .map(mode => ({ mode, amount: totals[mode] }));
        },
        // Rows actually shown in the table: the auto ones derived from the
        // remittance's receipts, plus any the verifier added manually. Manually
        // added rows have no "expected" amount, only a received amount.
        displayRows() {
            const autoModes = this.paymentBreakdown.map(r => r.mode);
            const extra = this.customModes.filter(m => !autoModes.includes(m));
            return [
                ...this.paymentBreakdown,
                ...extra.map(mode => ({ mode, amount: null })),
            ];
        },
        standardModeOptions() {
            return ['Cash', 'GCash', 'Check', 'Bank Transfer'];
        },
        availableModeOptions() {
            const used = this.displayRows.map(r => r.mode.toLowerCase());
            return this.standardModeOptions.filter(m => !used.includes(m.toLowerCase()));
        },
        canAddMode() {
            if (this.newModeSelect === '__other__') {
                return !!this.newModeCustomLabel.trim();
            }
            return !!this.newModeSelect;
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
            this.customModes = [];
            this.newModeSelect = '';
            this.newModeCustomLabel = '';
            const breakdown = {};
            this.paymentBreakdown.forEach(row => {
                breakdown[row.mode] = null;
            });
            this.form.received_breakdown = breakdown;
            this.showModal = true;
        },
        addPaymentMode() {
            const label = this.newModeSelect === '__other__' ? this.newModeCustomLabel.trim() : this.newModeSelect;
            if (!label) return;
            const exists = this.displayRows.some(r => r.mode.toLowerCase() === label.toLowerCase());
            if (!exists) {
                this.customModes.push(label);
                this.form.received_breakdown[label] = null;
            }
            this.newModeSelect = '';
            this.newModeCustomLabel = '';
        },
        removeCustomMode(mode) {
            this.customModes = this.customModes.filter(m => m !== mode);
            delete this.form.received_breakdown[mode];
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
.modal-title { font-size: 0.95rem; font-weight: 700; color: #16322e; }
.modal-subtitle { font-size: 0.76rem; color: #6b8c85; }
.form-actions .btn { min-width: 120px; }

.summary-panel {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f4fbf8;
    border: 1px solid #c4d9d2;
    border-radius: 10px;
    padding: 0.7rem 1rem;
}
.summary-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #6b8c85;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.summary-value {
    font-size: 1rem;
    font-weight: 700;
    color: #16423c;
}
.summary-rep {
    font-size: 0.85rem;
    font-weight: 600;
    color: #16422c;
}

.received-table { font-size: 0.85rem; }
.received-table th { color: #6b8c85; font-weight: 600; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em; border-top: none; }
.received-table td { vertical-align: middle; }
.received-table input { width: 120px; }

.remove-mode-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: none;
    background: transparent;
    color: #c62828;
    border-radius: 6px;
}
.remove-mode-btn:hover { background: #ffebee; }

.add-mode-row .form-select,
.add-mode-row .form-control { font-size: 0.8rem; }

.received-via-chip {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    margin-right: 6px;
    border-radius: 20px;
    background: rgba(61,141,122,0.12);
    color: #16322e;
    font-weight: 600;
    font-size: 0.78rem;
}

.variance-line {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
}
.variance-label { color: #6b8c85; }
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
