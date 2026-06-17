<template>
    <div
        v-show="showModal"
        class="modal-overlay"
        :class="{ active: showModal }"
        @click.self="hide"
    >
        <div class="modal-container modal-md">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="modal-header-icon">
                        <i class="ri-checkbox-circle-line"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0">Remittance Approval</h5>
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
                    <span>Approval has been saved successfully!</span>
                </div>

                <form @submit.prevent="submit">
                    <!-- Approve / Disapprove toggle -->
                    <div class="mb-3">
                        <div class="d-flex gap-3" style="font-size:14px">
                            <label class="d-flex align-items-center gap-1">
                                <input type="radio" v-model="form.status" value="Approve"> Approve
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
                            <label class="form-label">Amount Received (₱) <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                class="form-control"
                                :class="{ 'input-error': form.errors.received_amount }"
                                placeholder="Enter amount physically counted..."
                                v-model.number="form.received_amount"
                                min="0"
                                step="0.01"
                            >
                            <span class="error-message" v-if="form.errors.received_amount">{{ form.errors.received_amount }}</span>
                        </div>

                        <!-- Variance display -->
                        <div class="variance-line mb-3" v-if="form.received_amount !== null && form.received_amount !== ''">
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
                    </template>

                    <!-- Remarks -->
                    <div class="mb-3">
                        <label class="form-label">
                            Remarks
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
                            Variance detected — please explain the difference.
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
                remarks: null,
            }),
            showModal: false,
            saveSuccess: false,
        };
    },
    computed: {
        variance() {
            if (this.form.received_amount === null || this.form.received_amount === '') return 0;
            if (!this.item) return 0;
            return Math.round(((parseFloat(this.form.received_amount) - parseFloat(this.item.total_amount)) + Number.EPSILON) * 100) / 100;
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
            this.showModal = true;
        },
        submit() {
            if (this.form.status === 'Approve' && this.variance !== 0 && !this.form.remarks?.trim()) {
                this.form.setError('remarks', 'Remarks are required when there is a variance.');
                return;
            }
            this.form.post(`remittances/${this.item.id}/approve`, {
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
            return '₱' + Number(value).toFixed(2);
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
