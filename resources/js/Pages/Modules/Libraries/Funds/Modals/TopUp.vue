<template>
    <div v-if="showModal" class="modal-overlay" :class="{ active: showModal }" @click.self="hide">
        <div class="modal-container" style="max-width:480px" @click.stop>
            <div class="modal-header">
                <div class="modal-header-icon"><i class="ri-add-circle-line"></i></div>
                <div>
                    <h2>Top-up Fund</h2>
                    <p class="modal-header-kicker">{{ fund ? fund.name : '' }}</p>
                </div>
                <button class="close-btn" @click="hide"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 p-3 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
                    <div class="text-muted fs-12">Current Balance</div>
                    <div class="fw-bold fs-18">₱{{ fund ? Number(fund.balance).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '0.00' }}</div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" v-model="form.amount" class="form-control" :class="{ 'is-invalid': errors.amount }" placeholder="0.00" min="0.01" step="0.01">
                    </div>
                    <div class="invalid-feedback d-block" v-if="errors.amount">{{ errors.amount[0] }}</div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" v-model="form.date" class="form-control" :class="{ 'is-invalid': errors.date }">
                    <div class="invalid-feedback" v-if="errors.date">{{ errors.date[0] }}</div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" v-model="form.description" class="form-control" placeholder="e.g. Monthly replenishment">
                </div>
                <div class="alert alert-success" v-if="success">{{ success }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" @click="hide"><i class="ri-close-line"></i> Cancel</button>
                <button type="button" class="btn btn-save" :disabled="saving" @click="submit">
                    <i class="ri-add-circle-line" v-if="!saving"></i>
                    <i class="ri-loader-4-line spinner" v-else></i>
                    {{ saving ? 'Processing...' : 'Top-up' }}
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
            form: { amount: null, date: new Date().toISOString().split('T')[0], description: null },
            errors: {},
            saving: false,
            success: null,
            showModal: false,
        };
    },
    methods: {
        show(fund) {
            this.fund    = fund;
            this.form    = { amount: null, date: new Date().toISOString().split('T')[0], description: null };
            this.errors  = {};
            this.success = null;
            this.showModal = true;
        },
        hide() { this.showModal = false; },
        submit() {
            this.saving = true;
            this.errors = {};
            axios.post(`/libraries/funds/${this.fund.id}/top-up`, this.form)
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
