<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" @click.stop>
            <div class="modal-header">
                <div class="modal-header-icon"><i class="ri-safe-line"></i></div>
                <div>
                    <h2>{{ editable ? 'Edit Fund' : 'New Petty Cash Fund' }}</h2>
                    <p class="modal-header-kicker">{{ editable ? 'Update fund details' : 'Create a new petty cash fund' }}</p>
                </div>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <form @submit.prevent="submit">
                    <div class="form-group mb-3">
                        <label class="form-label">Fund Name <span class="text-danger">*</span></label>
                        <input type="text" v-model="form.name" class="form-control" :class="{ 'is-invalid': form.errors.name }" placeholder="e.g. Main Office Fund">
                        <div class="invalid-feedback" v-if="form.errors.name">{{ form.errors.name }}</div>
                    </div>
                    <div class="form-group mb-3" v-if="editable">
                        <label class="form-label">GL Code <span class="text-danger">*</span></label>
                        <input type="text" v-model="form.gl_code" class="form-control" :class="{ 'is-invalid': form.errors.gl_code }" placeholder="e.g. PCF-001">
                        <div class="invalid-feedback" v-if="form.errors.gl_code">{{ form.errors.gl_code }}</div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Low Balance Threshold <span class="text-muted">(optional)</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" v-model="form.low_balance_threshold" class="form-control" placeholder="e.g. 500.00" min="0" step="0.01">
                        </div>
                        <small class="text-muted">Admins are notified when the fund balance drops below this amount.</small>
                    </div>
                    <template v-if="!editable">
                        <div class="form-group mb-3">
                            <label class="form-label">Initial Balance <span class="text-muted">(optional)</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" v-model="form.initial_balance" class="form-control" :class="{ 'is-invalid': form.errors.initial_balance }" placeholder="0.00" min="0.01" step="0.01">
                            </div>
                            <div class="invalid-feedback" v-if="form.errors.initial_balance">{{ form.errors.initial_balance }}</div>
                        </div>
                        <div class="form-group mb-3" v-if="form.initial_balance">
                            <label class="form-label">Funded From</label>
                            <select v-model="form.source_type" class="form-control">
                                <option value="cash">Cash on Hand</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="form-group mb-3" v-if="form.initial_balance && form.source_type === 'bank'">
                            <label class="form-label">Bank Account</label>
                            <select v-model="form.bank_account_id" class="form-control">
                                <option value="">-- Select bank --</option>
                                <option v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ b.bank_name }} — {{ b.account_name }}</option>
                            </select>
                        </div>
                    </template>
                    <div class="success-alert" v-if="saveSuccess">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Fund saved successfully!</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide"><i class="ri-close-line"></i> Cancel</button>
                <button type="button" class="btn btn-save" :disabled="form.processing" @click="submit">
                    <i class="ri-save-line" v-if="!form.processing"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ form.processing ? 'Saving...' : 'Save Fund' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
    emits: ['add', 'update'],
    props: {
        bankAccounts: { type: Array, default: () => [] },
    },
    data() {
        return {
            form: useForm({ id: null, name: null, gl_code: null, low_balance_threshold: null, initial_balance: null, source_type: 'cash', bank_account_id: null }),
            showModal: false,
            editable: false,
            saveSuccess: false,
        };
    },
    methods: {
        show() {
            this.form.defaults({
                id: null,
                name: null,
                gl_code: null,
                low_balance_threshold: null,
                initial_balance: null,
                source_type: 'cash',
                bank_account_id: null,
            }).reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(fund) {
            this.form.id                    = fund.id;
            this.form.name                  = fund.name;
            this.form.gl_code               = fund.gl_code;
            this.form.low_balance_threshold = fund.low_balance_threshold;
            this.editable   = true;
            this.saveSuccess = false;
            this.showModal   = true;
        },
        hide() {
            this.form.reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = false;
        },
        submit() {
            if (this.form.source_type !== 'bank') {
                this.form.bank_account_id = null;
            }
            if (this.editable) {
                this.form.put(`/accounting/funds/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.saveSuccess = true;
                        this.$emit('update');
                        setTimeout(() => this.hide(), 1200);
                    },
                });
            } else {
                this.form.post('/accounting/funds', {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.saveSuccess = true;
                        this.$emit('add');
                        setTimeout(() => this.hide(), 1200);
                    },
                });
            }
        },
    },
};
</script>
