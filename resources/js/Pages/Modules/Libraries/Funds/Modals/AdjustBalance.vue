<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" style="max-width:480px" @click.stop>
            <div class="modal-header">
                <div class="modal-header-icon"><i class="ri-scales-line"></i></div>
                <div>
                    <h2>Adjust Balance</h2>
                    <p class="modal-header-kicker">{{ fund ? fund.name : '' }} — correction only</p>
                </div>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning fs-12 mb-3">
                    <i class="ri-alert-line me-1"></i>
                    This directly overrides the fund balance with no transaction record. Use only for cash count corrections.
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">New Balance <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" v-model="form.balance" class="form-control" :class="{ 'is-invalid': errors.balance }" placeholder="0.00" min="0" step="0.01">
                    </div>
                    <div class="invalid-feedback d-block" v-if="errors.balance">{{ errors.balance[0] }}</div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                    <input type="text" v-model="form.reason" class="form-control" :class="{ 'is-invalid': errors.reason }" placeholder="e.g. Cash count on 2026-05-29 showed ₱250">
                    <div class="invalid-feedback" v-if="errors.reason">{{ errors.reason[0] }}</div>
                </div>
                <div class="alert alert-success" v-if="success">{{ success }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide"><i class="ri-close-line"></i> Cancel</button>
                <button type="button" class="btn btn-save" :disabled="saving" @click="submit">
                    <i class="ri-scales-line" v-if="!saving"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ saving ? 'Saving...' : 'Apply Adjustment' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    emits: ['done'],
    data() {
        return {
            fund: null,
            form: { balance: null, reason: null },
            errors: {},
            saving: false,
            success: null,
            showModal: false,
        };
    },
    methods: {
        show(fund) {
            this.fund    = fund;
            this.form    = { balance: fund.balance, reason: null };
            this.errors  = {};
            this.success = null;
            this.showModal = true;
        },
        hide() { this.showModal = false; },
        submit() {
            this.saving = true;
            this.errors = {};
            axios.patch(`/libraries/funds/${this.fund.id}/balance`, this.form)
                .then(res => {
                    this.success = res.data.message;
                    this.$emit('done');
                    setTimeout(() => this.hide(), 1200);
                })
                .catch(err => { this.errors = err.response?.data?.errors || {}; })
                .finally(() => { this.saving = false; });
        },
    },
};
</script>
