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
                    <div class="form-group mb-3">
                        <label class="form-label">GL Code <span class="text-danger">*</span></label>
                        <input type="text" v-model="form.gl_code" class="form-control" :class="{ 'is-invalid': form.errors.gl_code }" placeholder="e.g. PCF-001">
                        <div class="invalid-feedback" v-if="form.errors.gl_code">{{ form.errors.gl_code }}</div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Weekly Budget <span class="text-muted">(optional)</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" v-model="form.weekly_budget" class="form-control" placeholder="0.00" min="0" step="0.01">
                        </div>
                    </div>
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
    data() {
        return {
            form: useForm({ id: null, name: null, gl_code: null, weekly_budget: null }),
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
                weekly_budget: null,
            }).reset();
            this.form.clearErrors();
            this.editable = false;
            this.saveSuccess = false;
            this.showModal = true;
        },
        edit(fund) {
            this.form.id            = fund.id;
            this.form.name          = fund.name;
            this.form.gl_code       = fund.gl_code;
            this.form.weekly_budget = fund.weekly_budget;
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
            if (this.editable) {
                this.form.put(`/libraries/funds/${this.form.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.saveSuccess = true;
                        this.$emit('update');
                        setTimeout(() => this.hide(), 1200);
                    },
                });
            } else {
                this.form.post('/libraries/funds', {
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
