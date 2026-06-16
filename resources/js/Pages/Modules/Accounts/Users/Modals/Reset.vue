<template>
    <b-modal v-model="showModal" size="md" header-class="p-3 bg-light" title="Reset Password" class="v-modal-custom" modal-class="zoomIn" centered no-close-on-backdrop hide-footer>
        <div v-if="!resetSuccess">
            <BRow>
                <BCol lg="12" class="mt-n1 mb-3">
                    <div class="alert alert-warning alert-dismissible alert-additional fade show mb-xl-0 material-shadow" role="alert">
                        <div class="alert-body">
                            <div class="d-flex mt-n1 mb-n2">
                                <div class="flex-shrink-0 me-2">
                                    <i class="ri-alert-line fs-14 align-middle"></i>
                                </div>
                                <div class="flex-grow-1 mt-1">
                                    <h5 class="fs-11 text-primary">Reset password for <b class="text-uppercase">{{ user?.name }}</b>?</h5>
                                </div>
                            </div>
                        </div>
                        <div class="bg-warning p-2 rounded alert-content">
                            <p class="mb-0 fs-10">Their password will be reset to the default. They'll be required to set a new username and password on their next login — just like a new account.</p>
                        </div>
                    </div>
                </BCol>
            </BRow>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <BButton variant="light" @click="closeModal">Cancel</BButton>
                <BButton variant="primary" @click="submit()" :disabled="processing">
                    <i class="ri-loader-4-line spinner" v-if="processing"></i>
                    {{ processing ? 'Resetting...' : 'Reset Password' }}
                </BButton>
            </div>
        </div>
        <div v-else>
            <div class="success-alert">
                <div class="success-alert-icon"><i class="ri-checkbox-circle-fill"></i></div>
                <div class="success-alert-body">
                    <strong>Password reset</strong>
                    <div class="credentials-list">
                        <div class="credentials-row">
                            <span class="credentials-label">Username</span>
                            <span class="credentials-value">{{ user?.username }}</span>
                        </div>
                        <div v-if="user?.email" class="credentials-row">
                            <span class="credentials-label">Email</span>
                            <span class="credentials-value">{{ user.email }}</span>
                        </div>
                        <div class="credentials-row">
                            <span class="credentials-label">Default Password</span>
                            <span class="credentials-value">{{ defaultPassword }}</span>
                        </div>
                    </div>
                    <p class="credentials-note">{{ user?.name }} will be required to set a new username and password on their next login.</p>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <BButton variant="primary" @click="closeModal">Done</BButton>
            </div>
        </div>
    </b-modal>
</template>
<script>
import { useForm } from '@inertiajs/vue3';

export default {
    data() {
        return {
            showModal: false,
            user: null,
            form: useForm({
                id: null,
                option: 'reset_password'
            }),
            processing: false,
            resetSuccess: false,
            defaultPassword: ''
        }
    },
    methods: {
        show(data) {
            this.form.id = data.id;
            this.user = data;
            this.showModal = true;
            this.form.errors = {};
            this.resetSuccess = false;
            this.defaultPassword = '';
        },
        closeModal() {
            this.showModal = false;
        },
        submit() {
            this.processing = true;
            this.form.option = 'reset_password';
            this.form.put('/users/' + this.form.id,{
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = page?.props?.flash || {};
                    this.defaultPassword = flash.password || '';
                    this.resetSuccess = true;
                    this.processing = false;
                    this.$emit('update', true);
                },
                onError: () => {
                    this.processing = false;
                }
            });
        }
    }
}
</script>
<style scoped>
.success-alert {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    border: 1px solid #6ee7b7;
    color: #065f46;
    padding: 1.1rem 1.25rem;
    border-radius: 14px;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.success-alert-icon {
    font-size: 1.25rem;
    line-height: 1.4;
}

.success-alert-body {
    flex: 1;
}

.credentials-list {
    margin-top: 0.6rem;
    background: rgba(255, 255, 255, 0.55);
    border-radius: 10px;
    padding: 0.6rem 0.9rem;
}

.credentials-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.3rem 0;
}

.credentials-row + .credentials-row {
    border-top: 1px solid rgba(110, 231, 183, 0.5);
}

.credentials-label {
    font-size: 0.8rem;
    color: #0d6e4f;
    font-weight: 500;
}

.credentials-value {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-weight: 600;
    color: #065f46;
}

.credentials-note {
    margin: 0.6rem 0 0;
    font-size: 0.8rem;
    color: #0d6e4f;
}
</style>
